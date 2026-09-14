<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    rating: number | null;
    max?: number;
    showValue?: boolean;
    size?: 'sm' | 'lg';
  }>(),
  {
    max: 5,
    showValue: false,
    size: 'sm',
  },
);

const filledCount = computed(() => {
  if (props.rating === null) {
    return 0;
  }

  return Math.max(0, Math.min(props.max, Math.round(props.rating)));
});

const stars = computed(() => Array.from({ length: props.max }, (_, index) => index < filledCount.value));
</script>

<template>
  <span class="inline-flex items-center gap-1" :class="size === 'lg' ? 'text-2xl' : 'text-sm'">
    <span
      v-for="(filled, index) in stars"
      :key="index"
      :class="filled ? 'text-amber-300' : 'text-slate-600'"
      aria-hidden="true"
    >
      ★
    </span>
    <span v-if="showValue && rating !== null" class="ml-1 text-base text-slate-200">
      {{ rating.toFixed(1) }}
    </span>
  </span>
</template>
