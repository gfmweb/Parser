import { describe, expect, it } from 'vitest';
import { normalizeApiError } from '@/api/errors';

describe('normalizeApiError', () => {
  it('uses a Russian default fallback', () => {
    expect(normalizeApiError(null).message).toBe('Не удалось выполнить запрос.');
  });

  it('maps an already normalized English message', () => {
    expect(
      normalizeApiError({
        message: 'Invalid credentials.',
        errors: {},
        status: 401,
      }).message,
    ).toBe('Неверный email или пароль.');
  });

  it('prefers the first validation error over Laravel wrapper', () => {
    expect(
      normalizeApiError({
        message: 'The given data was invalid.',
        errors: { url: ['Эта организация уже добавлена.'] },
        status: 422,
      }).message,
    ).toBe('Эта организация уже добавлена.');
  });

  it('uses the fallback for Axios network errors without a response', () => {
    expect(
      normalizeApiError(
        { message: 'Network Error', name: 'AxiosError' },
        'Не удалось загрузить организации.',
      ).message,
    ).toBe('Не удалось загрузить организации.');
  });
});
