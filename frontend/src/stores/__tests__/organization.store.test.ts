import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import api from '@/api/client';
import { useOrganizationStore } from '@/stores/organization';
import type { Organization } from '@/types/models';

vi.mock('@/api/client', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    delete: vi.fn(),
  },
}));

const postMock = api.post as unknown as Mock;
const getMock = api.get as unknown as Mock;
const deleteMock = api.delete as unknown as Mock;

const organization: Organization = {
  id: 7,
  yandex_url: 'https://yandex.ru/maps/org/cafe/12345678/',
  yandex_id: null,
  name: null,
  address: null,
  rating: null,
  rating_count: 0,
  review_count: 0,
  parse_status: 'pending',
  parse_error: null,
  last_parsed_at: null,
};

describe('useOrganizationStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    postMock.mockReset();
    getMock.mockReset();
    deleteMock.mockReset();
  });

  it('dispatches POST /organizations with the given url', async () => {
    postMock.mockResolvedValueOnce({ data: organization });

    const store = useOrganizationStore();
    const created = await store.create(organization.yandex_url);

    expect(postMock).toHaveBeenCalledWith('/organizations', {
      url: organization.yandex_url,
    });
    expect(created.id).toBe(7);
    expect(store.organizations).toHaveLength(1);
    expect(store.currentOrganization?.id).toBe(7);
  });

  it('fetchAll populates the organizations list', async () => {
    getMock.mockResolvedValueOnce({
      data: {
        data: [organization],
        meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
      },
    });

    const store = useOrganizationStore();
    await store.fetchAll();

    expect(getMock).toHaveBeenCalledWith('/organizations', { params: { page: 1 } });
    expect(store.organizations).toHaveLength(1);
    expect(store.organizations[0]?.id).toBe(7);
  });

  it('triggerParse posts to the parse endpoint and sets parsing status', async () => {
    getMock.mockResolvedValueOnce({
      data: {
        data: [organization],
        meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
      },
    });
    postMock.mockResolvedValueOnce({
      data: {
        status: 'queued',
        total_reviews: 0,
        parsed_reviews: 0,
        error_message: null,
        started_at: null,
        finished_at: null,
      },
    });

    const store = useOrganizationStore();
    await store.fetchAll();
    await store.triggerParse(7);

    expect(postMock).toHaveBeenCalledWith('/organizations/7/parse');
    expect(store.organizations[0]?.parse_status).toBe('parsing');
  });

  it('applyProgress marks a listed organization as done', async () => {
    getMock.mockResolvedValueOnce({
      data: {
        data: [{ ...organization, parse_status: 'parsing' }],
        meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
      },
    });

    const store = useOrganizationStore();
    await store.fetchAll();
    store.applyProgress({
      organizationId: 7,
      parseJobId: 3,
      total: 600,
      parsed: 600,
      status: 'done',
      error: null,
    });

    expect(store.organizations[0]?.parse_status).toBe('done');
    expect(store.organizations[0]?.review_count).toBe(600);
    expect(store.organizations[0]?.parse_error).toBeNull();
  });

  it('applyProgress localizes a stored English parse_error', async () => {
    getMock.mockResolvedValueOnce({
      data: {
        data: [{ ...organization, parse_status: 'parsing' }],
        meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
      },
    });

    const store = useOrganizationStore();
    await store.fetchAll();
    store.applyProgress({
      organizationId: 7,
      parseJobId: 3,
      total: 0,
      parsed: 0,
      status: 'failed',
      error: 'Structure changed: missing field data',
    });

    expect(store.organizations[0]?.parse_status).toBe('failed');
    expect(store.organizations[0]?.parse_error).toBe(
      'Не удалось разобрать страницу Яндекса. Попробуйте позже.',
    );
  });

  it('applyProgress patches name from an in-progress payload', async () => {
    getMock.mockResolvedValueOnce({
      data: {
        data: [{ ...organization, parse_status: 'parsing' }],
        meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
      },
    });

    const store = useOrganizationStore();
    await store.fetchAll();
    store.applyProgress({
      organizationId: 7,
      parseJobId: 3,
      total: 600,
      parsed: 50,
      status: 'parsing',
      error: null,
      name: 'Своя компания',
      rating: 4.7,
      address: 'Уфа',
    });

    expect(store.organizations[0]?.parse_status).toBe('parsing');
    expect(store.organizations[0]?.name).toBe('Своя компания');
    expect(store.organizations[0]?.rating).toBe(4.7);
    expect(store.organizations[0]?.address).toBe('Уфа');
  });

  it('fetchReviews sends rating when a filter is selected', async () => {
    getMock.mockResolvedValueOnce({
      data: {
        data: [],
        meta: {
          current_page: 1,
          last_page: 1,
          total: 0,
          per_page: 50,
          rating_counts: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 10 },
        },
      },
    });

    const store = useOrganizationStore();
    await store.fetchReviews(7, 1, 5);

    expect(getMock).toHaveBeenCalledWith('/organizations/7/reviews', {
      params: { page: 1, rating: 5 },
    });
  });

  it('fetchReviews omits rating when the filter is cleared', async () => {
    getMock.mockResolvedValueOnce({
      data: {
        data: [],
        meta: {
          current_page: 1,
          last_page: 1,
          total: 0,
          per_page: 50,
          rating_counts: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 10 },
        },
      },
    });

    const store = useOrganizationStore();
    await store.fetchReviews(7, 2, null);

    expect(getMock).toHaveBeenCalledWith('/organizations/7/reviews', {
      params: { page: 2 },
    });
  });

  it('remove deletes the organization from the list', async () => {
    getMock.mockResolvedValueOnce({
      data: {
        data: [organization],
        meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
      },
    });
    deleteMock.mockResolvedValueOnce({ data: { message: 'Организация удалена.' } });

    const store = useOrganizationStore();
    await store.fetchAll();
    await store.remove(7);

    expect(deleteMock).toHaveBeenCalledWith('/organizations/7');
    expect(store.organizations).toHaveLength(0);
  });

  it('fetchAll concatenates every page of organizations', async () => {
    getMock
      .mockResolvedValueOnce({
        data: {
          data: [organization],
          meta: { current_page: 1, last_page: 2, total: 2, per_page: 20 },
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: [{ ...organization, id: 8, yandex_url: 'https://yandex.ru/maps/org/cafe/87654321/' }],
          meta: { current_page: 2, last_page: 2, total: 2, per_page: 20 },
        },
      });

    const store = useOrganizationStore();
    await store.fetchAll();

    expect(getMock).toHaveBeenNthCalledWith(1, '/organizations', { params: { page: 1 } });
    expect(getMock).toHaveBeenNthCalledWith(2, '/organizations', { params: { page: 2 } });
    expect(store.organizations.map((item) => item.id)).toEqual([7, 8]);
  });

  it('fetchAll caps at 50 pages and sets a truncation error', async () => {
    getMock.mockImplementation(async (_url: string, config: { params: { page: number } }) => ({
      data: {
        data: [{ ...organization, id: config.params.page }],
        meta: { current_page: config.params.page, last_page: 51, total: 1020, per_page: 20 },
      },
    }));

    const store = useOrganizationStore();
    await store.fetchAll();

    expect(getMock).toHaveBeenCalledTimes(50);
    expect(store.organizations).toHaveLength(50);
    expect(store.error).toBe('Показаны первые организации. Обновите список позже.');
  });

  it('remove clears reviews of the deleted current organization', async () => {
    deleteMock.mockResolvedValueOnce({ data: { message: 'Организация удалена.' } });

    const store = useOrganizationStore();
    store.currentOrganization = organization;
    store.reviews = [
      { id: 1, author_name: 'A', rating: 5, text: 'ok', reviewed_at: null },
    ];
    store.reviewsMeta = { current_page: 1, last_page: 1, total: 1, per_page: 50 };
    store.organizations = [organization];

    await store.remove(7);

    expect(store.currentOrganization).toBeNull();
    expect(store.reviews).toEqual([]);
    expect(store.reviewsMeta).toBeNull();
  });

  it('remove does not write a global error when the request fails', async () => {
    deleteMock.mockRejectedValueOnce({
      message: 'Не удалось удалить организацию.',
      errors: {},
      status: 500,
    });

    const store = useOrganizationStore();
    store.organizations = [organization];

    await expect(store.remove(7)).rejects.toMatchObject({ status: 500 });
    expect(store.error).toBeNull();
    expect(store.organizations).toHaveLength(1);
  });
});
