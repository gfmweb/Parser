<script setup lang="ts">
import { computed } from 'vue';
import type { ParseStatus } from '@/types';

const props = defineProps<{
  status: ParseStatus;
}>();

const label = computed(() => {
  switch (props.status) {
    case 'pending':
      return 'pending';
    case 'parsing':
      return 'parsing';
    case 'done':
      return 'done';
    case 'failed':
      return 'Ошибка парсера';
    default:
      return props.status;
  }
});
</script>

<template>
  <span class="status-badge" :class="`status-${status}`">{{ label }}</span>
</template>
