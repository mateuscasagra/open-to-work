<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSubscription } from '@/modules/subscription/composables/useSubscription';
import { useSubscribeToPro, type SubscribeError } from '@/modules/subscription/composables/useSubscribeToPro';
import { useCancelSubscription } from '@/modules/subscription/composables/useCancelSubscription';
import PixCheckoutModal from '@/modules/subscription/components/PixCheckoutModal.vue';
import CpfPromptModal from '@/modules/subscription/components/CpfPromptModal.vue';
import type { PixCheckout } from '@/shared/api/schemas';
import { formatBrl } from '@/shared/format/currency';

const { t, locale } = useI18n();
const subscription = useSubscription();
const subscribeMutation = useSubscribeToPro();
const cancelMutation = useCancelSubscription();

const checkout = ref<PixCheckout | null>(null);
const cpfModalOpen = ref(false);
const cpfModalError = ref<string | null>(null);
const subscribeError = ref<string | null>(null);
const cancelError = ref<string | null>(null);
const cancelDialogOpen = ref(false);

const isPro = computed(() =>
  subscription.value?.plan === 'pro' && subscription.value?.status !== 'canceled',
);
const isCanceledPro = computed(() =>
  subscription.value?.plan === 'pro' && subscription.value?.status === 'canceled',
);
const isPastDuePro = computed(() =>
  subscription.value?.plan === 'pro' && subscription.value?.status === 'past_due',
);
const showUpgradeCard = computed(() => !isPro.value);

const quota = computed(() => subscription.value?.quota ?? null);

const percentUsed = computed(() => {
  const q = quota.value;
  if (!q || q.limit === null || q.limit === 0) return 0;
  return Math.min(100, Math.round((q.used / q.limit) * 100));
});

const usageColor = computed(() => {
  if (percentUsed.value >= 100) return 'bg-red-500';
  if (percentUsed.value >= 80) return 'bg-amber-500';
  return 'bg-brand-500';
});

function fmtDate(iso: string | null | undefined): string {
  if (!iso) return '';
  try {
    const lang = locale.value.replace('_', '-');
    return new Intl.DateTimeFormat(lang, { day: '2-digit', month: 'long', year: 'numeric' })
      .format(new Date(iso));
  } catch {
    return iso;
  }
}

const periodEndLabel = computed(() => fmtDate(subscription.value?.current_period_end));
const resetAtLabel = computed(() => fmtDate(quota.value?.reset_at));

// Preço Pro vem do backend (tabela `plans`). Display sem decimais quando
// é valor inteiro pra ficar mais limpo no card de upgrade.
const proPrice = computed(() => formatBrl(subscription.value?.pro_price_cents ?? 2500, true));

function onSubscribe(): void {
  // Abre o modal de CPF — só depois da confirmação chamamos a mutation.
  // Asaas exige cpfCnpj no customer pra gerar cobrança PIX.
  subscribeError.value = null;
  cpfModalError.value = null;
  cpfModalOpen.value = true;
}

async function onCpfSubmit(cpf: string): Promise<void> {
  cpfModalError.value = null;
  try {
    const result = await subscribeMutation.mutateAsync({ cpf });
    cpfModalOpen.value = false;
    checkout.value = result;
  } catch (e) {
    const err = e as SubscribeError;
    if (err.kind === 'validation') {
      // CPF rejeitado pelo backend (ou Asaas) — mostra no próprio modal.
      cpfModalError.value = err.message || t('subscription.upgrade.errors.unknown');
      return;
    }
    // Erros não relacionados ao CPF: fecha modal e mostra erro geral no card.
    cpfModalOpen.value = false;
    const key = err.kind === 'already' ? 'already'
      : err.kind === 'gateway' ? 'gateway'
      : 'unknown';
    subscribeError.value = t(`subscription.upgrade.errors.${key}`);
  }
}

async function onConfirmCancel(): Promise<void> {
  cancelError.value = null;
  try {
    await cancelMutation.mutateAsync();
    cancelDialogOpen.value = false;
  } catch {
    cancelError.value = t('subscription.cancel_failed');
  }
}

function onCheckoutActivated(): void {
  checkout.value = null;
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-4">
    <header class="mb-2">
      <h1 class="text-2xl font-bold tracking-tight text-ink-900">
        {{ t('subscription.title') }}
      </h1>
      <p class="mt-1 text-sm text-ink-500">
        {{ t('subscription.subtitle') }}
      </p>
    </header>

    <!-- Plano atual -->
    <section class="card border-l-4 border-brand-500 p-5">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wide text-ink-500">
            {{ t('subscription.current_plan') }}
          </p>
          <div class="mt-1 flex items-center gap-2">
            <span
              v-if="isPro || isCanceledPro || isPastDuePro"
              class="inline-flex items-center rounded-md bg-brand-600 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-white"
            >
              {{ t('subscription.plan.pro') }}
            </span>
            <span
              v-else
              class="inline-flex items-center rounded-md bg-ink-200 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-ink-700"
            >
              {{ t('subscription.plan.free') }}
            </span>
            <span
              v-if="subscription"
              class="text-xs text-ink-500"
            >
              · {{ t(`subscription.status.${subscription.status}`) }}
            </span>
          </div>
          <p
            v-if="isPro && periodEndLabel"
            class="mt-2 text-xs text-ink-500"
          >
            {{ t('subscription.renews_on', { date: periodEndLabel }) }}
          </p>
        </div>
        <button
          v-if="isPro"
          type="button"
          class="btn-secondary text-sm"
          :disabled="cancelMutation.isPending.value"
          @click="cancelDialogOpen = true"
        >
          {{ t('subscription.cancel') }}
        </button>
      </div>

      <p
        v-if="isCanceledPro && periodEndLabel"
        class="mt-3 rounded-lg border border-ink-200 bg-ink-50 px-3 py-2 text-sm text-ink-700"
      >
        {{ t('subscription.canceled_notice', { date: periodEndLabel }) }}
      </p>
      <p
        v-if="isPastDuePro"
        class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
      >
        {{ t('subscription.past_due_notice') }}
      </p>
    </section>

    <!-- Uso este mês -->
    <section
      v-if="quota"
      class="card border-l-4 border-brand-500 p-5"
    >
      <h2 class="mb-3 font-semibold text-ink-900">
        {{ t('subscription.usage.title') }}
      </h2>
      <div v-if="quota.limit === null">
        <p class="text-sm font-medium text-brand-700">
          {{ t('subscription.usage.unlimited') }}
        </p>
      </div>
      <div v-else>
        <div class="mb-2 flex items-baseline justify-between gap-2">
          <p class="text-sm font-medium text-ink-900">
            {{ t('subscription.usage.used_of_limit', { used: quota.used, limit: quota.limit }) }}
          </p>
          <p class="text-xs text-ink-500">
            {{ percentUsed }}%
          </p>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-ink-100">
          <div
            class="h-full transition-all"
            :class="usageColor"
            :style="{ width: `${percentUsed}%` }"
          />
        </div>
        <p
          v-if="percentUsed >= 80 && percentUsed < 100"
          class="mt-2 text-xs text-amber-700"
        >
          {{ t('subscription.usage.near_limit') }}
        </p>
        <p class="mt-3 text-xs text-ink-500">
          {{ t('subscription.usage.resets_on', { date: resetAtLabel }) }}
        </p>
      </div>
    </section>

    <!-- Upgrade -->
    <section
      v-if="showUpgradeCard"
      class="card relative overflow-hidden border-l-4 border-brand-500 p-5 ring-2 ring-brand-200"
    >
      <div class="absolute right-4 top-4 hidden sm:block">
        <span class="inline-flex items-center rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-700">
          {{ t('subscription.plan.pro') }}
        </span>
      </div>
      <h2 class="text-lg font-bold text-ink-900">
        {{ t('subscription.upgrade.card_title') }}
      </h2>
      <p class="mt-1 text-sm text-ink-600">
        {{ t('subscription.upgrade.card_subtitle') }}
      </p>

      <div class="mt-4 flex items-baseline gap-1">
        <span class="text-3xl font-bold text-ink-900">{{ proPrice }}</span>
        <span class="text-sm text-ink-500">{{ t('subscription.upgrade.period') }}</span>
      </div>

      <ul class="mt-4 space-y-2 text-sm text-ink-700">
        <li class="flex items-center gap-2">
          <svg
            class="h-4 w-4 shrink-0 text-brand-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
          {{ t('subscription.upgrade.benefit_unlimited') }}
        </li>
        <li class="flex items-center gap-2">
          <svg
            class="h-4 w-4 shrink-0 text-brand-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
          {{ t('subscription.upgrade.benefit_beta') }}
        </li>
        <li class="flex items-center gap-2">
          <svg
            class="h-4 w-4 shrink-0 text-brand-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
          {{ t('subscription.upgrade.benefit_exclusive') }}
        </li>
      </ul>

      <button
        type="button"
        class="btn-primary mt-5 w-full sm:w-auto"
        :disabled="subscribeMutation.isPending.value"
        @click="onSubscribe"
      >
        {{ subscribeMutation.isPending.value
          ? t('subscription.upgrade.subscribing')
          : t('subscription.upgrade.cta') }}
      </button>

      <p
        v-if="subscribeError"
        class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        role="alert"
      >
        {{ subscribeError }}
      </p>
    </section>

    <!-- Cancel confirmation dialog -->
    <div
      v-if="cancelDialogOpen"
      class="fixed inset-0 z-50 flex items-end justify-center bg-ink-900/50 p-4 backdrop-blur-sm sm:items-center"
      role="dialog"
      aria-modal="true"
    >
      <div class="w-full max-w-md rounded-2xl bg-white shadow-card">
        <div class="px-6 py-5">
          <h2 class="text-lg font-semibold text-ink-900">
            {{ t('subscription.cancel') }}
          </h2>
          <p class="mt-2 text-sm text-ink-700">
            {{ t('subscription.cancel_confirm', { date: periodEndLabel }) }}
          </p>
          <p
            v-if="cancelError"
            class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            role="alert"
          >
            {{ cancelError }}
          </p>
        </div>
        <div class="flex flex-col-reverse gap-2 border-t border-ink-100 px-6 py-4 sm:flex-row sm:justify-end">
          <button
            type="button"
            class="btn-ghost"
            :disabled="cancelMutation.isPending.value"
            @click="cancelDialogOpen = false"
          >
            {{ t('applications.manual_cancel') }}
          </button>
          <button
            type="button"
            class="btn-danger"
            :disabled="cancelMutation.isPending.value"
            @click="onConfirmCancel"
          >
            {{ t('subscription.cancel') }}
          </button>
        </div>
      </div>
    </div>

    <CpfPromptModal
      v-if="cpfModalOpen"
      :saving="subscribeMutation.isPending.value"
      :server-error="cpfModalError"
      @close="cpfModalOpen = false"
      @submit="onCpfSubmit"
    />

    <PixCheckoutModal
      v-if="checkout"
      :checkout="checkout"
      @close="checkout = null"
      @activated="onCheckoutActivated"
    />
  </div>
</template>
