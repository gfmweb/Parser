import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { ref } from 'vue';
import api from '@/api/client';
import DashboardView from '@/views/DashboardView.vue';
import type { Organization } from '@/types';

vi.mock('@/api/client', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/composables/useParseProgress', () => ({
  useParseProgress: () => ({
    progress: ref(null),
    isConnected: ref(false),
  }),
}));

const getMock = api.get as unknown as Mock;
const deleteMock = api.delete as unknown as Mock;

const organization: Organization = {
  id: 7,
  yandex_url: 'https://yandex.ru/maps/org/cafe/12345678/',
  yandex_id: '12345678',
  name: 'Кафе',
  address: null,
  rating: null,
  rating_count: 0,
  review_count: 0,
  parse_status: 'done',
  parse_error: null,
  last_parsed_at: null,
};

async function mountDashboard() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: DashboardView },
      { path: '/organizations/:id', name: 'organization', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  });
  await router.push('/');
  await router.isReady();

  return mount(DashboardView, {
    global: {
      plugins: [createPinia(), router],
    },
    attachTo: document.body,
  });
}

describe('DashboardView', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    getMock.mockReset();
    deleteMock.mockReset();
    getMock.mockResolvedValue({
      data: {
        data: [organization],
        meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
      },
    });
  });

  it('labels the organization url field', async () => {
    const wrapper = await mountDashboard();
    await flushPromises();

    expect(wrapper.get('label').text()).toContain('Ссылка на организацию в Яндекс Картах');
    expect(wrapper.get('form button[type="submit"]').text()).toContain('Добавить');
    wrapper.unmount();
  });

  it('deletes an organization after confirmation', async () => {
    deleteMock.mockResolvedValueOnce({ data: { message: 'Организация удалена.' } });
    const wrapper = await mountDashboard();
    await flushPromises();

    await wrapper.get('[aria-label="Действия"]').trigger('click');
    await wrapper.get('[aria-label="Удалить"]').trigger('click');
    await wrapper.vm.$nextTick();

    document.body.querySelector('.glass-btn-danger')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flushPromises();

    expect(deleteMock).toHaveBeenCalledWith('/organizations/7');
    expect(wrapper.text()).not.toContain('Кафе');
    wrapper.unmount();
  });

  it('shows a delete error inside the dialog instead of the list', async () => {
    deleteMock.mockRejectedValueOnce({
      message: 'Не удалось удалить организацию.',
      errors: {},
      status: 500,
    });
    const wrapper = await mountDashboard();
    await flushPromises();

    await wrapper.get('[aria-label="Действия"]').trigger('click');
    await wrapper.get('[aria-label="Удалить"]').trigger('click');
    document.body.querySelector('.glass-btn-danger')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flushPromises();

    expect(document.body.querySelector('[role="alert"]')?.textContent).toBe(
      'Не удалось удалить организацию.',
    );
    expect(wrapper.text()).toContain('Кафе');
    wrapper.unmount();
  });
});
