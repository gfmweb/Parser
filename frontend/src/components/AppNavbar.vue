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
  <header class="glass-panel flex items-center justify-between px-6 py-4">
    <router-link class="text-left" :to="{ name: 'dashboard' }">
      <p class="text-xs uppercase tracking-[0.2em] text-indigo-300">Parser</p>
      <h1 class="text-lg font-semibold text-white">Yandex Reviews</h1>
    </router-link>
    <div class="flex items-center gap-4">
      <span class="hidden text-sm text-slate-300 sm:inline">{{ auth.user?.email }}</span>
      <button class="glass-btn" type="button" @click="onLogout">Выйти</button>
    </div>
  </header>
</template>
