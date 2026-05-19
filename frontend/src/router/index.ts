import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/modules/auth/stores/auth';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'landing',
    component: () => import('@/modules/landing/views/LandingView.vue'),
    meta: { public: true, guestOnly: false },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/modules/auth/views/LoginView.vue'),
    meta: { public: true, guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/modules/auth/views/RegisterView.vue'),
    meta: { public: true, guestOnly: true },
  },
  {
    path: '/verify-email',
    name: 'verify-email',
    component: () => import('@/modules/auth/views/VerifyEmailView.vue'),
    meta: { public: true, guestOnly: true },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/modules/auth/views/ForgotPasswordView.vue'),
    meta: { public: true, guestOnly: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/modules/auth/views/ResetPasswordView.vue'),
    meta: { public: true, guestOnly: true },
  },
  {
    path: '/auth/callback',
    name: 'auth.callback',
    component: () => import('@/modules/auth/views/OAuthCallbackView.vue'),
    meta: { public: true },
  },
  {
    path: '/app',
    component: () => import('@/shared/layouts/AppLayout.vue'),
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/modules/metrics/views/DashboardView.vue'),
      },
      {
        path: 'jobs',
        name: 'jobs',
        component: () => import('@/modules/jobs/views/JobsListView.vue'),
      },
      {
        path: 'applications',
        name: 'applications',
        component: () => import('@/modules/applications/views/ApplicationsKanbanView.vue'),
      },
      {
        path: 'applications/:id(\\d+)',
        name: 'application-detail',
        component: () => import('@/modules/applications/views/ApplicationDetailView.vue'),
      },
      {
        path: 'resumes',
        name: 'resumes',
        component: () => import('@/modules/resumes/views/ResumesListView.vue'),
      },
      {
        path: 'resumes/new',
        name: 'resume-new',
        component: () => import('@/modules/resumes/views/ResumeBuilderView.vue'),
      },
      {
        path: 'resumes/:id(\\d+)/edit',
        name: 'resume-edit',
        component: () => import('@/modules/resumes/views/ResumeBuilderView.vue'),
      },
      {
        path: 'resumes/:id(\\d+)/export',
        name: 'resume-export',
        component: () => import('@/modules/resumes/views/ResumeExportView.vue'),
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('@/modules/profile/views/ProfileView.vue'),
      },
      {
        path: 'suggestions',
        name: 'suggestions',
        component: () => import('@/modules/suggestions/views/SuggestionsView.vue'),
      },
      {
        path: 'admin',
        name: 'admin',
        component: () => import('@/modules/admin/views/AdminView.vue'),
        meta: { adminOnly: true },
      },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, _from, saved) {
    if (saved) return saved;
    if (to.hash) return { el: to.hash, behavior: 'smooth' };
    return { top: 0 };
  },
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();

  if (!auth.initialized) {
    await auth.fetchMe();
  }

  if (to.meta.guestOnly && auth.user) {
    return auth.locationComplete
      ? { name: 'dashboard' }
      : { name: 'profile' };
  }

  if (!to.meta.public && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  if (auth.user && !auth.locationComplete && !to.meta.public && to.name !== 'profile') {
    return { name: 'profile' };
  }

  if (to.meta.adminOnly && !auth.user?.is_admin) {
    return { name: 'dashboard' };
  }

  return true;
});

// Após um deploy, o navegador pode ter o index.js antigo carregado em memória
// e tentar importar chunks com hashes que não existem mais no servidor (404).
// Detectamos esse caso e forçamos um full reload para buscar o index.html novo.
// Uma flag por destino em sessionStorage evita loops infinitos caso o erro
// persista após o reload (ex.: bug real no novo build).
const CHUNK_ERROR_PATTERNS = [
  'Failed to fetch dynamically imported module',
  'error loading dynamically imported module',
  'Importing a module script failed',
];

router.onError((error, to) => {
  const message = error instanceof Error ? error.message : String(error);
  const isChunkError = CHUNK_ERROR_PATTERNS.some((p) => message.includes(p));

  if (!isChunkError) return;

  const reloadKey = `chunk-reload:${to.fullPath}`;
  if (sessionStorage.getItem(reloadKey)) {
    console.error('[router] chunk load failed after reload:', error);
    return;
  }

  sessionStorage.setItem(reloadKey, '1');
  window.location.assign(to.fullPath);
});

export default router;
