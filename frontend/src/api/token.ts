export const AUTH_TOKEN_KEY = 'auth_token';

export function getStoredToken(): string | null {
  return localStorage.getItem(AUTH_TOKEN_KEY);
}

export function setStoredToken(token: string | null): void {
  if (token === null || token === '') {
    localStorage.removeItem(AUTH_TOKEN_KEY);
    return;
  }

  localStorage.setItem(AUTH_TOKEN_KEY, token);
}
