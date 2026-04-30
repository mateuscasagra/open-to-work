<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import logoLight from '@/assets/logo4.png';

const { t } = useI18n();
const auth = useAuthStore();

const email = ref('');
const loading = ref(false);
const sent = ref(false);
const error = ref<string | null>(null);

async function submit() {
  loading.value = true;
  error.value = null;
  try {
    await auth.forgotPassword(email.value);
    sent.value = true;
  } catch (e) {
    error.value = t('auth.forgotPassword.failed');
    console.error(e);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-ink-50 px-6 py-12">
    <div class="w-full max-w-md">
      <RouterLink
        :to="{ name: 'landing' }"
        class="mb-8 inline-flex"
      >
        <img
          :src="logoLight"
          alt="Open to Work"
          class="h-16 object-contain"
        >
      </RouterLink>

      <div
        v-if="!sent"
        class="card p-6"
      >
        <h1 class="text-2xl font-bold tracking-tight text-ink-900">
          {{ t('auth.forgotPassword.title') }}
        </h1>
        <p class="mt-2 text-sm text-ink-500">
          {{ t('auth.forgotPassword.subtitle') }}
        </p>

        <form
          class="mt-6 space-y-4"
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
              placeholder="voce@email.com"
              class="input mt-1.5"
            >
          </div>

          <p
            v-if="error"
            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            role="alert"
          >
            {{ error }}
          </p>

          <button
            type="submit"
            :disabled="loading"
            class="btn-primary w-full"
          >
            {{ loading ? t('auth.forgotPassword.sending') : t('auth.forgotPassword.submit') }}
          </button>
        </form>

        <p class="mt-4 text-center text-sm">
          <RouterLink
            :to="{ name: 'login' }"
            class="font-medium text-brand-600 hover:text-brand-700"
          >
            {{ t('auth.forgotPassword.back_to_login') }}
          </RouterLink>
        </p>
      </div>

      <div
        v-else
        class="card p-6 text-center"
      >
        <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-full bg-brand-50 text-brand-600">
          <svg
            class="h-6 w-6"
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
        </div>
        <h1 class="text-xl font-bold tracking-tight text-ink-900">
          {{ t('auth.forgotPassword.sent_title') }}
        </h1>
        <p class="mt-2 text-sm text-ink-500">
          {{ t('auth.forgotPassword.sent_subtitle') }}
        </p>
        <RouterLink
          :to="{ name: 'login' }"
          class="mt-6 inline-block text-sm font-medium text-brand-600 hover:text-brand-700"
        >
          {{ t('auth.forgotPassword.back_to_login') }}
        </RouterLink>
      </div>
    </div>
  </div>
</template>
