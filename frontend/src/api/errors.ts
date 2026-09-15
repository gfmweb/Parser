import type { NormalizedApiError } from '@/types/models';
import { userFacingError } from '@/utils/userFacingError';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

function asStringArrayMap(value: unknown): Record<string, string[]> {
  if (!isRecord(value)) {
    return {};
  }

  const result: Record<string, string[]> = {};

  for (const [key, item] of Object.entries(value)) {
    if (Array.isArray(item) && item.every((entry) => typeof entry === 'string')) {
      result[key] = item.map((entry) => userFacingError(entry));
    }
  }

  return result;
}

function firstFieldError(errors: Record<string, string[]>): string | null {
  for (const messages of Object.values(errors)) {
    const first = messages[0];

    if (typeof first === 'string' && first !== '') {
      return first;
    }
  }

  return null;
}

export function isNormalizedApiError(error: unknown): error is NormalizedApiError {
  if (!isRecord(error)) {
    return false;
  }

  return typeof error.message === 'string' && isRecord(error.errors);
}

export function normalizeApiError(
  error: unknown,
  fallbackMessage = 'Не удалось выполнить запрос.',
): NormalizedApiError {
  const fallback = userFacingError(fallbackMessage);

  if (isNormalizedApiError(error)) {
    const errors = asStringArrayMap(error.errors);
    const fieldError = firstFieldError(errors);

    return {
      ...error,
      message: fieldError ?? userFacingError(error.message),
      errors,
    };
  }

  if (!isRecord(error)) {
    return { message: fallback, errors: {}, status: null };
  }

  const response = isRecord(error.response) ? error.response : null;
  const statusValue = response !== null && typeof response.status === 'number' ? response.status : null;

  if (response === null) {
    return { message: fallback, errors: {}, status: null };
  }

  const data = isRecord(response.data) ? response.data : null;
  const errors = data !== null ? asStringArrayMap(data.errors) : {};
  const fieldError = firstFieldError(errors);
  const message =
    data !== null && typeof data.message === 'string' && data.message !== '' ? data.message : fallback;

  return {
    message: fieldError ?? userFacingError(message),
    errors,
    status: statusValue,
  };
}
