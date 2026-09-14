<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const email = ref('');
const password = ref('');

async function onSubmit(): Promise<void> {
  try {
    await auth.login(email.value, password.value);
    await router.push({ name: 'dashboard' });
  } catch {
    // Сообщение уже записано в auth.error
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4 py-8">
    <form class="glass-panel w-full max-w-md p-8" @submit.prevent="onSubmit">
      <div class="mb-8 text-center">
        <p class="text-sm uppercase tracking-[0.2em] text-indigo-300">Parser</p>
        <h1 class="mt-2 text-2xl font-semibold text-white">Yandex Reviews</h1>
        <p class="mt-2 text-sm text-slate-300">Войдите, чтобы управлять организациями</p>
      </div>

      <label class="mb-4 block text-sm text-slate-300">
        Email
        <input
          v-model="email"
          class="glass-input mt-1"
          type="email"
          name="email"
          autocomplete="username"
          required
        />
      </label>

      <label class="mb-6 block text-sm text-slate-300">
        Пароль
        <input
          v-model="password"
          class="glass-input mt-1"
          type="password"
          name="password"
          autocomplete="current-password"
          required
        />
      </label>

      <button class="glass-btn flex w-full items-center justify-center gap-2" type="submit" :disabled="auth.isLoading">
        <span v-if="auth.isLoading" class="spinner" aria-hidden="true" />
        <span>{{ auth.isLoading ? 'Входим…' : 'Войти' }}</span>
      </button>

      <p v-if="auth.error" class="mt-4 text-center text-sm text-red-300" role="alert">
        {{ auth.error }}
      </p>
    </form>
  </div>
</template>
