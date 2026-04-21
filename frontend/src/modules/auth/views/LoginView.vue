<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();

const email = ref('');
const password = ref('');
const remember = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);

async function submit() {
  loading.value = true;
  error.value = null;
  try {
    await auth.login(email.value, password.value, remember.value);
    router.push({ name: 'dashboard' });
  } catch (e) {
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
      <RouterLink
        :to="{ name: 'landing' }"
        class="relative flex items-center gap-2.5"
      >
        <span
          class="grid h-9 w-9 place-items-center rounded-lg bg-brand-500 font-bold text-white shadow-glow"
        >
          O
        </span>
        <span class="font-semibold tracking-tight">{{ t('app.name') }}</span>
      </RouterLink>

      <div class="relative">
        <p class="max-w-md text-3xl font-semibold leading-tight tracking-tight">
          {{ t('landing.title_1') }}
          <span class="bg-gradient-to-r from-brand-300 to-teal-300 bg-clip-text text-transparent">
            {{ t('landing.title_2') }}
          </span>
        </p>
        <p class="mt-4 max-w-md text-white/70">
          {{ t('app.tagline') }}
        </p>
      </div>

      <div class="relative text-sm text-white/50">
        © 2026 · {{ t('app.name') }}
      </div>
    </aside>

    <!-- Right: form -->
    <div class="flex w-full items-center justify-center px-6 py-12 lg:w-1/2">
      <div class="w-full max-w-md">
        <RouterLink
          :to="{ name: 'landing' }"
          class="mb-8 inline-flex items-center gap-2 text-sm text-ink-500 hover:text-ink-900 lg:hidden"
        >
          <span aria-hidden="true">←</span> {{ t('app.name') }}
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
          class="mt-8 space-y-5"
          @submit.prevent="submit"
        >
          <div>
            <label
              for="email"
              class="label"
            >{{ t('auth.email') }}</label>
            <input
              id="email"
              v-model="email"
              type="email"
              required
              autocomplete="email"
              class="input mt-1.5"
            >
          </div>

          <div>
            <label
              for="password"
              class="label"
            >{{ t('auth.password') }}</label>
            <input
              id="password"
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              class="input mt-1.5"
            >
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
            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          >
            {{ error }}
          </p>

          <button
            type="submit"
            :disabled="loading"
            class="btn-primary w-full !py-2.5"
          >
            {{ loading ? t('auth.processing') : t('auth.login') }}
          </button>
        </form>

        <div class="my-6 flex items-center gap-3 text-xs uppercase tracking-wider text-ink-400">
          <div class="h-px flex-1 bg-ink-200" />
          {{ t('auth.or') }}
          <div class="h-px flex-1 bg-ink-200" />
        </div>

        <div class="space-y-2">
          <a
            :href="auth.oauthUrl('google')"
            class="btn-secondary w-full"
          >
            {{ t('auth.continueWith', { provider: 'Google' }) }}
          </a>
          <a
            :href="auth.oauthUrl('linkedin')"
            class="btn-secondary w-full"
          >
            {{ t('auth.continueWith', { provider: 'LinkedIn' }) }}
          </a>
          <a
            :href="auth.oauthUrl('github')"
            class="btn-secondary w-full"
          >
            {{ t('auth.continueWith', { provider: 'GitHub' }) }}
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
