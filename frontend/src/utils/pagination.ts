export type PaginationItem = number | 'ellipsis';

export function paginationItems(currentPage: number, lastPage: number): PaginationItem[] {
  if (lastPage <= 0) {
    return [];
  }

  if (lastPage <= 7) {
    return Array.from({ length: lastPage }, (_, index) => index + 1);
  }

  const items: PaginationItem[] = [1];
  const start = Math.max(2, currentPage - 1);
  const end = Math.min(lastPage - 1, currentPage + 1);

  if (start > 2) {
    items.push('ellipsis');
  }

  for (let page = start; page <= end; page += 1) {
    items.push(page);
  }

  if (end < lastPage - 1) {
    items.push('ellipsis');
  }

  items.push(lastPage);

  return items;
}
