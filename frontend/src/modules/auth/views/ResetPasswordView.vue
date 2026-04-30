<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import logoLight from '@/assets/logo4.png';

const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const tokenParam = computed(() => {
  const v = route.query.token;
  return typeof v === 'string' ? v : '';
});
const emailParam = computed(() => {
  const v = route.query.email;
  return typeof v === 'string' ? v : '';
});

const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});

const missingToken = computed(() => !tokenParam.value || !emailParam.value);

async function submit() {
  if (missingToken.value) return;
  loading.value = true;
  error.value = null;
  fieldErrors.value = {};
  try {
    await auth.resetPassword({
      email: emailParam.value,
      token: tokenParam.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    await router.push({ name: 'dashboard' });
  } catch (e) {
    const err = e as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } };
    if (err.response?.status === 422) {
      fieldErrors.value = err.response.data?.errors ?? {};
      const tokenErr = fieldErrors.value.token?.[0];
      error.value = tokenErr ?? t('auth.resetPassword.failed');
    } else {
      error.value = t('auth.resetPassword.failed');
    }
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

      <div class="card p-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink-900">
          {{ t('auth.resetPassword.title') }}
        </h1>
        <p class="mt-2 text-sm text-ink-500">
          {{ t('auth.resetPassword.subtitle') }}
        </p>

        <p
          v-if="missingToken"
          class="mt-6 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {{ t('auth.resetPassword.missing_token') }}
        </p>

        <form
          v-else
          class="mt-6 space-y-4"
          @submit.prevent="submit"
        >
          <div>
            <label
              for="password"
              class="label"
            >{{ t('auth.resetPassword.new_password') }}</label>
            <input
              id="password"
              v-model="password"
              type="password"
              required
              autocomplete="new-password"
              placeholder="••••••••"
              class="input mt-1.5"
            >
            <p
              v-if="fieldErrors.password?.[0]"
              class="mt-1 text-xs text-red-600"
            >
              {{ fieldErrors.password[0] }}
            </p>
          </div>

          <div>
            <label
              for="password_confirmation"
              class="label"
            >{{ t('auth.resetPassword.confirm_password') }}</label>
            <input
              id="password_confirmation"
              v-model="passwordConfirmation"
              type="password"
              required
              autocomplete="new-password"
              placeholder="••••••••"
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
            {{ loading ? t('auth.resetPassword.processing') : t('auth.resetPassword.submit') }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
