<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import StarRating from '@/components/StarRating.vue';
import type { Organization } from '@/types';

const props = defineProps<{
  organization: Organization;
}>();

const emit = defineEmits<{
  view: [];
  reparse: [];
}>();

const title = computed(() => props.organization.name ?? props.organization.yandex_url);
const canReparse = computed(
  () => props.organization.parse_status === 'done' || props.organization.parse_status === 'failed',
);
</script>

<template>
  <article class="glass-panel flex h-full flex-col gap-4 p-5">
    <div class="flex items-start justify-between gap-3">
      <h3 class="line-clamp-2 text-base font-semibold text-white">{{ title }}</h3>
      <StatusBadge :status="organization.parse_status" />
    </div>

    <div class="mt-auto space-y-1 text-sm text-slate-300">
      <StarRating :rating="organization.rating" show-value />
      <p>{{ organization.review_count }} отзывов</p>
      <p v-if="organization.parse_status === 'failed' && organization.parse_error" class="text-red-300">
        {{ organization.parse_error }}
      </p>
    </div>

    <div class="flex flex-wrap gap-2">
      <button class="glass-btn" type="button" @click="emit('view')">Смотреть отзывы</button>
      <button
        v-if="canReparse"
        class="glass-btn-secondary"
        type="button"
        @click="emit('reparse')"
      >
        Перепарсить
      </button>
    </div>
  </article>
</template>
