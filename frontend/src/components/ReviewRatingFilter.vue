<script setup lang="ts">
import StarRating from '@/components/StarRating.vue';
import type { RatingCounts } from '@/types';

const props = defineProps<{
  counts: RatingCounts;
  selected: number | null;
}>();

const emit = defineEmits<{
  select: [rating: number | null];
}>();

const stars = [5, 4, 3, 2, 1] as const;

function labelFor(rating: number): string {
  if (rating === 1) {
    return '1 звезда';
  }

  if (rating >= 2 && rating <= 4) {
    return `${rating} звезды`;
  }

  return `${rating} звёзд`;
}

function onSelect(rating: number): void {
  emit('select', props.selected === rating ? null : rating);
}
</script>

<template>
  <div class="glass-panel min-w-0 p-4">
    <div class="mb-3 flex items-center justify-between gap-3">
      <p class="text-sm font-medium text-slate-200">По звёздам</p>
      <button
        v-if="selected !== null"
        class="text-xs text-indigo-300 hover:text-indigo-200"
        type="button"
        @click="emit('select', null)"
      >
        Все
      </button>
    </div>
    <ul class="space-y-1">
      <li v-for="rating in stars" :key="rating">
        <button
          class="flex w-full min-w-0 items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition"
          :class="
            selected === rating
              ? 'bg-indigo-500/30 text-white'
              : 'text-slate-300 hover:bg-white/5'
          "
          type="button"
          :aria-label="labelFor(rating)"
          :aria-pressed="selected === rating"
          @click="onSelect(rating)"
        >
          <StarRating :rating="rating" size="sm" />
          <span class="tabular-nums text-slate-400">{{ counts[rating] }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
