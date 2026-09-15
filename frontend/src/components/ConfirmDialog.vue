<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { onKeyStroke } from '@vueuse/core';

const props = withDefaults(
  defineProps<{
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    isLoading?: boolean;
    error?: string | null;
  }>(),
  {
    confirmLabel: 'Удалить',
    cancelLabel: 'Отмена',
    isLoading: false,
    error: null,
  },
);

const emit = defineEmits<{
  confirm: [];
  cancel: [];
}>();

const cancelButton = ref<HTMLButtonElement | null>(null);
const confirmButton = ref<HTMLButtonElement | null>(null);
let previousActive: HTMLElement | null = null;

const describedBy = computed(() => {
  if (props.error !== null && props.error !== '') {
    return 'confirm-dialog-message confirm-dialog-error';
  }

  return 'confirm-dialog-message';
});

watch(
  () => props.open,
  async (open) => {
    if (open) {
      const active = document.activeElement;
      previousActive = active instanceof HTMLElement ? active : null;
      await nextTick();
      cancelButton.value?.focus();
      return;
    }

    previousActive?.focus();
    previousActive = null;
  },
  { immediate: true },
);

onKeyStroke('Escape', () => {
  if (props.open && !props.isLoading) {
    emit('cancel');
  }
});

function focusableButtons(): HTMLButtonElement[] {
  return [cancelButton.value, confirmButton.value].filter(
    (button): button is HTMLButtonElement => button instanceof HTMLButtonElement && !button.disabled,
  );
}

function onDialogKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Tab') {
    return;
  }

  const buttons = focusableButtons();

  if (buttons.length === 0) {
    return;
  }

  const first = buttons[0];
  const last = buttons[buttons.length - 1];

  if (first === undefined || last === undefined) {
    return;
  }

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
    return;
  }

  if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

function cancel(): void {
  if (props.isLoading) {
    return;
  }

  emit('cancel');
}

function confirm(): void {
  if (props.isLoading) {
    return;
  }

  emit('confirm');
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8 backdrop-blur-sm"
      role="presentation"
      @click.self="cancel"
    >
      <div
        class="glass-panel w-full max-w-md p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        :aria-describedby="describedBy"
        :aria-busy="isLoading"
        @keydown="onDialogKeydown"
      >
        <h2 id="confirm-dialog-title" class="text-lg font-semibold text-white">{{ title }}</h2>
        <p id="confirm-dialog-message" class="mt-3 text-sm text-slate-300">{{ message }}</p>
        <p v-if="error" id="confirm-dialog-error" class="mt-3 text-sm text-red-300" role="alert">{{ error }}</p>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button
            ref="cancelButton"
            class="glass-btn-secondary"
            type="button"
            :disabled="isLoading"
            @click="cancel"
          >
            {{ cancelLabel }}
          </button>
          <button
            ref="confirmButton"
            class="glass-btn-danger flex items-center justify-center gap-2"
            type="button"
            :disabled="isLoading"
            @click="confirm"
          >
            <span v-if="isLoading" class="spinner" aria-hidden="true" />
            <span>{{ confirmLabel }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
