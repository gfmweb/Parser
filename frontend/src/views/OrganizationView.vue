<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppNavbar from '@/components/AppNavbar.vue';
import Pagination from '@/components/Pagination.vue';
import ReviewCard from '@/components/ReviewCard.vue';
import ReviewSkeleton from '@/components/ReviewSkeleton.vue';
import StarRating from '@/components/StarRating.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { useParseProgress } from '@/composables/useParseProgress';
import { useOrganizationStore } from '@/stores/organization';

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

const { progress, isConnected } = useParseProgress(organizationId);
const organization = computed(() => organizations.currentOrganization);
const isParsing = computed(
  () => organization.value?.parse_status === 'parsing' || progress.value?.status === 'parsing',
);

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
  organizationId,
  async (id) => {
    if (id <= 0) {
      return;
    }

    skipReviewsScroll.value = true;
    await Promise.all([organizations.fetchOne(id), organizations.fetchReviews(id, page.value)]).catch(
      () => undefined,
    );
  },
  { immediate: true },
);

watch(page, async (nextPage, previousPage) => {
  if (organizationId.value <= 0 || nextPage === previousPage) {
    return;
  }

  await organizations.fetchReviews(organizationId.value, nextPage).catch(() => undefined);

  if (!skipReviewsScroll.value) {
    reviewsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  skipReviewsScroll.value = false;
});

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
        organizations.fetchReviews(organizationId.value, page.value),
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
</script>

<template>
  <div class="mx-auto flex min-h-screen max-w-5xl flex-col gap-6 px-4 py-8">
    <AppNavbar />

    <section v-if="organizations.isLoadingCurrent && organization === null" class="glass-panel space-y-4 p-8">
      <div class="skeleton h-8 w-1/2" />
      <div class="skeleton h-4 w-2/3" />
      <div class="skeleton h-6 w-40" />
    </section>

    <template v-else-if="organization !== null">
      <section class="glass-panel p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-sm text-indigo-300">Организация</p>
            <h1 class="mt-1 text-2xl font-semibold text-white">
              {{ organization.name ?? organization.yandex_url }}
            </h1>
            <p v-if="organization.address" class="mt-2 text-slate-300">{{ organization.address }}</p>
          </div>
          <StatusBadge :status="organization.parse_status" />
        </div>
        <p
          v-if="organization.parse_status === 'failed' && organization.parse_error"
          class="mt-3 text-sm text-red-300"
        >
          {{ organization.parse_error }}
        </p>

        <div class="mt-6 flex flex-wrap items-end gap-8">
          <div>
            <StarRating :rating="organization.rating" size="lg" show-value />
            <p class="mt-1 text-sm text-slate-400">{{ organization.rating_count }} оценок</p>
          </div>
          <p class="text-slate-200">{{ organization.review_count }} отзывов</p>
        </div>
      </section>

      <section v-if="isParsing" class="glass-panel p-6">
        <div class="mb-3 flex items-center justify-between text-sm text-slate-300">
          <p>Загрузка отзывов: {{ parsedCount }} / {{ totalCount }}</p>
          <span class="text-xs text-slate-400">{{ isConnected ? 'live' : 'переподключение…' }}</span>
        </div>
        <div class="progress-track">
          <div class="progress-fill" :style="{ width: `${progressPercent}%` }" />
        </div>
      </section>
    </template>

    <p v-if="organizations.error" class="text-sm text-red-300">{{ organizations.error }}</p>

    <section ref="reviewsSection" class="space-y-4">
      <h2 class="text-lg font-semibold text-white">Отзывы</h2>

      <div v-if="organizations.isLoadingReviews" class="space-y-4">
        <ReviewSkeleton v-for="item in skeletonItems" :key="item" />
      </div>

      <p v-else-if="organizations.reviews.length === 0" class="glass-panel p-8 text-slate-300">
        Отзывов пока нет.
      </p>

      <div v-else class="space-y-4">
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
</template>
