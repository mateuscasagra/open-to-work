<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/modules/auth/stores/auth';
import { useSupportRequest, type SupportError } from '@/modules/support/composables/useSupportRequest';
import logoLight from '@/assets/logo4.png';

const { t } = useI18n();
const auth = useAuthStore();
const mutation = useSupportRequest();

// Pré-preenche se autenticado — visitante anônimo informa do zero.
const name = ref(auth.user?.name ?? '');
const email = ref(auth.user?.email ?? '');
const title = ref('');
const description = ref('');

const sent = ref(false);
const errorMsg = ref<string | null>(null);

const canSubmit = computed(
  () => email.value.trim() !== ''
    && title.value.trim().length >= 3
    && description.value.trim().length >= 10
    && !mutation.isPending.value,
);

const descCharCount = computed(() => description.value.length);

async function submit(): Promise<void> {
  errorMsg.value = null;
  try {
    await mutation.mutateAsync({
      name: name.value.trim() || undefined,
      email: email.value.trim(),
      title: title.value.trim(),
      description: description.value.trim(),
    });
    sent.value = true;
  } catch (e) {
    const err = e as SupportError;
    if (err.kind === 'rate_limited') {
      errorMsg.value = t('support.errors.rate_limited');
    } else if (err.kind === 'validation') {
      errorMsg.value = err.message || t('support.errors.validation');
    } else {
      errorMsg.value = t('support.errors.unknown');
    }
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-ink-50 px-6 py-12">
    <div class="w-full max-w-xl">
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
          {{ t('support.title') }}
        </h1>
        <p class="mt-2 text-sm text-ink-500">
          {{ t('support.subtitle') }}
        </p>

        <form
          class="mt-6 space-y-4"
          @submit.prevent="submit"
        >
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label
                for="support-name"
                class="label"
              >{{ t('support.form.name') }}</label>
              <input
                id="support-name"
                v-model="name"
                type="text"
                maxlength="120"
                autocomplete="name"
                :placeholder="t('support.form.name_placeholder')"
                class="input mt-1.5"
              >
            </div>
            <div>
              <label
                for="support-email"
                class="label"
              >{{ t('support.form.email') }} *</label>
              <input
                id="support-email"
                v-model="email"
                type="email"
                required
                maxlength="255"
                autocomplete="email"
                placeholder="voce@email.com"
                class="input mt-1.5"
              >
            </div>
          </div>

          <div>
            <label
              for="support-title"
              class="label"
            >{{ t('support.form.title_field') }} *</label>
            <input
              id="support-title"
              v-model="title"
              type="text"
              required
              minlength="3"
              maxlength="200"
              :placeholder="t('support.form.title_placeholder')"
              class="input mt-1.5"
            >
          </div>

          <div>
            <label
              for="support-description"
              class="label"
            >{{ t('support.form.description') }} *</label>
            <textarea
              id="support-description"
              v-model="description"
              required
              minlength="10"
              maxlength="5000"
              rows="7"
              :placeholder="t('support.form.description_placeholder')"
              class="input mt-1.5"
            />
            <p class="mt-1 text-right text-xs text-ink-400">
              {{ descCharCount }}/5000
            </p>
          </div>

          <p
            v-if="errorMsg"
            class="whitespace-pre-line rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            role="alert"
          >
            {{ errorMsg }}
          </p>

          <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
            <RouterLink
              :to="{ name: 'landing' }"
              class="text-center text-sm font-medium text-ink-500 hover:text-ink-900"
            >
              {{ t('support.cancel') }}
            </RouterLink>
            <button
              type="submit"
              :disabled="!canSubmit"
              class="btn-primary"
            >
              {{ mutation.isPending.value ? t('support.sending') : t('support.submit') }}
            </button>
          </div>
        </form>
      </div>

      <div
        v-else
        class="card p-8 text-center"
      >
        <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-600">
          <svg
            class="h-7 w-7"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.75"
              d="M5 13l4 4L19 7"
            />
          </svg>
        </div>
        <h1 class="text-xl font-bold tracking-tight text-ink-900">
          {{ t('support.sent_title') }}
        </h1>
        <p class="mt-2 text-sm text-ink-500">
          {{ t('support.sent_subtitle') }}
        </p>
        <RouterLink
          :to="{ name: 'landing' }"
          class="mt-6 inline-block text-sm font-medium text-brand-600 hover:text-brand-700"
        >
          {{ t('support.back_to_landing') }}
        </RouterLink>
      </div>
    </div>
  </div>
</template>
