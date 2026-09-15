import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import OrganizationCard from '@/components/OrganizationCard.vue';
import type { Organization } from '@/types';

const organization: Organization = {
  id: 7,
  yandex_url: 'https://yandex.ru/maps/org/cafe/12345678/',
  yandex_id: null,
  name: null,
  address: null,
  rating: null,
  rating_count: 0,
  review_count: 0,
  parse_status: 'parsing',
  parse_error: null,
  last_parsed_at: null,
};

describe('OrganizationCard', () => {
  it('shows Новая компания instead of the yandex url when name is missing', () => {
    const wrapper = mount(OrganizationCard, { props: { organization } });

    expect(wrapper.get('h3').text()).toBe('Новая компания');
    expect(wrapper.text()).not.toContain(organization.yandex_url);
  });

  it('renders compact action buttons on one row when reparse is available', () => {
    const wrapper = mount(OrganizationCard, {
      props: { organization: { ...organization, name: 'Своя компания', parse_status: 'done' } },
    });
    const buttons = wrapper.findAll('.flex.gap-2 button');

    expect(buttons).toHaveLength(2);
    expect(buttons[0]?.text()).toBe('Смотреть отзывы');
    expect(buttons[1]?.text()).toBe('Перепарсить');
    expect(buttons[0]?.classes()).toContain('glass-btn-compact');
    expect(buttons[1]?.classes()).toContain('glass-btn-compact');
    expect(wrapper.find('.flex.gap-2').exists()).toBe(true);
    expect(wrapper.find('.flex-wrap').exists()).toBe(false);
  });

  it('emits delete from the kebab menu', async () => {
    const wrapper = mount(OrganizationCard, {
      props: { organization: { ...organization, name: 'Своя компания', parse_status: 'done' } },
    });

    await wrapper.get('[aria-label="Действия"]').trigger('click');
    await wrapper.get('[aria-label="Удалить"]').trigger('click');

    expect(wrapper.emitted('delete')).toHaveLength(1);
  });

  it('renders a solid delete item with the label before the icon', async () => {
    const wrapper = mount(OrganizationCard, {
      props: { organization: { ...organization, name: 'Своя компания', parse_status: 'done' } },
    });

    await wrapper.get('[aria-label="Действия"]').trigger('click');
    const item = wrapper.get('[aria-label="Удалить"]');
    const html = item.html();

    expect(item.classes()).not.toContain('glass-panel');
    expect(wrapper.get('#organization-actions-7').classes()).toContain('bg-slate-900');
    expect(html.indexOf('Удалить')).toBeLessThan(html.indexOf('<svg'));
    expect(item.get('span').classes()).toContain('text-left');
  });

  it('shows a stored Russian parse_error as-is', () => {
    const wrapper = mount(OrganizationCard, {
      props: {
        organization: {
          ...organization,
          parse_status: 'failed',
          parse_error: 'Слишком много запросов к Яндексу. Попробуйте позже.',
        },
      },
    });

    expect(wrapper.text()).toContain('Слишком много запросов к Яндексу. Попробуйте позже.');
  });
});
