import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { requiresGuest: true },
  },
  {
    path: '/',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/organizations/:id',
    name: 'organization',
    component: () => import('@/views/OrganizationView.vue'),
    meta: { requiresAuth: true },
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();

  if (!auth.isReady) {
    await auth.fetchUser();
  }

  if (to.meta.requiresAuth === true && !auth.isAuthenticated) {
    return { name: 'login' };
  }

  if (to.meta.requiresGuest === true && auth.isAuthenticated) {
    return { name: 'dashboard' };
  }

  return true;
});

export default router;
