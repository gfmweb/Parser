import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ReviewCard from '@/components/ReviewCard.vue';
import type { Review } from '@/types';

function makeReview(overrides: Partial<Review> = {}): Review {
  return {
    id: 1,
    author_name: 'Иван Петров',
    rating: 4,
    text: 'Короткий отзыв',
    reviewed_at: '2024-01-15',
    ...overrides,
  };
}

describe('ReviewCard', () => {
  it('renders author_name', () => {
    const wrapper = mount(ReviewCard, {
      props: { review: makeReview() },
    });

    expect(wrapper.text()).toContain('Иван Петров');
  });

  it('renders the correct number of filled stars', () => {
    const wrapper = mount(ReviewCard, {
      props: { review: makeReview({ rating: 4 }) },
    });

    expect(wrapper.findAll('.text-amber-300')).toHaveLength(4);
    expect(wrapper.findAll('.text-slate-600')).toHaveLength(1);
  });

  it('truncates text longer than 300 chars and shows expand button', () => {
    const longText = `${'Отличный сервис. '.repeat(25)}Конец.`;
    expect(longText.length).toBeGreaterThan(300);

    const wrapper = mount(ReviewCard, {
      props: { review: makeReview({ text: longText }) },
    });

    expect(wrapper.text()).toContain('Показать полностью');
    expect(wrapper.text()).not.toContain('Конец.');
    expect(wrapper.text().includes(longText.slice(0, 300))).toBe(true);
  });

  it('shows full text after clicking expand', async () => {
    const longText = `${'Отличный сервис. '.repeat(25)}Конец.`;

    const wrapper = mount(ReviewCard, {
      props: { review: makeReview({ text: longText }) },
    });

    await wrapper.get('button').trigger('click');

    expect(wrapper.text()).toContain('Конец.');
    expect(wrapper.text()).toContain('Свернуть');
    expect(wrapper.text()).not.toContain('Показать полностью');
  });
});
