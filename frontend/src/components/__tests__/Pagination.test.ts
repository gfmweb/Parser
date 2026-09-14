import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Pagination from '@/components/Pagination.vue';

describe('Pagination', () => {
  it('renders correct page buttons for 5 pages', () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 2,
        lastPage: 5,
      },
    });

    const labels = wrapper.findAll('button').map((button) => button.text());

    expect(labels).toEqual(['Назад', '1', '2', '3', '4', '5', 'Вперёд']);
  });

  it('shows ellipsis for more than 7 pages', () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 5,
        lastPage: 12,
      },
    });

    const labels = wrapper.findAll('button').map((button) => button.text());

    expect(wrapper.text()).toContain('…');
    expect(labels).toContain('1');
    expect(labels).toContain('4');
    expect(labels).toContain('5');
    expect(labels).toContain('6');
    expect(labels).toContain('12');
    expect(labels).not.toContain('2');
    expect(labels).not.toContain('11');
  });

  it('emits page-change with the clicked page number', async () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 1,
        lastPage: 5,
      },
    });

    const pageButtons = wrapper.findAll('button').filter((button) => button.text() === '3');
    expect(pageButtons).toHaveLength(1);
    await pageButtons[0]?.trigger('click');

    expect(wrapper.emitted('page-change')).toEqual([[3]]);
  });

  it('disables Previous on page 1', () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 1,
        lastPage: 4,
      },
    });

    const previous = wrapper.findAll('button')[0];
    expect(previous?.text()).toBe('Назад');
    expect(previous?.attributes('disabled')).toBeDefined();
  });

  it('disables Next on the last page', () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 4,
        lastPage: 4,
      },
    });

    const buttons = wrapper.findAll('button');
    const next = buttons[buttons.length - 1];
    expect(next?.text()).toBe('Вперёд');
    expect(next?.attributes('disabled')).toBeDefined();
  });
});
