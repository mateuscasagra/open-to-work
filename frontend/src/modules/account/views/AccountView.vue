<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAccount } from '../composables/useAccount';
import { useAuthStore } from '@/modules/auth/stores/auth';

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();
const { exporting, deleting, error, exportData, deleteAccount } = useAccount();

const confirmText = ref('');
const showDeleteDialog = ref(false);

async function onExport() {
  try {
    await exportData();
  } catch {
    // already recorded in `error`
  }
}

async function onDelete() {
  if (confirmText.value !== 'EXCLUIR') return;
  try {
    await deleteAccount();
    auth.user = null;
    router.push({ name: 'landing' });
  } catch {
    // already recorded in `error`
  }
}
</script>

<template>
  <div class="max-w-2xl lg:flex lg:h-full lg:flex-col">
    <header class="mb-4 lg:mb-3">
      <h1 class="text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">{{ t('account.title') }}</h1>
      <p class="mt-0.5 text-xs text-ink-500">{{ t('account.subtitle') }}</p>
    </header>

    <div
      v-if="error"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
    >
      {{ error }}
    </div>

    <section class="card mb-4 p-6">
      <h2 class="text-lg font-semibold text-ink-900">{{ t('account.export.title') }}</h2>
      <p class="mt-1 text-sm text-ink-500">{{ t('account.export.description') }}</p>
      <button
        type="button"
        class="btn-secondary mt-4"
        :disabled="exporting"
        data-testid="account-export"
        @click="onExport"
      >
        {{ exporting ? t('account.export.exporting') : t('account.export.action') }}
      </button>
    </section>

    <section class="rounded-xl border border-red-200 bg-white p-6 shadow-soft">
      <div class="flex items-start gap-3">
        <span
          class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-red-50 text-red-600"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.2 16a2 2 0 001.73 3z"
            />
          </svg>
        </span>
        <div>
          <h2 class="text-lg font-semibold text-red-800">{{ t('account.delete.title') }}</h2>
          <p class="mt-1 text-sm text-ink-500">{{ t('account.delete.description') }}</p>
        </div>
      </div>

      <div class="mt-5">
        <button
          v-if="!showDeleteDialog"
          type="button"
          class="btn-danger"
          data-testid="account-delete-start"
          @click="showDeleteDialog = true"
        >
          {{ t('account.delete.action') }}
        </button>

        <div v-else class="space-y-3">
          <p class="text-sm text-ink-700">
            {{ t('account.delete.confirm_instruction') }}
          </p>
          <input v-model="confirmText" type="text" class="input" placeholder="EXCLUIR" />
          <div class="flex gap-2">
            <button
              type="button"
              class="btn-danger"
              :disabled="deleting || confirmText !== 'EXCLUIR'"
              data-testid="account-delete-confirm"
              @click="onDelete"
            >
              {{ deleting ? t('account.delete.deleting') : t('account.delete.confirm') }}
            </button>
            <button
              type="button"
              class="btn-secondary"
              @click="
                showDeleteDialog = false;
                confirmText = '';
              "
            >
              {{ t('account.delete.cancel') }}
            </button>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
