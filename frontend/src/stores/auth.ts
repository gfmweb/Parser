import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '@/api/client';
import { normalizeApiError } from '@/api/errors';
import { getStoredToken, setStoredToken } from '@/api/token';
import type { LoginResponse, User } from '@/types/models';

interface AuthState {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  error: string | null;
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthState['user']>(null);
  const token = ref<AuthState['token']>(getStoredToken());
  const isLoading = ref<AuthState['isLoading']>(false);
  const error = ref<AuthState['error']>(null);
  const isReady = ref(false);

  const isAuthenticated = computed(() => token.value !== null && token.value !== '');

  function persistToken(nextToken: string | null): void {
    token.value = nextToken;
    setStoredToken(nextToken);
  }

  function clearSession(): void {
    user.value = null;
    error.value = null;
    persistToken(null);
  }

  async function login(email: string, password: string): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await api.post<LoginResponse>('/login', { email, password });
      persistToken(response.data.token);
      user.value = response.data.user;
    } catch (caught) {
      clearSession();
      error.value = normalizeApiError(caught, 'Не удалось войти.').message;
      throw caught;
    } finally {
      isLoading.value = false;
      isReady.value = true;
    }
  }

  async function logout(): Promise<void> {
    try {
      if (token.value !== null && token.value !== '') {
        await api.post('/logout');
      }
    } catch {
      // Сессию на клиенте очищаем даже если сервер уже отклонил токен.
    } finally {
      clearSession();
    }
  }

  async function fetchUser(): Promise<void> {
    if (token.value === null || token.value === '') {
      clearSession();
      isReady.value = true;
      return;
    }

    try {
      const response = await api.get<User>('/user');
      user.value = response.data;
    } catch {
      clearSession();
    } finally {
      isReady.value = true;
    }
  }

  return {
    user,
    token,
    isLoading,
    error,
    isReady,
    isAuthenticated,
    login,
    logout,
    fetchUser,
    clearSession,
  };
});
