import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import api from '@/api/client';
import { AUTH_TOKEN_KEY } from '@/api/token';
import { useAuthStore } from '@/stores/auth';

vi.mock('@/api/client', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    delete: vi.fn(),
  },
}));

const postMock = api.post as unknown as Mock;
const getMock = api.get as unknown as Mock;

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    postMock.mockReset();
    getMock.mockReset();
  });

  it('sets token and user after a successful login', async () => {
    postMock.mockResolvedValueOnce({
      data: {
        user: { id: 1, name: 'Admin', email: 'admin@test.com' },
        token: 'test-token',
      },
    });

    const store = useAuthStore();
    await store.login('admin@test.com', 'password');

    expect(postMock).toHaveBeenCalledWith('/login', {
      email: 'admin@test.com',
      password: 'password',
    });
    expect(store.token).toBe('test-token');
    expect(store.user?.email).toBe('admin@test.com');
    expect(localStorage.getItem(AUTH_TOKEN_KEY)).toBe('test-token');
    expect(store.isAuthenticated).toBe(true);
  });

  it('stores an error message when credentials are invalid', async () => {
    postMock.mockRejectedValueOnce({
      message: 'Invalid credentials.',
      errors: {},
      status: 401,
    });

    const store = useAuthStore();

    await expect(store.login('admin@test.com', 'wrong')).rejects.toBeTruthy();
    expect(store.token).toBeNull();
    expect(store.error).toBe('Invalid credentials.');
    expect(localStorage.getItem(AUTH_TOKEN_KEY)).toBeNull();
  });

  it('clears state on logout', async () => {
    postMock.mockResolvedValueOnce({ data: { message: 'Logged out' } });

    const store = useAuthStore();
    store.token = 'test-token';
    store.user = { id: 1, name: 'Admin', email: 'admin@test.com' };
    localStorage.setItem(AUTH_TOKEN_KEY, 'test-token');

    await store.logout();

    expect(store.token).toBeNull();
    expect(store.user).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(localStorage.getItem(AUTH_TOKEN_KEY)).toBeNull();
  });

  it('hydrates token from localStorage and fetchUser loads the current user', async () => {
    localStorage.setItem(AUTH_TOKEN_KEY, 'stored-token');
    setActivePinia(createPinia());

    getMock.mockResolvedValueOnce({
      data: { id: 1, name: 'Admin', email: 'admin@test.com' },
    });

    const store = useAuthStore();
    expect(store.token).toBe('stored-token');

    await store.fetchUser();

    expect(getMock).toHaveBeenCalledWith('/user');
    expect(store.user?.email).toBe('admin@test.com');
    expect(store.isAuthenticated).toBe(true);
  });
});
