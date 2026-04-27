<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import logoLight from '@/assets/logo4.png';
import logoDark from '@/assets/logo4branca.png';

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();

const email = ref('');
const password = ref('');
const remember = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const showPassword = ref(false);

async function submit() {
  loading.value = true;
  error.value = null;
  try {
    await auth.login(email.value, password.value, remember.value);
    router.push({ name: 'dashboard' });
  } catch (e) {
    const err = e as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } };
    if (err.response?.status === 403) {
      auth.setPendingVerificationEmail(email.value);
      router.push({ name: 'verify-email', query: { email: email.value } });
      return;
    }
    error.value = t('auth.loginFailed');
    console.error(e);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="flex min-h-screen bg-white">
    <!-- Left: hero panel -->
    <aside
      class="relative hidden w-1/2 overflow-hidden bg-gradient-hero p-12 text-white lg:flex lg:flex-col lg:justify-between"
    >
      <div
        class="absolute inset-0 bg-grid-slate opacity-[0.06]"
        aria-hidden="true"
      />
      <div
        class="absolute -left-20 top-1/3 h-80 w-80 rounded-full bg-brand-500/25 blur-3xl"
        aria-hidden="true"
      />
      <div
        class="absolute -right-32 bottom-10 h-96 w-96 rounded-full bg-emerald-400/10 blur-3xl"
        aria-hidden="true"
      />

      <RouterLink
        :to="{ name: 'landing' }"
        class="relative inline-flex"
      >
        <img
          :src="logoDark"
          alt="Open to Work"
          class="h-20 object-contain"
        >
      </RouterLink>

      <div class="relative">
        <span
          class="inline-flex items-center gap-2 rounded-full border border-emerald-300/30 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200 backdrop-blur"
        >
          <span class="relative flex h-1.5 w-1.5">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400" />
          </span>
          {{ t('landing.eyebrow') }}
        </span>

        <p class="mt-6 max-w-md text-4xl font-semibold leading-[1.1] tracking-tight">
          {{ t('landing.title_1') }}
          <span class="bg-gradient-to-r from-brand-300 via-emerald-400 to-teal-300 bg-clip-text text-transparent">
            {{ t('landing.title_2') }}
          </span>
        </p>
        <p class="mt-5 max-w-md text-white/70">
          {{ t('app.tagline') }}
        </p>

        <ul class="mt-8 space-y-3 text-sm text-white/80">
          <li class="flex items-center gap-3">
            <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
              <svg
                class="h-3 w-3"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="3"
                  d="M4.5 12.75l6 6 9-13.5"
                />
              </svg>
            </span>
            {{ t('landing.features.kanban_title') }}
          </li>
          <li class="flex items-center gap-3">
            <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
              <svg
                class="h-3 w-3"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="3"
                  d="M4.5 12.75l6 6 9-13.5"
                />
              </svg>
            </span>
            {{ t('landing.features.metrics_title') }}
          </li>
          <li class="flex items-center gap-3">
            <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
              <svg
                class="h-3 w-3"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="3"
                  d="M4.5 12.75l6 6 9-13.5"
                />
              </svg>
            </span>
            {{ t('landing.features.resumes_title') }}
          </li>
        </ul>
      </div>

      <div class="relative text-sm text-white/50">
        © 2026 · {{ t('landing.footer.rights') }}
      </div>
    </aside>

    <!-- Right: form -->
    <div class="flex w-full items-center justify-center px-6 py-12 lg:w-1/2">
      <div class="w-full max-w-md">
        <RouterLink
          :to="{ name: 'landing' }"
          class="mb-8 inline-flex lg:hidden"
        >
          <img
            :src="logoLight"
            alt="Open to Work"
            class="h-16 object-contain"
          >
        </RouterLink>

        <h1 class="text-3xl font-bold tracking-tight text-ink-900">
          {{ t('auth.login') }}
        </h1>
        <p class="mt-2 text-sm text-ink-500">
          {{ t('auth.noAccount') }}
          <RouterLink
            :to="{ name: 'register' }"
            class="font-semibold text-brand-600 hover:text-brand-700"
          >
            {{ t('auth.signUp') }}
          </RouterLink>
        </p>

        <form
          class="mt-8 space-y-4"
          @submit.prevent="submit"
        >
          <div>
            <label
              for="email"
              class="label"
            >{{ t('auth.email') }}</label>
            <div class="relative mt-1.5">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-400">
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
                    d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
                  />
                </svg>
              </span>
              <input
                id="email"
                v-model="email"
                type="email"
                required
                autocomplete="email"
                placeholder="voce@email.com"
                class="input !pl-10"
              >
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between">
              <label
                for="password"
                class="label"
              >{{ t('auth.password') }}</label>
              <a class="text-xs font-medium text-brand-600 hover:text-brand-700">
                {{ t('auth.forgot') }}
              </a>
            </div>
            <div class="relative mt-1.5">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-400">
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
                    d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
                  />
                </svg>
              </span>
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="input !pl-10 !pr-10"
              >
              <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-ink-400 hover:text-ink-700"
                :aria-label="showPassword ? t('auth.hide_password') : t('auth.show_password')"
                @click="showPassword = !showPassword"
              >
                <svg
                  v-if="!showPassword"
                  class="h-4 w-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.75"
                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.75"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                </svg>
                <svg
                  v-else
                  class="h-4 w-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.75"
                    d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                  />
                </svg>
              </button>
            </div>
          </div>

          <label class="flex items-center gap-2 text-sm text-ink-700">
            <input
              v-model="remember"
              type="checkbox"
              class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
            >
            {{ t('auth.remember') }}
          </label>

          <p
            v-if="error"
            class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          >
            <svg
              class="mt-0.5 h-4 w-4 flex-none"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
              />
            </svg>
            {{ error }}
          </p>

          <button
            type="submit"
            :disabled="loading"
            class="btn-primary w-full !py-2.5 shadow-glow"
          >
            {{ loading ? t('auth.processing') : t('auth.login') }}
            <span
              v-if="!loading"
              aria-hidden="true"
            >→</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
