import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { normalizeApiError } from '@/api/errors';
import { getStoredToken, setStoredToken } from '@/api/token';

const client = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

client.interceptors.request.use((config: InternalAxiosRequestConfig): InternalAxiosRequestConfig => {
  const token = getStoredToken();

  if (token !== null && token !== '') {
    config.headers.set('Authorization', `Bearer ${token}`);
  }

  return config;
});

client.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const normalized = normalizeApiError(error);
    const requestUrl = error.config?.url ?? '';
    const isLoginRequest = requestUrl.includes('/login');

    if (normalized.status === 401 && !isLoginRequest) {
      setStoredToken(null);
      const { useAuthStore } = await import('@/stores/auth');
      useAuthStore().clearSession();

      const { default: router } = await import('@/router');
      if (router.currentRoute.value.meta.requiresAuth === true) {
        await router.push({ name: 'login' });
      }
    }

    return Promise.reject(normalized);
  },
);

export default client;
