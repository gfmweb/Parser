<script setup lang="ts">
import { computed } from 'vue';
import StarRating from '@/components/StarRating.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { Organization } from '@/types';
import { organizationDisplayName } from '@/utils/organizationTitle';

const props = defineProps<{
  organization: Organization;
}>();

const parseErrorText = computed(() => {
  const parseError = props.organization.parse_error;

  return parseError === null || parseError === '' ? null : parseError;
});

const emit = defineEmits<{
  back: [];
}>();
</script>

<template>
  <section class="glass-panel p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <h1 class="text-2xl font-semibold text-white">
          {{ organizationDisplayName(organization) }}
        </h1>
        <p v-if="organization.address" class="mt-2 text-slate-300">{{ organization.address }}</p>
      </div>
      <StatusBadge :status="organization.parse_status" />
    </div>
    <p
      v-if="organization.parse_status === 'failed' && parseErrorText"
      class="mt-3 text-sm text-red-300"
    >
      {{ parseErrorText }}
    </p>

    <div class="mt-6 flex flex-wrap items-end gap-8">
      <div>
        <StarRating :rating="organization.rating" size="lg" show-value />
        <p class="mt-1 text-sm text-slate-400">{{ organization.rating_count }} оценок</p>
      </div>
      <p class="text-slate-200">{{ organization.review_count }} отзывов</p>
    </div>

    <div class="mt-6">
      <button class="glass-btn-outline w-full text-center" type="button" @click="emit('back')">Назад</button>
    </div>
  </section>
</template>
