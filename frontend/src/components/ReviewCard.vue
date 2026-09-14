<script setup lang="ts">
import { computed, ref } from 'vue';
import StarRating from '@/components/StarRating.vue';
import type { Review } from '@/types';
import { avatarHue, initialsFromName } from '@/utils/avatar';
import { formatReviewDate } from '@/utils/dates';

const props = defineProps<{
  review: Review;
}>();

const expanded = ref(false);
const author = computed(() => props.review.author_name?.trim() || 'Аноним');
const initials = computed(() => initialsFromName(author.value));
const hue = computed(() => avatarHue(author.value));
const text = computed(() => props.review.text ?? '');
const isLong = computed(() => text.value.length > 300);
const displayedText = computed(() => {
  if (!isLong.value || expanded.value) {
    return text.value;
  }

  return `${text.value.slice(0, 300).trimEnd()}…`;
});
</script>

<template>
  <article class="glass-panel p-5">
    <div class="flex items-start gap-3">
      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
        :style="{ backgroundColor: `hsl(${hue} 70% 42%)` }"
      >
        {{ initials }}
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="font-medium text-white">{{ author }}</p>
          <time class="text-xs text-slate-400">{{ formatReviewDate(review.reviewed_at) }}</time>
        </div>
        <StarRating class="mt-1" :rating="review.rating" />
      </div>
    </div>

    <p v-if="text !== ''" class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-200">
      {{ displayedText }}
    </p>
    <button
      v-if="isLong"
      class="mt-2 text-sm text-indigo-300 hover:text-indigo-200"
      type="button"
      @click="expanded = !expanded"
    >
      {{ expanded ? 'Свернуть' : 'Показать полностью' }}
    </button>
  </article>
</template>
