import { describe, expect, it } from 'vitest';
import { userFacingError } from '@/utils/userFacingError';

describe('userFacingError', () => {
  it('maps known English API and parser messages', () => {
    expect(userFacingError('Invalid credentials.')).toBe('Неверный email или пароль.');
    expect(userFacingError('Unauthenticated.')).toBe('Необходимо войти в систему.');
    expect(userFacingError('Rate limited')).toBe('Слишком много запросов к Яндексу. Попробуйте позже.');
    expect(userFacingError('Organization not found.')).toBe('Организация не найдена в Яндекс Картах.');
    expect(userFacingError('Job failed')).toBe('Парсинг не удался.');
    expect(userFacingError('Request failed')).toBe('Не удалось выполнить запрос.');
    expect(userFacingError('The given data was invalid.')).toBe('Проверьте введённые данные.');
  });

  it('maps Structure changed messages regardless of the missing field', () => {
    expect(userFacingError('Structure changed: missing field data')).toBe(
      'Не удалось разобрать страницу Яндекса. Попробуйте позже.',
    );
    expect(userFacingError('Structure changed: missing field reviews[].id')).toBe(
      'Не удалось разобрать страницу Яндекса. Попробуйте позже.',
    );
  });

  it('keeps Russian messages as-is', () => {
    expect(userFacingError('Организация уже парсится.')).toBe('Организация уже парсится.');
  });

  it('maps network and timeout messages', () => {
    expect(userFacingError('Network Error')).toBe('Не удалось выполнить запрос.');
    expect(userFacingError('timeout of 10000ms exceeded')).toBe('Не удалось выполнить запрос.');
  });

  it('uses a Russian fallback for blank input', () => {
    expect(userFacingError('   ')).toBe('Не удалось выполнить запрос.');
  });
});
