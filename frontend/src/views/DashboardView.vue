<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppNavbar from '@/components/AppNavbar.vue';
import OrganizationCard from '@/components/OrganizationCard.vue';
import OrganizationSkeleton from '@/components/OrganizationSkeleton.vue';
import { useOrganizationStore } from '@/stores/organization';
import { isYandexMapsUrl } from '@/utils/yandexUrl';

const router = useRouter();
const organizations = useOrganizationStore();

const url = ref('');
const urlError = ref<string | null>(null);
const skeletons = [1, 2, 3, 4, 5, 6];

const isEmpty = computed(
  () => !organizations.isLoading && organizations.organizations.length === 0,
);

onMounted(() => {
  void organizations.fetchAll().catch(() => undefined);
});

async function onCreate(): Promise<void> {
  const value = url.value.trim();
  urlError.value = null;

  if (!isYandexMapsUrl(value)) {
    urlError.value = 'Укажите ссылку вида yandex.ru/maps или yandex.com/maps';
    return;
  }

  try {
    const created = await organizations.create(value);
    await router.push({ name: 'organization', params: { id: String(created.id) } });
  } catch {
    urlError.value = organizations.error;
  }
}

function openOrganization(id: number): void {
  void router.push({ name: 'organization', params: { id: String(id) } });
}

async function reparse(id: number): Promise<void> {
  try {
    await organizations.triggerParse(id);
  } catch {
    // ошибка уже в store.error
  }
}
</script>

<template>
  <div class="mx-auto flex min-h-screen max-w-6xl flex-col gap-6 px-4 py-8">
    <AppNavbar />

    <section class="glass-panel p-6">
      <h2 class="text-lg font-semibold text-white">Добавить организацию</h2>
      <p class="mt-1 text-sm text-slate-400">Вставьте ссылку на карточку в Яндекс Картах</p>
      <form class="mt-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="onCreate">
        <input
          v-model="url"
          class="glass-input"
          type="text"
          name="url"
          placeholder="https://yandex.ru/maps/org/..."
          autocomplete="off"
        />
        <button
          class="glass-btn flex items-center justify-center gap-2 whitespace-nowrap"
          type="submit"
          :disabled="organizations.isCreating"
        >
          <span v-if="organizations.isCreating" class="spinner" aria-hidden="true" />
          <span>{{ organizations.isCreating ? 'Добавляем…' : 'Парсить' }}</span>
        </button>
      </form>
      <p v-if="urlError" class="mt-3 text-sm text-red-300" role="alert">{{ urlError }}</p>
    </section>

    <section>
      <h2 class="mb-4 text-lg font-semibold text-white">Организации</h2>

      <div v-if="organizations.isLoading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <OrganizationSkeleton v-for="item in skeletons" :key="item" />
      </div>

      <div
        v-else-if="isEmpty"
        class="glass-panel flex flex-col items-center gap-4 px-6 py-16 text-center"
      >
        <svg class="h-24 w-24 text-indigo-300/70" viewBox="0 0 120 120" fill="none" aria-hidden="true">
          <rect x="18" y="28" width="84" height="64" rx="12" stroke="currentColor" stroke-width="4" />
          <path d="M18 48h84" stroke="currentColor" stroke-width="4" />
          <circle cx="40" cy="72" r="8" fill="currentColor" opacity="0.7" />
          <path d="M56 78h36" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
        </svg>
        <p class="max-w-md text-slate-300">
          Пока нет организаций. Добавьте ссылку на Яндекс Карты выше, чтобы начать парсинг отзывов.
        </p>
      </div>

      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <OrganizationCard
          v-for="organization in organizations.organizations"
          :key="organization.id"
          :organization="organization"
          @view="openOrganization(organization.id)"
          @reparse="reparse(organization.id)"
        />
      </div>

      <p v-if="organizations.error && !urlError" class="mt-4 text-sm text-red-300">
        {{ organizations.error }}
      </p>
    </section>
  </div>
</template>
