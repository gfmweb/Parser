const MONTHS = [
  'января',
  'февраля',
  'марта',
  'апреля',
  'мая',
  'июня',
  'июля',
  'августа',
  'сентября',
  'октября',
  'ноября',
  'декабря',
] as const;

export function formatReviewDate(value: string | null): string {
  if (value === null || value === '') {
    return '';
  }

  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

  if (match === null) {
    return '';
  }

  const year = Number(match[1]);
  const monthIndex = Number(match[2]) - 1;
  const day = Number(match[3]);
  const month = MONTHS[monthIndex];

  if (!Number.isInteger(year) || !Number.isInteger(day) || month === undefined) {
    return '';
  }

  return `${day} ${month} ${year}`;
}
