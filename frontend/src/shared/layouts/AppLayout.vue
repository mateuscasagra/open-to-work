<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/modules/auth/stores/auth';
import logoSrc from '@/assets/logo.png';

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();

const mobileOpen = ref(false);
const userMenuOpen = ref(false);

async function logout() {
  await auth.logout();
  router.push({ name: 'landing' });
}

type NavItem = { name: string; label: string; icon: string };

const navItems = computed<NavItem[]>(() => [
  {
    name: 'dashboard',
    label: t('nav.dashboard'),
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  },
  {
    name: 'jobs',
    label: t('nav.jobs'),
    icon: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m6 7v-1a1 1 0 00-1-1h-4a1 1 0 00-1 1v1M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  },
  {
    name: 'applications',
    label: t('nav.applications'),
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
  },
  {
    name: 'resumes',
    label: t('nav.resumes'),
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  },
  {
    name: 'profile',
    label: t('nav.profile'),
    icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
  },
]);

const userInitial = computed(() => (auth.user?.name?.[0] ?? 'U').toUpperCase());
</script>

<template>
  <div class="min-h-screen bg-ink-200 lg:flex lg:h-screen lg:flex-col lg:overflow-hidden">
    <!-- Top navbar -->
    <header class="sticky top-0 z-30 border-b border-ink-300 bg-white shadow-soft">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-10">
        <!-- Left: Brand + nav -->
        <div class="flex items-center gap-8">
          <RouterLink
            :to="{ name: 'dashboard' }"
            class="transition hover:opacity-80"
          >
            <img :src="logoSrc" alt="Open to Work" class="h-20 object-contain">
          </RouterLink>

          <!-- Desktop nav -->
          <nav class="hidden lg:block">
            <ul class="flex items-center gap-1">
              <li
                v-for="item in navItems"
                :key="item.name"
              >
                <RouterLink
                  :to="{ name: item.name }"
                  class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-ink-500 transition hover:bg-ink-100 hover:text-ink-900"
                  exact-active-class="!bg-brand-50 !text-brand-700"
                >
                  <svg
                    class="h-4 w-4 shrink-0"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.75"
                      :d="item.icon"
                    />
                  </svg>
                  <span>{{ item.label }}</span>
                </RouterLink>
              </li>
            </ul>
          </nav>
        </div>

        <!-- Right: User menu + mobile hamburger -->
        <div class="flex items-center gap-3">
          <!-- User dropdown (desktop) -->
          <div class="relative hidden lg:block">
            <button
              type="button"
              class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition hover:bg-ink-100"
              @click="userMenuOpen = !userMenuOpen"
            >
              <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700">
                {{ userInitial }}
              </span>
              <div class="min-w-0 text-left">
                <p class="truncate text-sm font-medium text-ink-900">
                  {{ auth.user?.name ?? '—' }}
                </p>
              </div>
              <svg
                class="h-4 w-4 text-ink-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 9l-7 7-7-7"
                />
              </svg>
            </button>

            <!-- Dropdown -->
            <div
              v-if="userMenuOpen"
              class="absolute right-0 mt-2 w-48 rounded-xl border border-ink-200 bg-white py-1.5 shadow-card"
            >
              <p class="truncate px-3 py-1.5 text-xs text-ink-400">
                {{ auth.user?.email ?? '' }}
              </p>
              <hr class="my-1 border-ink-100">
              <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-ink-700 transition hover:bg-ink-50"
                @click="userMenuOpen = false; logout()"
              >
                <svg
                  class="h-4 w-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.75"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                  />
                </svg>
                {{ t('nav.logout') }}
              </button>
            </div>
          </div>

          <!-- Mobile hamburger -->
          <button
            type="button"
            class="rounded-lg p-2 text-ink-700 hover:bg-ink-100 lg:hidden"
            :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
            @click="mobileOpen = !mobileOpen"
          >
            <svg
              class="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                :d="mobileOpen ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'"
              />
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile nav panel -->
      <div
        v-if="mobileOpen"
        class="border-t border-ink-200 bg-white px-4 py-3 lg:hidden"
      >
        <ul class="space-y-1">
          <li
            v-for="item in navItems"
            :key="item.name"
          >
            <RouterLink
              :to="{ name: item.name }"
              class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-ink-600 transition hover:bg-ink-100 hover:text-ink-900"
              active-class="!bg-brand-50 !text-brand-700"
              @click="mobileOpen = false"
            >
              <svg
                class="h-5 w-5 shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="1.75"
                  :d="item.icon"
                />
              </svg>
              <span>{{ item.label }}</span>
            </RouterLink>
          </li>
        </ul>
        <hr class="my-2 border-ink-100">
        <div class="flex items-center gap-3 px-3 py-2">
          <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700">
            {{ userInitial }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-ink-900">
              {{ auth.user?.name ?? '—' }}
            </p>
            <p class="truncate text-xs text-ink-400">
              {{ auth.user?.email ?? '' }}
            </p>
          </div>
        </div>
        <button
          type="button"
          class="mt-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-600 transition hover:bg-ink-100"
          @click="mobileOpen = false; logout()"
        >
          <svg
            class="h-4 w-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.75"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
            />
          </svg>
          {{ t('nav.logout') }}
        </button>
      </div>
    </header>

    <!-- Backdrop mobile -->
    <div
      v-if="mobileOpen"
      class="fixed inset-0 z-20 bg-ink-900/40 backdrop-blur-sm lg:hidden"
      aria-hidden="true"
      @click="mobileOpen = false"
    />

    <!-- Content -->
    <main class="min-w-0 lg:flex-1 lg:min-h-0 lg:overflow-y-auto">
      <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-6">
        <RouterView />
      </div>
    </main>

    <!-- Click-away for user menu -->
    <div
      v-if="userMenuOpen"
      class="fixed inset-0 z-20"
      aria-hidden="true"
      @click="userMenuOpen = false"
    />
  </div>
</template>
