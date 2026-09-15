import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StatusBadge from '@/components/StatusBadge.vue';

describe('StatusBadge', () => {
  it('renders parsing and done labels in Russian', () => {
    const parsing = mount(StatusBadge, { props: { status: 'parsing' } });
    const done = mount(StatusBadge, { props: { status: 'done' } });

    expect(parsing.text()).toBe('Парсинг');
    expect(done.text()).toBe('Готово');
  });

  it('renders pending in Russian', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'pending' } });

    expect(wrapper.text()).toBe('В очереди');
  });
});
