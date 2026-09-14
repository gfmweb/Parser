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

    expect(getMock).toHaveBeenCalledWith('/organizations');
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
});
