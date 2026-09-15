<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

async function onLogout(): Promise<void> {
  await auth.logout();
  await router.push({ name: 'login' });
}
</script>

<template>
  <header class="glass-panel flex min-w-0 items-center justify-between gap-3 px-4 py-4">
    <router-link class="min-w-0 text-left" :to="{ name: 'dashboard' }">
      <p class="text-xs uppercase tracking-[0.2em] text-indigo-300">Parser</p>
      <h1 class="truncate text-lg font-semibold text-white">Yandex Reviews</h1>
    </router-link>
    <div class="flex shrink-0 items-center gap-4">
      <span class="hidden text-sm text-slate-300 sm:inline">{{ auth.user?.email }}</span>
      <button class="glass-btn glass-btn-compact" type="button" @click="onLogout">Выйти</button>
    </div>
  </header>
</template>
