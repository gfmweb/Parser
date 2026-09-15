import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import OrganizationSummary from '@/components/OrganizationSummary.vue';
import type { Organization } from '@/types';

const organization: Organization = {
  id: 7,
  yandex_url: 'https://yandex.ru/maps/org/cafe/12345678/',
  yandex_id: '12345678',
  name: 'The Borщ',
  address: 'Уфа',
  rating: 4.4,
  rating_count: 1008,
  review_count: 478,
  parse_status: 'done',
  parse_error: null,
  last_parsed_at: null,
};

describe('OrganizationSummary', () => {
  it('renders only an outlined back button', () => {
    const wrapper = mount(OrganizationSummary, { props: { organization } });
    const buttons = wrapper.findAll('button');

    expect(buttons).toHaveLength(1);
    expect(buttons[0]?.text()).toBe('Назад');
    expect(buttons[0]?.classes()).toContain('glass-btn-outline');
    expect(buttons[0]?.classes()).toContain('w-full');
    expect(buttons[0]?.classes()).toContain('text-center');
    expect(wrapper.text()).not.toContain('Парсинг');
  });

  it('emits back on click', async () => {
    const wrapper = mount(OrganizationSummary, { props: { organization } });

    await wrapper.get('button').trigger('click');

    expect(wrapper.emitted('back')).toHaveLength(1);
  });

  it('shows a stored Russian parse_error as-is', () => {
    const wrapper = mount(OrganizationSummary, {
      props: {
        organization: {
          ...organization,
          parse_status: 'failed',
          parse_error: 'Не удалось разобрать страницу Яндекса. Попробуйте позже.',
        },
      },
    });

    expect(wrapper.text()).toContain('Не удалось разобрать страницу Яндекса. Попробуйте позже.');
  });
});
