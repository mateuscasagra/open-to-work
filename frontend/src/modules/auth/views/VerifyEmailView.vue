<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import logoLight from '@/assets/logo4.png';
import logoDark from '@/assets/logo4branca.png';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const queryEmail = typeof route.query.email === 'string' ? route.query.email : null;
const initialEmail = queryEmail ?? auth.pendingVerificationEmail ?? '';

const email = ref(initialEmail);
const digits = ref<string[]>(['', '', '', '', '', '']);
const inputs = ref<HTMLInputElement[]>([]);
const loading = ref(false);
const resendLoading = ref(false);
const resendCooldown = ref(0);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});
const successMessage = ref<string | null>(null);

const code = computed(() => digits.value.join(''));
const canSubmit = computed(() => code.value.length === 6 && /^\d{6}$/.test(code.value) && email.value.length > 0);

onMounted(async () => {
  await nextTick();
  if (initialEmail) {
    inputs.value[0]?.focus();
  }
});

function setDigit(index: number, value: string) {
  const cleaned = value.replace(/\D/g, '');

  if (cleaned.length > 1) {
    fillFromIndex(index, cleaned);
    return;
  }

  digits.value[index] = cleaned;
  if (cleaned && index < 5) {
    nextTick(() => inputs.value[index + 1]?.focus());
  }
}

function fillFromIndex(index: number, raw: string) {
  const cleaned = raw.replace(/\D/g, '');
  const chars = cleaned.slice(0, 6 - index).split('');
  for (let i = 0; i < chars.length; i++) {
    digits.value[index + i] = chars[i];
  }
  const next = Math.min(index + chars.length, 5);
  nextTick(() => inputs.value[next]?.focus());
}

function onPaste(index: number, e: ClipboardEvent) {
  const text = e.clipboardData?.getData('text') ?? '';
  if (!/\d/.test(text)) return;
  e.preventDefault();
  fillFromIndex(index, text);
}

function onKeydown(index: number, e: KeyboardEvent) {
  if (e.key === 'Backspace' && !digits.value[index] && index > 0) {
    inputs.value[index - 1]?.focus();
  } else if (e.key === 'ArrowLeft' && index > 0) {
    inputs.value[index - 1]?.focus();
  } else if (e.key === 'ArrowRight' && index < 5) {
    inputs.value[index + 1]?.focus();
  }
}

async function submit() {
  if (!canSubmit.value || loading.value) return;
  loading.value = true;
  error.value = null;
  fieldErrors.value = {};
  successMessage.value = null;
  try {
    await auth.verifyEmail(email.value, code.value);
    router.push({ name: 'profile' });
  } catch (e) {
    const err = e as { response?: { status?: number; data?: { errors?: Record<string, string[]>; message?: string } } };
    if (err.response?.status === 422 && err.response.data?.errors) {
      fieldErrors.value = err.response.data.errors;
    } else {
      error.value = err.response?.data?.message ?? t('auth.verify.failed');
    }
    digits.value = ['', '', '', '', '', ''];
    nextTick(() => inputs.value[0]?.focus());
  } finally {
    loading.value = false;
  }
}

function startCooldown() {
  resendCooldown.value = 30;
  const id = window.setInterval(() => {
    resendCooldown.value -= 1;
    if (resendCooldown.value <= 0) {
      window.clearInterval(id);
      resendCooldown.value = 0;
    }
  }, 1000);
}

async function resend() {
  if (resendLoading.value || resendCooldown.value > 0 || !email.value) return;
  resendLoading.value = true;
  error.value = null;
  fieldErrors.value = {};
  try {
    await auth.resendVerificationCode(email.value);
    successMessage.value = t('auth.verify.resent');
    startCooldown();
  } catch (e) {
    const err = e as { response?: { status?: number; data?: { errors?: Record<string, string[]>; message?: string } } };
    error.value = err.response?.data?.message ?? t('auth.verify.resend_failed');
  } finally {
    resendLoading.value = false;
  }
}
</script>

<template>
  <div class="flex min-h-screen bg-white">
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
          <svg
            class="h-3.5 w-3.5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
            />
          </svg>
          {{ t('auth.verify.eyebrow') }}
        </span>

        <p class="mt-6 max-w-md text-4xl font-semibold leading-[1.1] tracking-tight">
          {{ t('auth.verify.hero_title') }}
        </p>
        <p class="mt-5 max-w-md text-white/70">
          {{ t('auth.verify.hero_subtitle') }}
        </p>
      </div>

      <div class="relative text-sm text-white/50">
        © 2026 · {{ t('landing.footer.rights') }}
      </div>
    </aside>

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
          {{ t('auth.verify.title') }}
        </h1>
        <p class="mt-2 text-sm text-ink-500">
          {{ t('auth.verify.subtitle') }}
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
            <p
              v-if="fieldErrors.email"
              class="mt-1 text-xs text-red-600"
            >
              {{ fieldErrors.email[0] }}
            </p>
          </div>

          <div>
            <label class="label">{{ t('auth.verify.code_label') }}</label>
            <div class="mt-1.5 flex items-center justify-between gap-2">
              <input
                v-for="(_, idx) in digits"
                :key="idx"
                ref="inputs"
                :value="digits[idx]"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="1"
                class="input h-14 w-12 text-center text-2xl font-semibold tracking-widest"
                :aria-label="t('auth.verify.digit_aria', { n: idx + 1 })"
                @input="(e) => setDigit(idx, (e.target as HTMLInputElement).value)"
                @keydown="(e) => onKeydown(idx, e)"
                @paste="(e) => onPaste(idx, e)"
              >
            </div>
            <p
              v-if="fieldErrors.code"
              class="mt-2 text-xs text-red-600"
            >
              {{ fieldErrors.code[0] }}
            </p>
            <p class="mt-2 text-xs text-ink-500">
              {{ t('auth.verify.code_hint') }}
            </p>
          </div>

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

          <p
            v-if="successMessage"
            class="flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
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
                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            {{ successMessage }}
          </p>

          <button
            type="submit"
            :disabled="!canSubmit || loading"
            class="btn-primary w-full !py-2.5 shadow-glow"
          >
            {{ loading ? t('auth.processing') : t('auth.verify.submit') }}
            <span
              v-if="!loading"
              aria-hidden="true"
            >→</span>
          </button>

          <div class="flex items-center justify-between text-sm">
            <RouterLink
              :to="{ name: 'login' }"
              class="font-medium text-ink-500 hover:text-ink-700"
            >
              {{ t('auth.verify.back_to_login') }}
            </RouterLink>
            <button
              type="button"
              :disabled="resendLoading || resendCooldown > 0 || !email"
              class="font-semibold text-brand-600 transition hover:text-brand-700 disabled:cursor-not-allowed disabled:text-ink-400"
              @click="resend"
            >
              <span v-if="resendCooldown > 0">{{ t('auth.verify.resend_in', { seconds: resendCooldown }) }}</span>
              <span v-else-if="resendLoading">{{ t('auth.processing') }}</span>
              <span v-else>{{ t('auth.verify.resend') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
