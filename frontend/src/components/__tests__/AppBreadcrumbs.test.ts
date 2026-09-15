import { mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { describe, expect, it } from 'vitest';
import AppBreadcrumbs from '@/components/AppBreadcrumbs.vue';

async function mountBreadcrumbs(current: string) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/organizations/:id', name: 'organization', component: { template: '<div />' } },
    ],
  });

  await router.push('/organizations/17');
  await router.isReady();

  return mount(AppBreadcrumbs, {
    props: { current },
    global: { plugins: [router] },
  });
}

describe('AppBreadcrumbs', () => {
  it('links Организации to the dashboard', async () => {
    const wrapper = await mountBreadcrumbs('The Borщ');
    const link = wrapper.get('a');

    expect(link.text()).toBe('Организации');
    expect(link.attributes('href')).toBe('/');
  });

  it('renders the current page as text, not a link', async () => {
    const wrapper = await mountBreadcrumbs('The Borщ');
    const current = wrapper.get('[aria-current="page"]');

    expect(current.text()).toBe('The Borщ');
    expect(current.element.tagName).toBe('LI');
    expect(wrapper.findAll('a')).toHaveLength(1);
  });
});
