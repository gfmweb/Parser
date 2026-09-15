import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import ConfirmDialog from '@/components/ConfirmDialog.vue';

function dialogButton(selector: string): HTMLButtonElement {
  const button = document.body.querySelector(selector);

  if (!(button instanceof HTMLButtonElement)) {
    throw new Error(`Button not found: ${selector}`);
  }

  return button;
}

describe('ConfirmDialog', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('emits cancel from the secondary button', async () => {
    const wrapper = mount(ConfirmDialog, {
      props: {
        open: true,
        title: 'Удалить организацию?',
        message: '«Кафе» и все отзывы будут удалены. Это нельзя отменить.',
      },
      attachTo: document.body,
    });

    expect(document.body.querySelector('#confirm-dialog-title')?.textContent).toBe('Удалить организацию?');
    expect(document.body.textContent).toContain('и все отзывы будут удалены');

    dialogButton('.glass-btn-secondary').click();
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('cancel')).toHaveLength(1);
    wrapper.unmount();
  });

  it('emits confirm from the danger button', async () => {
    const wrapper = mount(ConfirmDialog, {
      props: {
        open: true,
        title: 'Удалить организацию?',
        message: '«Кафе» и все отзывы будут удалены. Это нельзя отменить.',
      },
      attachTo: document.body,
    });

    dialogButton('.glass-btn-danger').click();
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('confirm')).toHaveLength(1);
    wrapper.unmount();
  });

  it('does not emit confirm while loading', async () => {
    const wrapper = mount(ConfirmDialog, {
      props: {
        open: true,
        title: 'Удалить организацию?',
        message: '«Кафе» и все отзывы будут удалены. Это нельзя отменить.',
        isLoading: true,
      },
      attachTo: document.body,
    });

    dialogButton('.glass-btn-danger').click();
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('confirm')).toBeUndefined();
    wrapper.unmount();
  });

  it('shows an error inside the dialog', () => {
    const wrapper = mount(ConfirmDialog, {
      props: {
        open: true,
        title: 'Удалить организацию?',
        message: '«Кафе» и все отзывы будут удалены. Это нельзя отменить.',
        error: 'Не удалось удалить организацию.',
      },
      attachTo: document.body,
    });

    expect(document.body.querySelector('[role="alert"]')?.textContent).toBe(
      'Не удалось удалить организацию.',
    );
    wrapper.unmount();
  });

  it('does not render the dialog when closed', () => {
    const wrapper = mount(ConfirmDialog, {
      props: {
        open: false,
        title: 'Удалить организацию?',
        message: 'hidden',
      },
      attachTo: document.body,
    });

    expect(document.body.querySelector('[role="dialog"]')).toBeNull();
    wrapper.unmount();
  });

  it('focuses the cancel button when opened and restores focus on close', async () => {
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.textContent = 'open';
    document.body.appendChild(trigger);
    trigger.focus();

    const wrapper = mount(ConfirmDialog, {
      props: {
        open: true,
        title: 'Удалить организацию?',
        message: '«Кафе» и все отзывы будут удалены. Это нельзя отменить.',
      },
      attachTo: document.body,
    });

    await wrapper.vm.$nextTick();
    expect(document.activeElement).toBe(dialogButton('.glass-btn-secondary'));

    await wrapper.setProps({ open: false });
    await wrapper.vm.$nextTick();
    expect(document.activeElement).toBe(trigger);

    wrapper.unmount();
    trigger.remove();
  });

  it('emits cancel on Escape and backdrop click', async () => {
    const wrapper = mount(ConfirmDialog, {
      props: {
        open: true,
        title: 'Удалить организацию?',
        message: '«Кафе» и все отзывы будут удалены. Это нельзя отменить.',
      },
      attachTo: document.body,
    });

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    await wrapper.vm.$nextTick();
    expect(wrapper.emitted('cancel')).toHaveLength(1);

    const overlay = document.body.querySelector('[role="presentation"]');
    overlay?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await wrapper.vm.$nextTick();
    expect(wrapper.emitted('cancel')?.length).toBeGreaterThanOrEqual(1);

    wrapper.unmount();
  });

  it('keeps Tab inside the dialog while loading', async () => {
    const outside = document.createElement('button');
    outside.type = 'button';
    outside.textContent = 'outside';
    document.body.appendChild(outside);

    const wrapper = mount(ConfirmDialog, {
      props: {
        open: true,
        title: 'Удалить организацию?',
        message: '«Кафе» и все отзывы будут удалены. Это нельзя отменить.',
        isLoading: true,
      },
      attachTo: document.body,
    });

    await wrapper.vm.$nextTick();
    const dialog = document.body.querySelector('[role="dialog"]');
    expect(dialog).not.toBeNull();

    const tab = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
    dialog?.dispatchEvent(tab);
    await wrapper.vm.$nextTick();

    expect(tab.defaultPrevented).toBe(true);
    expect(document.activeElement).not.toBe(outside);

    wrapper.unmount();
    outside.remove();
  });
});
