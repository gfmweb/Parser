import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/api/client';
import { normalizeApiError } from '@/api/errors';
import type {
  Organization,
  PaginatedMeta,
  PaginatedResponse,
  ParseJob,
  ParseProgressPayload,
  Review,
} from '@/types';

export const useOrganizationStore = defineStore('organization', () => {
  const organizations = ref<Organization[]>([]);
  const currentOrganization = ref<Organization | null>(null);
  const reviews = ref<Review[]>([]);
  const reviewsMeta = ref<PaginatedMeta | null>(null);
  const isLoading = ref(false);
  const isCreating = ref(false);
  const isLoadingCurrent = ref(false);
  const isLoadingReviews = ref(false);
  const error = ref<string | null>(null);

  function patchOrganization(id: number, patch: Partial<Organization>): void {
    organizations.value = organizations.value.map((organization) =>
      organization.id === id ? { ...organization, ...patch } : organization,
    );

    if (currentOrganization.value?.id === id) {
      currentOrganization.value = { ...currentOrganization.value, ...patch };
    }
  }

  async function fetchAll(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await api.get<PaginatedResponse<Organization>>('/organizations');
      organizations.value = response.data.data;
    } catch (caught) {
      error.value = normalizeApiError(caught, 'Не удалось загрузить организации.').message;
      throw caught;
    } finally {
      isLoading.value = false;
    }
  }

  async function create(url: string): Promise<Organization> {
    isCreating.value = true;
    error.value = null;

    try {
      const response = await api.post<Organization>('/organizations', { url });
      const created = response.data;
      organizations.value = [created, ...organizations.value];
      currentOrganization.value = created;
      return created;
    } catch (caught) {
      error.value = normalizeApiError(caught, 'Не удалось добавить организацию.').message;
      throw caught;
    } finally {
      isCreating.value = false;
    }
  }

  async function fetchOne(id: number): Promise<void> {
    isLoadingCurrent.value = true;
    error.value = null;

    try {
      const response = await api.get<Organization>(`/organizations/${id}`);
      currentOrganization.value = response.data;
      patchOrganization(id, response.data);
    } catch (caught) {
      error.value = normalizeApiError(caught, 'Не удалось загрузить организацию.').message;
      throw caught;
    } finally {
      isLoadingCurrent.value = false;
    }
  }

  async function fetchReviews(id: number, page = 1): Promise<void> {
    isLoadingReviews.value = true;
    error.value = null;

    try {
      const response = await api.get<PaginatedResponse<Review>>(`/organizations/${id}/reviews`, {
        params: { page },
      });
      reviews.value = response.data.data;
      reviewsMeta.value = response.data.meta;
    } catch (caught) {
      error.value = normalizeApiError(caught, 'Не удалось загрузить отзывы.').message;
      throw caught;
    } finally {
      isLoadingReviews.value = false;
    }
  }

  async function remove(id: number): Promise<void> {
    error.value = null;

    try {
      await api.delete(`/organizations/${id}`);
      organizations.value = organizations.value.filter((organization) => organization.id !== id);
      if (currentOrganization.value?.id === id) {
        currentOrganization.value = null;
      }
    } catch (caught) {
      error.value = normalizeApiError(caught, 'Не удалось удалить организацию.').message;
      throw caught;
    }
  }

  async function triggerParse(id: number): Promise<ParseJob> {
    error.value = null;

    try {
      const response = await api.post<ParseJob>(`/organizations/${id}/parse`);
      patchOrganization(id, {
        parse_status: 'parsing',
        latest_parse_job: response.data,
      });
      return response.data;
    } catch (caught) {
      error.value = normalizeApiError(caught, 'Не удалось запустить парсинг.').message;
      throw caught;
    }
  }

  function applyProgress(payload: ParseProgressPayload): void {
    const existing =
      currentOrganization.value?.id === payload.organizationId
        ? currentOrganization.value
        : (organizations.value.find((organization) => organization.id === payload.organizationId) ?? null);

    const latestJob =
      existing?.latest_parse_job !== undefined && existing.latest_parse_job !== null
        ? {
            ...existing.latest_parse_job,
            status: payload.status,
            parsed_reviews: payload.parsed,
            total_reviews: payload.total,
            error_message: payload.error,
          }
        : existing?.latest_parse_job;

    const patch: Partial<Organization> = {
      parse_status: payload.status,
      latest_parse_job: latestJob,
    };

    if (payload.status === 'done') {
      patch.review_count = payload.parsed;
      patch.parse_error = null;
    }

    if (payload.status === 'failed') {
      patch.parse_error = payload.error;
    }

    patchOrganization(payload.organizationId, patch);
  }

  return {
    organizations,
    currentOrganization,
    reviews,
    reviewsMeta,
    isLoading,
    isCreating,
    isLoadingCurrent,
    isLoadingReviews,
    error,
    fetchAll,
    create,
    fetchOne,
    fetchReviews,
    remove,
    triggerParse,
    applyProgress,
  };
});
