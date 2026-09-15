import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ReviewRatingFilter from '@/components/ReviewRatingFilter.vue';

const counts = { 1: 40, 2: 0, 3: 1, 4: 100, 5: 200 };

describe('ReviewRatingFilter', () => {
  it('renders star rows with counts instead of text labels', () => {
    const wrapper = mount(ReviewRatingFilter, {
      props: { counts, selected: null },
    });

    expect(wrapper.text()).not.toContain('5 звёзд');
    expect(wrapper.text()).not.toContain('1 звезда');
    expect(wrapper.text()).toContain('200');
    expect(wrapper.text()).toContain('40');
    expect(wrapper.find('[aria-label="5 звёзд"]').exists()).toBe(true);
    expect(wrapper.find('[aria-label="1 звезда"]').exists()).toBe(true);
  });

  it('emits the clicked rating and clears on second click', async () => {
    const wrapper = mount(ReviewRatingFilter, {
      props: { counts, selected: null },
    });

    const fiveStar = wrapper.get('[aria-label="5 звёзд"]');
    await fiveStar.trigger('click');

    expect(wrapper.emitted('select')).toEqual([[5]]);

    await wrapper.setProps({ selected: 5 });
    await fiveStar.trigger('click');

    expect(wrapper.emitted('select')?.[1]).toEqual([null]);
  });
});
