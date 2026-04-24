<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useResumes } from '@/modules/resumes/composables/useResumes';
import { useDeleteResume } from '@/modules/resumes/composables/useDeleteResume';
import { useUploadResumePdf } from '@/modules/resumes/composables/useUploadResumePdf';
import { downloadResumePdf } from '@/modules/resumes/composables/useResumePdfDownload';

const { t } = useI18n();
const router = useRouter();
const resumes = useResumes();
const deleteResume = useDeleteResume();
const uploadPdf = useUploadResumePdf();

const uploadInput = ref<HTMLInputElement | null>(null);
const uploadError = ref<string | null>(null);

function formatDate(iso?: string): string {
  return iso ? new Date(iso).toLocaleDateString() : '';
}

function goNew(): void {
  router.push({ name: 'resume-new' });
}

function goEdit(id: number): void {
  router.push({ name: 'resume-edit', params: { id } });
}

function goExport(id: number): void {
  router.push({ name: 'resume-export', params: { id } });
}

async function onDelete(id: number): Promise<void> {
  if (!window.confirm(t('resumes.confirm_delete'))) return;
  await deleteResume.mutateAsync(id);
}

function triggerUpload(): void {
  uploadError.value = null;
  uploadInput.value?.click();
}

async function onUploadChange(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;

  const title = file.name.replace(/\.pdf$/i, '');
  try {
    await uploadPdf.mutateAsync({ title, file });
  } catch {
    uploadError.value = t('resumes.upload_failed');
  } finally {
    input.value = '';
  }
}

async function onDownload(id: number): Promise<void> {
  try {
    const url = await downloadResumePdf(id);
    window.open(url, '_blank', 'noopener,noreferrer');
  } catch {
    uploadError.value = t('resumes.download_failed');
  }
}
</script>

<template>
  <div class="lg:flex lg:h-full lg:flex-col">
    <header class="mb-4 flex flex-wrap items-center justify-between gap-3 lg:mb-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
          {{ t('resumes.title') }}
        </h1>
        <p class="mt-0.5 text-xs text-ink-500">
          {{ t('resumes.subtitle') }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <input
          ref="uploadInput"
          type="file"
          accept="application/pdf"
          class="hidden"
          @change="onUploadChange"
        >
        <button
          type="button"
          class="btn-secondary"
          :disabled="uploadPdf.isPending.value"
          @click="triggerUpload"
        >
          {{ uploadPdf.isPending.value ? t('resumes.uploading') : `⇧ ${t('resumes.upload_pdf')}` }}
        </button>
        <button
          type="button"
          class="btn-primary"
          @click="goNew"
        >
          + {{ t('resumes.new') }}
        </button>
      </div>
    </header>

    <p
      v-if="uploadError"
      class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      role="alert"
    >
      {{ uploadError }}
    </p>

    <p
      v-if="resumes.isLoading.value"
      class="text-ink-500"
    >
      {{ t('resumes.loading') }}
    </p>
    <p
      v-else-if="resumes.error.value"
      class="text-red-600"
      role="alert"
    >
      {{ t('resumes.load_failed') }}
    </p>

    <div
      v-else-if="resumes.data.value && resumes.data.value.data.length === 0"
      class="rounded-2xl border border-dashed border-ink-300 bg-white py-14 text-center"
    >
      <div
        class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-brand-50 text-2xl text-brand-600"
      >
        📄
      </div>
      <p class="mb-4 text-ink-500">
        {{ t('resumes.empty') }}
      </p>
      <button
        type="button"
        class="btn-primary"
        @click="goNew"
      >
        + {{ t('resumes.new') }}
      </button>
    </div>

    <ul
      v-else-if="resumes.data.value"
      class="space-y-2 lg:min-h-0 lg:flex-1 lg:overflow-y-auto"
    >
      <li
        v-for="resume in resumes.data.value.data"
        :key="resume.id"
        class="card flex items-center justify-between px-4 py-3 transition hover:border-brand-200 hover:shadow-card"
      >
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <h2 class="truncate font-semibold text-ink-900">
              {{ resume.title }}
            </h2>
            <span
              v-if="resume.is_pdf_upload"
              class="rounded-md bg-ink-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-ink-600"
            >
              {{ t('resumes.pdf_upload_badge') }}
            </span>
          </div>
          <p class="mt-1 text-xs text-ink-500">
            {{ resume.language }}
            <span v-if="resume.sections_count !== undefined">
              · {{ t('resumes.sections_count', { n: resume.sections_count }) }}
            </span>
            <span v-if="resume.updated_at"> · {{ formatDate(resume.updated_at) }}</span>
          </p>
        </div>
        <div class="ml-4 flex shrink-0 items-center gap-4 text-sm">
          <button
            v-if="resume.is_pdf_upload"
            type="button"
            class="font-medium text-brand-700 hover:text-brand-900"
            @click="onDownload(resume.id)"
          >
            {{ t('resumes.download') }}
          </button>
          <template v-else>
            <button
              type="button"
              class="font-medium text-brand-700 hover:text-brand-900"
              @click="goExport(resume.id)"
            >
              {{ t('resumes.export.action') }}
            </button>
            <button
              type="button"
              class="font-medium text-brand-700 hover:text-brand-900"
              @click="goEdit(resume.id)"
            >
              {{ t('resumes.edit') }}
            </button>
          </template>
          <button
            type="button"
            class="font-medium text-red-600 hover:text-red-800"
            :disabled="deleteResume.isPending.value"
            @click="onDelete(resume.id)"
          >
            {{ t('resumes.delete') }}
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>
