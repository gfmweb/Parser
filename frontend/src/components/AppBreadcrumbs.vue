<script setup lang="ts">
defineProps<{
  current: string;
}>();
</script>

<template>
  <nav aria-label="Навигация" class="text-sm text-slate-400">
    <ol class="flex flex-wrap items-center gap-2">
      <li>
        <router-link class="text-indigo-300 hover:text-indigo-200" :to="{ name: 'dashboard' }">
          Организации
        </router-link>
      </li>
      <li aria-hidden="true">/</li>
      <li class="min-w-0 truncate text-slate-200" aria-current="page">{{ current }}</li>
    </ol>
  </nav>
</template>
