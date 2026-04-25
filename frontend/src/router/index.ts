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
    return { name: 'dashboard' };
  }

  if (!to.meta.public && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  if (to.meta.adminOnly && !auth.user?.is_admin) {
    return { name: 'dashboard' };
  }

  return true;
});

export default router;
