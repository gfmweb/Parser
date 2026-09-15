<script setup lang="ts">
import { computed, ref } from 'vue';
import { onClickOutside, onKeyStroke } from '@vueuse/core';
import StatusBadge from '@/components/StatusBadge.vue';
import StarRating from '@/components/StarRating.vue';
import type { Organization } from '@/types';
import { organizationDisplayName } from '@/utils/organizationTitle';

const props = defineProps<{
  organization: Organization;
}>();

const emit = defineEmits<{
  view: [];
  reparse: [];
  delete: [];
}>();

const title = computed(() => organizationDisplayName(props.organization));
const parseErrorText = computed(() => {
  const parseError = props.organization.parse_error;

  return parseError === null || parseError === '' ? null : parseError;
});
const canReparse = computed(
  () => props.organization.parse_status === 'done' || props.organization.parse_status === 'failed',
);

const menuOpen = ref(false);
const menuRoot = ref<HTMLElement | null>(null);

onClickOutside(menuRoot, () => {
  menuOpen.value = false;
});

onKeyStroke('Escape', () => {
  if (!menuOpen.value) {
    return;
  }

  menuOpen.value = false;
});

function toggleMenu(): void {
  menuOpen.value = !menuOpen.value;
}

function requestDelete(): void {
  menuOpen.value = false;
  emit('delete');
}
</script>

<template>
  <article class="glass-panel flex h-full flex-col gap-4 p-5" :class="{ 'relative z-30': menuOpen }">
    <div class="relative z-20 flex items-start justify-between gap-3">
      <div class="flex min-w-0 items-start gap-1">
        <h3 class="line-clamp-2 min-w-0 text-base font-semibold text-white">{{ title }}</h3>
        <div ref="menuRoot" class="relative shrink-0">
          <button
            class="rounded-md p-1 text-slate-300 hover:bg-white/10 hover:text-white"
            type="button"
            aria-haspopup="true"
            :aria-expanded="menuOpen"
            :aria-controls="`organization-actions-${organization.id}`"
            aria-label="Действия"
            @click.stop="toggleMenu"
          >
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <circle cx="10" cy="4" r="1.5" />
              <circle cx="10" cy="10" r="1.5" />
              <circle cx="10" cy="16" r="1.5" />
            </svg>
          </button>
          <div
            v-if="menuOpen"
            :id="`organization-actions-${organization.id}`"
            class="absolute right-0 z-30 mt-1 min-w-[10rem] rounded-xl border border-white/20 bg-slate-900 p-1 shadow-xl"
          >
            <button
              class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-red-200 hover:bg-white/10"
              type="button"
              aria-label="Удалить"
              @click="requestDelete"
            >
              <span class="min-w-0 flex-1 text-left">Удалить</span>
              <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path
                  d="M7 4h6M4 6h12M8 6v9m4-9v9M6 6l.7 10.2A1.5 1.5 0 0 0 8.2 17.5h3.6a1.5 1.5 0 0 0 1.5-1.3L14 6"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
      <StatusBadge :status="organization.parse_status" />
    </div>

    <div class="mt-auto space-y-1 text-sm text-slate-300">
      <StarRating :rating="organization.rating" show-value />
      <p>{{ organization.review_count }} отзывов</p>
      <p v-if="organization.parse_status === 'failed' && parseErrorText" class="text-red-300">
        {{ parseErrorText }}
      </p>
    </div>

    <div class="flex gap-2">
      <button
        class="glass-btn glass-btn-compact min-w-0 flex-1 text-center leading-tight"
        type="button"
        @click="emit('view')"
      >
        Смотреть отзывы
      </button>
      <button
        v-if="canReparse"
        class="glass-btn-secondary glass-btn-compact min-w-0 flex-1 text-center leading-tight"
        type="button"
        @click="emit('reparse')"
      >
        Перепарсить
      </button>
    </div>
  </article>
</template>
