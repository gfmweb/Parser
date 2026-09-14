export function initialsFromName(name: string): string {
  const parts = name
    .trim()
    .split(/\s+/)
    .filter((part) => part !== '');

  const first = parts[0];
  const second = parts[1];

  if (first === undefined) {
    return '?';
  }

  const letters = second === undefined ? first.slice(0, 2) : `${first.charAt(0)}${second.charAt(0)}`;

  return letters.toUpperCase();
}

export function avatarHue(name: string): number {
  let hash = 0;

  for (const char of name) {
    hash = char.charCodeAt(0) + ((hash << 5) - hash);
  }

  return Math.abs(hash) % 360;
}
