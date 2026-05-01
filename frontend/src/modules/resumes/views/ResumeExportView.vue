<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/auth/stores/auth';
import { useResumeDetail } from '@/modules/resumes/composables/useResumeDetail';
import { useResumePdfExport } from '@/modules/resumes/composables/useResumePdfExport';
import ClassicTemplate from '@/modules/resumes/templates/ClassicTemplate.vue';
import ModernTemplate from '@/modules/resumes/templates/ModernTemplate.vue';

type TemplateKey = 'classic' | 'modern';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const resumeId = computed(() => Number(route.params.id));
const detail = useResumeDetail(resumeId);
const { exportToPdf, exporting, error } = useResumePdfExport();

const selectedTemplate = ref<TemplateKey>('classic');

async function onDownload(): Promise<void> {
  if (!detail.data.value) return;
  await exportToPdf({
    resume: detail.data.value,
    template: selectedTemplate.value,
    userName: auth.user?.name,
    filename: detail.data.value.title || 'resume',
  });
}

function onBack(): void {
  router.push({ name: 'resumes' });
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-900"
        @click="onBack"
      >
        <span aria-hidden="true">←</span> {{ t('resumes.back') }}
      </button>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm">
          <span class="text-ink-600">{{ t('resumes.export.template') }}</span>
          <select
            v-model="selectedTemplate"
            class="input !py-1.5 text-sm"
          >
            <option value="classic">{{ t('resumes.export.templates.classic') }}</option>
            <option value="modern">{{ t('resumes.export.templates.modern') }}</option>
          </select>
        </label>
        <button
          type="button"
          class="btn-primary"
          :disabled="exporting || detail.isLoading.value || !detail.data.value"
          @click="onDownload"
        >
          {{ exporting ? t('resumes.export.exporting') : t('resumes.export.download') }}
        </button>
      </div>
    </div>

    <p
      v-if="error"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      role="alert"
    >
      {{ error }}
    </p>

    <p
      v-if="detail.isLoading.value"
      class="text-ink-500"
    >
      {{ t('resumes.loading') }}
    </p>
    <p
      v-else-if="detail.error.value"
      class="text-red-600"
      role="alert"
    >
      {{ t('resumes.load_failed') }}
    </p>

    <div
      v-else-if="detail.data.value"
      class="flex justify-center overflow-auto rounded-xl border border-ink-200 bg-ink-100 p-6"
    >
      <div class="shadow-card">
        <ClassicTemplate
          v-if="selectedTemplate === 'classic'"
          :resume="detail.data.value"
          :user-name="auth.user?.name"
        />
        <ModernTemplate
          v-else
          :resume="detail.data.value"
          :user-name="auth.user?.name"
        />
      </div>
    </div>
  </div>
</template>
