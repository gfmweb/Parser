<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppBreadcrumbs from '@/components/AppBreadcrumbs.vue';
import AppNavbar from '@/components/AppNavbar.vue';
import OrganizationSummary from '@/components/OrganizationSummary.vue';
import Pagination from '@/components/Pagination.vue';
import ReviewCard from '@/components/ReviewCard.vue';
import ReviewRatingFilter from '@/components/ReviewRatingFilter.vue';
import ReviewSkeleton from '@/components/ReviewSkeleton.vue';
import { useParseProgress } from '@/composables/useParseProgress';
import { useOrganizationStore } from '@/stores/organization';
import type { RatingCounts } from '@/types';
import { organizationDisplayName } from '@/utils/organizationTitle';

const route = useRoute();
const router = useRouter();
const organizations = useOrganizationStore();
const reviewsSection = ref<HTMLElement | null>(null);
const skipReviewsScroll = ref(true);
const skeletonItems = [1, 2, 3, 4];

const organizationId = computed(() => {
  const raw = route.params.id;
  const value = Array.isArray(raw) ? raw[0] : raw;
  const parsed = Number.parseInt(value ?? '', 10);

  return Number.isInteger(parsed) ? parsed : 0;
});

const page = computed(() => {
  const raw = route.query.page;
  const value = Array.isArray(raw) ? raw[0] : raw;
  const parsed = Number.parseInt(value ?? '1', 10);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : 1;
});

const ratingFilter = computed((): number | null => {
  const raw = route.query.rating;
  const value = Array.isArray(raw) ? raw[0] : raw;
  const parsed = Number.parseInt(value ?? '', 10);

  if (parsed >= 1 && parsed <= 5) {
    return parsed;
  }

  return null;
});

const ratingCounts = computed((): RatingCounts | null => organizations.reviewsMeta?.rating_counts ?? null);

const { progress, isConnected } = useParseProgress(organizationId);
const organization = computed(() => organizations.currentOrganization);
const isParsing = computed(
  () => organization.value?.parse_status === 'parsing' || progress.value?.status === 'parsing',
);

const breadcrumbCurrent = computed(() => {
  if (organization.value === null) {
    return 'Организация';
  }

  return organizationDisplayName(organization.value);
});

const parsedCount = computed(() => {
  if (progress.value !== null) {
    return progress.value.parsed;
  }

  return organization.value?.latest_parse_job?.parsed_reviews ?? 0;
});

const totalCount = computed(() => {
  if (progress.value !== null && progress.value.total > 0) {
    return progress.value.total;
  }

  const fromJob = organization.value?.latest_parse_job?.total_reviews ?? 0;
  if (fromJob > 0) {
    return fromJob;
  }

  return Math.max(parsedCount.value, organization.value?.review_count ?? 0);
});

const progressPercent = computed(() => {
  if (totalCount.value <= 0) {
    return 0;
  }

  return Math.min(100, Math.round((parsedCount.value / totalCount.value) * 100));
});

watch(
  [organizationId, page, ratingFilter],
  async ([id], previous) => {
    if (id <= 0) {
      return;
    }

    const previousId = previous?.[0];
    const orgChanged = previousId === undefined || previousId !== id;

    if (orgChanged) {
      skipReviewsScroll.value = true;
      await Promise.all([
        organizations.fetchOne(id),
        organizations.fetchReviews(id, page.value, ratingFilter.value),
      ]).catch(() => undefined);

      return;
    }

    await organizations.fetchReviews(id, page.value, ratingFilter.value).catch(() => undefined);

    if (!skipReviewsScroll.value) {
      reviewsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    skipReviewsScroll.value = false;
  },
  { immediate: true },
);

watch(
  () => progress.value,
  async (payload) => {
    if (payload === null) {
      return;
    }

    organizations.applyProgress(payload);

    if (payload.status === 'done' || payload.status === 'failed') {
      await Promise.all([
        organizations.fetchOne(organizationId.value),
        organizations.fetchReviews(organizationId.value, page.value, ratingFilter.value),
      ]).catch(() => undefined);
    }
  },
);

async function onPageChange(nextPage: number): Promise<void> {
  skipReviewsScroll.value = false;
  await router.replace({
    query: {
      ...route.query,
      page: String(nextPage),
    },
  });
}

async function onRatingSelect(rating: number | null): Promise<void> {
  skipReviewsScroll.value = true;
  const query = { ...route.query, page: '1' };

  if (rating === null) {
    delete query.rating;
  } else {
    query.rating = String(rating);
  }

  await router.replace({ query });
}

function onBack(): void {
  void router.push({ name: 'dashboard' });
}
</script>

<template>
  <div class="mx-auto flex min-h-screen w-full min-w-0 max-w-7xl flex-col gap-6 px-4 py-8">
    <AppNavbar />
    <AppBreadcrumbs :current="breadcrumbCurrent" />

    <p v-if="organizations.error" class="text-sm text-red-300">{{ organizations.error }}</p>

    <div class="grid min-w-0 items-start gap-6 lg:grid-cols-[minmax(16rem,20rem)_1fr]">
      <aside class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-6">
        <section
          v-if="organizations.isLoadingCurrent && organization === null"
          class="glass-panel space-y-4 p-6"
        >
          <div class="skeleton h-8 w-1/2" />
          <div class="skeleton h-4 w-2/3" />
          <div class="skeleton h-6 w-40" />
        </section>
        <OrganizationSummary
          v-else-if="organization !== null"
          :organization="organization"
          @back="onBack"
        />

        <section v-if="isParsing" class="glass-panel p-6">
          <div class="mb-3 flex items-center justify-between text-sm text-slate-300">
            <p>Загрузка отзывов: {{ parsedCount }} / {{ totalCount }}</p>
            <span class="text-xs text-slate-400">{{ isConnected ? 'live' : 'переподключение…' }}</span>
          </div>
          <div class="progress-track">
            <div class="progress-fill" :style="{ width: `${progressPercent}%` }" />
          </div>
        </section>

        <ReviewRatingFilter
          v-if="ratingCounts !== null"
          :counts="ratingCounts"
          :selected="ratingFilter"
          @select="onRatingSelect"
        />
      </aside>

      <section ref="reviewsSection" class="min-w-0 space-y-4">
        <div v-if="organizations.isLoadingReviews" class="grid gap-4 lg:grid-cols-2">
          <ReviewSkeleton v-for="item in skeletonItems" :key="item" />
        </div>

        <p v-else-if="organizations.reviews.length === 0" class="glass-panel p-8 text-slate-300">
          {{ ratingFilter !== null ? 'Нет отзывов с такой оценкой.' : 'Отзывов пока нет.' }}
        </p>

        <div v-else class="grid gap-4 lg:grid-cols-2">
          <ReviewCard v-for="review in organizations.reviews" :key="review.id" :review="review" />
        </div>

        <Pagination
          v-if="organizations.reviewsMeta"
          :current-page="organizations.reviewsMeta.current_page"
          :last-page="organizations.reviewsMeta.last_page"
          @page-change="onPageChange"
        />
      </section>
    </div>
  </div>
</template>
