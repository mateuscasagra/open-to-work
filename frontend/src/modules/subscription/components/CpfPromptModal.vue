<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ saving?: boolean; serverError?: string | null }>();
const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'submit', cpf: string): void;
}>();

const { t } = useI18n();
const cpfInput = ref('');
const inputRef = ref<HTMLInputElement | null>(null);

onMounted(() => {
  inputRef.value?.focus();
});

const digits = computed(() => cpfInput.value.replace(/\D/g, ''));

const isValid = computed(() => digits.value.length === 11 || digits.value.length === 14);

// Máscara dinâmica: CPF (000.000.000-00) ou CNPJ (00.000.000/0000-00).
const masked = computed(() => {
  const d = digits.value;
  if (d.length <= 11) {
    return d
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1-$2');
  }
  return d
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d)/, '$1-$2')
    .slice(0, 18);
});

function onInput(event: Event): void {
  const target = event.target as HTMLInputElement;
  cpfInput.value = target.value;
  // Reescreve com máscara sem perder o caret nas pontas comuns.
  const m = masked.value;
  if (target.value !== m) {
    target.value = m;
    cpfInput.value = m;
  }
}

function onSubmit(): void {
  if (!isValid.value || props.saving) return;
  emit('submit', digits.value);
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
          {{ t('subscription.cpf_prompt.title') }}
        </h2>
        <p class="mt-1 text-sm text-ink-500">
          {{ t('subscription.cpf_prompt.subtitle') }}
        </p>
      </div>

      <form
        class="px-6 py-5"
        @submit.prevent="onSubmit"
      >
        <label class="label">{{ t('subscription.cpf_prompt.label') }}</label>
        <input
          ref="inputRef"
          :value="masked"
          type="text"
          inputmode="numeric"
          autocomplete="off"
          maxlength="18"
          class="input mt-1.5"
          :placeholder="t('subscription.cpf_prompt.placeholder')"
          @input="onInput"
        >
        <p class="mt-2 text-xs text-ink-500">
          {{ t('subscription.cpf_prompt.hint') }}
        </p>

        <p
          v-if="serverError"
          class="mt-3 whitespace-pre-line rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {{ serverError }}
        </p>
      </form>

      <div class="flex flex-col-reverse gap-2 border-t border-ink-100 px-6 py-4 sm:flex-row sm:justify-end">
        <button
          type="button"
          class="btn-ghost"
          :disabled="saving"
          @click="emit('close')"
        >
          {{ t('subscription.cpf_prompt.cancel') }}
        </button>
        <button
          type="button"
          class="btn-primary"
          :disabled="!isValid || saving"
          @click="onSubmit"
        >
          {{ saving ? t('subscription.upgrade.subscribing') : t('subscription.cpf_prompt.continue') }}
        </button>
      </div>
    </div>
  </div>
</template>
