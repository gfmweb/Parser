import type { NormalizedApiError } from '@/types/models';

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
      result[key] = item;
    }
  }

  return result;
}

export function isNormalizedApiError(error: unknown): error is NormalizedApiError {
  if (!isRecord(error)) {
    return false;
  }

  return typeof error.message === 'string' && isRecord(error.errors);
}

export function normalizeApiError(error: unknown, fallbackMessage = 'Request failed'): NormalizedApiError {
  if (isNormalizedApiError(error)) {
    return error;
  }

  if (!isRecord(error)) {
    return { message: fallbackMessage, errors: {}, status: null };
  }

  const response = isRecord(error.response) ? error.response : null;
  const data = response !== null && isRecord(response.data) ? response.data : isRecord(error) ? error : null;
  const statusValue = response !== null && typeof response.status === 'number' ? response.status : null;
  const message =
    data !== null && typeof data.message === 'string' && data.message !== '' ? data.message : fallbackMessage;

  return {
    message,
    errors: data !== null ? asStringArrayMap(data.errors) : {},
    status: statusValue,
  };
}
