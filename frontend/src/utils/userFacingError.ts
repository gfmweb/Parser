const EXACT_MESSAGES: Record<string, string> = {
  'Invalid credentials.': 'Неверный email или пароль.',
  'Unauthenticated.': 'Необходимо войти в систему.',
  'This action is unauthorized.': 'Недостаточно прав для этого действия.',
  'Logged out': 'Вы вышли из системы.',
  'Organization deleted': 'Организация удалена.',
  'Rate limited': 'Слишком много запросов к Яндексу. Попробуйте позже.',
  'Organization not found.': 'Организация не найдена в Яндекс Картах.',
  'Organization is already parsing.': 'Организация уже парсится.',
  'Job failed': 'Парсинг не удался.',
  'Failed to reach Yandex Maps': 'Не удалось получить данные с Яндекс Карт.',
  'Yandex API request failed': 'Не удалось получить данные с Яндекс Карт.',
  'Parse job not found.': 'Задача парсинга не найдена.',
  'Request failed': 'Не удалось выполнить запрос.',
  'The given data was invalid.': 'Проверьте введённые данные.',
  'The given data was invalid': 'Проверьте введённые данные.',
};

export function userFacingError(message: string): string {
  const trimmed = message.trim();

  if (trimmed === '') {
    return 'Не удалось выполнить запрос.';
  }

  const mapped = EXACT_MESSAGES[trimmed];
  if (mapped !== undefined) {
    return mapped;
  }

  if (trimmed.startsWith('Structure changed')) {
    return 'Не удалось разобрать страницу Яндекса. Попробуйте позже.';
  }

  if (trimmed === 'Network Error' || trimmed.startsWith('timeout of ') || trimmed.startsWith('timeout')) {
    return 'Не удалось выполнить запрос.';
  }

  return trimmed;
}
