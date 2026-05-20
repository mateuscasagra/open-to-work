<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/modules/auth/stores/auth';
import type { PixCheckout } from '@/shared/api/schemas';
import { formatBrl } from '@/shared/format/currency';

const props = defineProps<{ checkout: PixCheckout }>();
const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'activated'): void;
}>();

const { t, locale } = useI18n();
const auth = useAuthStore();

const copied = ref(false);
const elapsedMs = ref(0);
const POLL_INTERVAL_MS = 5_000;
const TIMEOUT_MS = 5 * 60_000;

let pollHandle: number | null = null;
let elapsedHandle: number | null = null;

const timedOut = computed(() => elapsedMs.value >= TIMEOUT_MS);

const imgSrc = computed(() => `data:image/png;base64,${props.checkout.pix_qr_code_base64}`);

// Valor formatado lido da subscription do user (que veio do backend).
// Coerente com o que o Asaas cobra de fato.
const amount = computed(() => formatBrl(auth.subscription?.pro_price_cents ?? 2500));

const formattedDueDate = computed(() => {
  try {
    const lang = locale.value.replace('_', '-');
    return new Intl.DateTimeFormat(lang, { day: '2-digit', month: '2-digit', year: 'numeric' })
      .format(new Date(props.checkout.due_date));
  } catch {
    return props.checkout.due_date;
  }
});

async function copyCode(): Promise<void> {
  try {
    await navigator.clipboard.writeText(props.checkout.pix_copy_paste);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2_000);
  } catch {
    // permission denied — keep silent, user can long-press the text
  }
}

async function pollPaymentStatus(): Promise<void> {
  try {
    await auth.fetchMe();
    if (auth.subscription?.plan === 'pro' && auth.subscription?.status === 'active') {
      stopPolling();
      emit('activated');
    }
  } catch {
    // network blip — keep polling
  }
}

function stopPolling(): void {
  if (pollHandle !== null) {
    window.clearInterval(pollHandle);
    pollHandle = null;
  }
  if (elapsedHandle !== null) {
    window.clearInterval(elapsedHandle);
    elapsedHandle = null;
  }
}

onMounted(() => {
  pollHandle = window.setInterval(() => { void pollPaymentStatus(); }, POLL_INTERVAL_MS);
  elapsedHandle = window.setInterval(() => { elapsedMs.value += 1_000; }, 1_000);
});

onBeforeUnmount(stopPolling);
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-end justify-center bg-ink-900/50 p-4 backdrop-blur-sm sm:items-center"
    role="dialog"
    aria-modal="true"
  >
    <div class="w-full max-w-md rounded-2xl bg-white shadow-card">
      <div class="border-b border-ink-100 px-6 py-4">
        <h2 class="text-lg font-semibold text-ink-900">
          {{ t('subscription.checkout.title') }}
        </h2>
        <p class="mt-1 text-sm text-ink-500">
          {{ t('subscription.checkout.subtitle') }}
        </p>
      </div>

      <div class="px-6 py-5">
        <div class="flex flex-col items-center gap-3">
          <img
            :src="imgSrc"
            class="h-56 w-56 rounded-lg border border-ink-200 bg-white"
            alt="QR Code PIX"
          >
          <p class="text-xl font-bold text-ink-900">
            {{ amount }}
          </p>
          <p class="text-xs text-ink-500">
            {{ t('subscription.checkout.due_date', { date: formattedDueDate }) }}
          </p>
        </div>

        <button
          type="button"
          class="btn-secondary mt-4 w-full"
          @click="copyCode"
        >
          {{ copied ? t('subscription.checkout.copied') : t('subscription.checkout.copy_code') }}
        </button>

        <div class="mt-4 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-xs text-brand-700">
          <p v-if="!timedOut">
            {{ t('subscription.checkout.waiting') }}
          </p>
          <p v-else>
            {{ t('subscription.checkout.timeout') }}
          </p>
        </div>
      </div>

      <div class="flex justify-end gap-2 border-t border-ink-100 px-6 py-4">
        <button
          type="button"
          class="btn-ghost"
          @click="emit('close')"
        >
          {{ t('subscription.checkout.close') }}
        </button>
      </div>
    </div>
  </div>
</template>
