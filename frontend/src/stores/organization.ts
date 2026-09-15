import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/api/client';
import { normalizeApiError } from '@/api/errors';
import { userFacingError } from '@/utils/userFacingError';
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

  function localizeParseError(value: string | null | undefined): string | null | undefined {
    if (value === null || value === undefined || value === '') {
      return value;
    }

    return userFacingError(value);
  }

  function localizeOrganization(organization: Organization): Organization {
    return {
      ...organization,
      parse_error: localizeParseError(organization.parse_error) ?? null,
      latest_parse_job:
        organization.latest_parse_job === undefined || organization.latest_parse_job === null
          ? organization.latest_parse_job
          : {
              ...organization.latest_parse_job,
              error_message: localizeParseError(organization.latest_parse_job.error_message) ?? null,
            },
    };
  }

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
      const collected: Organization[] = [];
      let page = 1;
      const maxPages = 50;

      while (page <= maxPages) {
        const response = await api.get<PaginatedResponse<Organization>>('/organizations', {
          params: { page },
        });
        collected.push(...response.data.data.map(localizeOrganization));

        const lastPage = response.data.meta.last_page;

        if (page >= lastPage) {
          break;
        }

        page += 1;
      }

      organizations.value = collected;
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
      const created = localizeOrganization(response.data);
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
      const loaded = localizeOrganization(response.data);
      currentOrganization.value = loaded;
      patchOrganization(id, loaded);
    } catch (caught) {
      error.value = normalizeApiError(caught, 'Не удалось загрузить организацию.').message;
      throw caught;
    } finally {
      isLoadingCurrent.value = false;
    }
  }

  async function fetchReviews(id: number, page = 1, rating: number | null = null): Promise<void> {
    isLoadingReviews.value = true;
    error.value = null;

    try {
      const params: { page: number; rating?: number } = { page };

      if (rating !== null) {
        params.rating = rating;
      }

      const response = await api.get<PaginatedResponse<Review>>(`/organizations/${id}/reviews`, {
        params,
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
    await api.delete(`/organizations/${id}`);
    organizations.value = organizations.value.filter((organization) => organization.id !== id);

    if (currentOrganization.value?.id === id) {
      currentOrganization.value = null;
      reviews.value = [];
      reviewsMeta.value = null;
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
            error_message: localizeParseError(payload.error) ?? null,
          }
        : existing?.latest_parse_job;

    const patch: Partial<Organization> = {
      parse_status: payload.status,
      latest_parse_job: latestJob,
    };

    const name = payload.name?.trim();
    if (name !== undefined && name !== '') {
      patch.name = name;
    }

    if (typeof payload.rating === 'number') {
      patch.rating = payload.rating;
    }

    const address = payload.address?.trim();
    if (address !== undefined && address !== '') {
      patch.address = address;
    }

    if (payload.status === 'done') {
      patch.review_count = payload.parsed;
      patch.parse_error = null;
    }

    if (payload.status === 'failed') {
      patch.parse_error = localizeParseError(payload.error) ?? null;
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
