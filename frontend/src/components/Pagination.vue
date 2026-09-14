<script setup lang="ts">
import { computed } from 'vue';
import { paginationItems } from '@/utils/pagination';

const props = defineProps<{
  currentPage: number;
  lastPage: number;
}>();

const emit = defineEmits<{
  'page-change': [page: number];
}>();

const items = computed(() => paginationItems(props.currentPage, props.lastPage));
const isFirst = computed(() => props.currentPage <= 1);
const isLast = computed(() => props.currentPage >= props.lastPage || props.lastPage <= 0);

function goTo(page: number): void {
  if (page < 1 || page > props.lastPage || page === props.currentPage) {
    return;
  }

  emit('page-change', page);
}
</script>

<template>
  <nav v-if="lastPage > 1" class="flex flex-wrap items-center justify-center gap-2" aria-label="Пагинация">
    <button class="glass-btn-secondary" type="button" :disabled="isFirst" @click="goTo(currentPage - 1)">
      Назад
    </button>

    <template v-for="(item, index) in items" :key="`${item}-${index}`">
      <span v-if="item === 'ellipsis'" class="px-1 text-slate-400">…</span>
      <button
        v-else
        class="min-w-10"
        :class="item === currentPage ? 'glass-btn' : 'glass-btn-secondary'"
        type="button"
        :aria-current="item === currentPage ? 'page' : undefined"
        @click="goTo(item)"
      >
        {{ item }}
      </button>
    </template>

    <button class="glass-btn-secondary" type="button" :disabled="isLast" @click="goTo(currentPage + 1)">
      Вперёд
    </button>
  </nav>
</template>
