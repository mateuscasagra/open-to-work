<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/auth/stores/auth';
import { formatBrl } from '@/shared/format/currency';

const props = defineProps<{ limit: number; resetAt?: string }>();
const emit = defineEmits<{ (e: 'close'): void }>();

const { t, locale } = useI18n();
const router = useRouter();
const auth = useAuthStore();

// Preço lido do envelope /api/me (tabela `plans` é a fonte da verdade).
// Compact: sem casas decimais quando inteiro (R$ 25 em vez de R$ 25,00).
const proPrice = computed(() => formatBrl(auth.subscription?.pro_price_cents ?? 2500, true));

const formattedReset = computed(() => {
  if (!props.resetAt) return '';
  try {
    const lang = locale.value.replace('_', '-');
    return new Intl.DateTimeFormat(lang, { day: '2-digit', month: 'long' })
      .format(new Date(props.resetAt));
  } catch {
    return props.resetAt;
  }
});

function goToPlan(): void {
  emit('close');
  router.push({ name: 'plan' });
}
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
          {{ t('subscription.upgrade_modal.title') }}
        </h2>
      </div>
      <div class="px-6 py-5">
        <p class="text-sm text-ink-700">
          {{ t('subscription.upgrade_modal.body', { limit }) }}
        </p>
        <p
          v-if="formattedReset"
          class="mt-3 text-xs text-ink-500"
        >
          {{ t('subscription.usage.resets_on', { date: formattedReset }) }}
        </p>
      </div>
      <div class="flex flex-col-reverse gap-2 border-t border-ink-100 px-6 py-4 sm:flex-row sm:justify-end">
        <button
          type="button"
          class="btn-ghost"
          @click="emit('close')"
        >
          {{ t('subscription.upgrade_modal.cta_wait') }}
        </button>
        <button
          type="button"
          class="btn-primary"
          @click="goToPlan"
        >
          {{ t('subscription.upgrade_modal.cta_upgrade', { price: proPrice }) }}
        </button>
      </div>
    </div>
  </div>
</template>
