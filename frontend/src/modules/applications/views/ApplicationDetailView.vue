<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import type { ApplicationStatus } from '@/shared/api/schemas';
import { useApplicationDetail } from '@/modules/applications/composables/useApplicationDetail';
import { useChangeApplicationStatus } from '@/modules/applications/composables/useChangeApplicationStatus';
import { useAttachments } from '@/modules/applications/composables/useAttachments';
import { useResumes } from '@/modules/resumes/composables/useResumes';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const applicationId = computed(() => Number(route.params.id));
const { detail, updateNotes } = useApplicationDetail(applicationId);
const changeStatus = useChangeApplicationStatus();
const { list: attachments, upload: uploadAttachment, remove: removeAttachment } = useAttachments(applicationId);

const uploadError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

async function onFileChange(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  uploadError.value = null;
  try {
    await uploadAttachment.mutateAsync(file);
    input.value = '';
  } catch {
    uploadError.value = t('applications.upload_failed');
  }
}

async function onRemoveAttachment(id: number): Promise<void> {
  if (!window.confirm(t('applications.confirm_delete_attachment'))) return;
  await removeAttachment.mutateAsync(id);
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

const notesInput = ref('');
const expectedSalaryInput = ref<number | null>(null);
const resumeIdInput = ref<number | null>(null);
const resumeMessage = ref<string | null>(null);
const saveMessage = ref<string | null>(null);
const statusError = ref<string | null>(null);

const resumes = useResumes();

watch(
  () => detail.data.value,
  (app) => {
    if (app) {
      notesInput.value = app.notes ?? '';
      expectedSalaryInput.value = app.expected_salary;
      resumeIdInput.value = app.resume_id ?? null;
    }
  },
  { immediate: true }
);

async function onChangeResume(): Promise<void> {
  resumeMessage.value = null;
  try {
    await updateNotes.mutateAsync({ resumeId: resumeIdInput.value });
    resumeMessage.value = t('applications.resume_saved');
  } catch {
    resumeMessage.value = t('applications.resume_save_failed');
  }
}

const ALLOWED_TRANSITIONS: Record<ApplicationStatus, ApplicationStatus[]> = {
  applied: ['screening', 'rejected', 'withdrawn'],
  screening: ['assessment', 'interview_hr', 'rejected', 'withdrawn'],
  assessment: ['interview_hr', 'interview_tech', 'rejected', 'withdrawn'],
  interview_hr: ['interview_tech', 'offer', 'rejected', 'withdrawn'],
  interview_tech: ['offer', 'rejected', 'withdrawn'],
  offer: ['accepted', 'rejected', 'withdrawn'],
  accepted: [],
  rejected: [],
  withdrawn: [],
};

const nextStatuses = computed<ApplicationStatus[]>(() => {
  const current = detail.data.value?.status;
  return current ? ALLOWED_TRANSITIONS[current] : [];
});

async function onChangeStatus(to: ApplicationStatus): Promise<void> {
  if (!detail.data.value) return;
  statusError.value = null;
  try {
    await changeStatus.mutateAsync({ applicationId: applicationId.value, status: to });
    detail.refetch();
  } catch (e) {
    const err = e as { kind: string; message: string };
    statusError.value =
      err.kind === 'invalid_transition' ? t('applications.invalid_transition') : err.message;
  }
}

async function onSaveNotes(): Promise<void> {
  saveMessage.value = null;
  try {
    await updateNotes.mutateAsync({
      notes: notesInput.value,
      expectedSalary: expectedSalaryInput.value ?? undefined,
    });
    saveMessage.value = t('applications.notes_saved');
  } catch {
    saveMessage.value = t('applications.notes_save_failed');
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString();
}

function goBack(): void {
  router.push({ name: 'applications' });
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <button
      type="button"
      class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-900"
      @click="goBack"
    >
      <span aria-hidden="true">←</span> {{ t('applications.back') }}
    </button>

    <p v-if="detail.isLoading.value" class="text-ink-500">{{ t('jobs.loading') }}</p>
    <p v-else-if="detail.error.value" class="text-red-600">{{ t('applications.load_failed') }}</p>

    <template v-else-if="detail.data.value">
      <header class="card p-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink-900">
          {{ detail.data.value.job?.title ?? '—' }}
        </h1>
        <p class="mt-1 text-ink-600">
          {{ detail.data.value.job?.company?.name ?? '—' }}
          <span v-if="detail.data.value.job?.location"> · {{ detail.data.value.job.location }}</span>
        </p>
        <p class="mt-2 text-xs text-ink-400">
          {{ t('applications.applied_at', { date: formatDate(detail.data.value.applied_at) }) }}
        </p>
        <p class="mt-4">
          <span class="chip-brand">
            {{ t(`applications.status.${detail.data.value.status}`) }}
          </span>
        </p>
      </header>

      <section v-if="nextStatuses.length" class="card p-6">
        <h2 class="mb-3 font-semibold text-ink-900">{{ t('applications.advance') }}</h2>
        <p
          v-if="statusError"
          class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {{ statusError }}
        </p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in nextStatuses"
            :key="status"
            type="button"
            class="btn-secondary !px-3 !py-1.5 text-sm hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700"
            :disabled="changeStatus.isPending.value"
            @click="onChangeStatus(status)"
          >
            → {{ t(`applications.status.${status}`) }}
          </button>
        </div>
      </section>

      <section class="card p-6">
        <h2 class="mb-3 font-semibold text-ink-900">{{ t('applications.notes') }}</h2>
        <textarea
          v-model="notesInput"
          rows="5"
          class="input"
          :placeholder="t('applications.notes_placeholder')"
        />
        <label class="mt-4 block">
          <span class="label">{{ t('applications.expected_salary') }}</span>
          <input v-model.number="expectedSalaryInput" type="number" min="0" class="input mt-1.5 w-40" />
        </label>
        <div class="mt-4 flex items-center gap-3">
          <button
            type="button"
            class="btn-primary"
            :disabled="updateNotes.isPending.value"
            @click="onSaveNotes"
          >
            {{ t('profile.save') }}
          </button>
          <span v-if="saveMessage" class="text-xs text-ink-500">{{ saveMessage }}</span>
        </div>
      </section>

      <section class="card p-6">
        <h2 class="mb-3 font-semibold text-ink-900">{{ t('applications.resume') }}</h2>
        <div
          v-if="resumes.data.value && resumes.data.value.data.length === 0"
          class="text-sm text-ink-500"
        >
          {{ t('applications.no_resumes_yet') }}
        </div>
        <div v-else class="flex flex-wrap items-center gap-3">
          <select v-model="resumeIdInput" class="input min-w-[220px] flex-1">
            <option :value="null">{{ t('applications.no_resume') }}</option>
            <option
              v-for="resume in resumes.data.value?.data ?? []"
              :key="resume.id"
              :value="resume.id"
            >
              {{ resume.title }}{{ resume.is_pdf_upload ? ' (PDF)' : '' }}
            </option>
          </select>
          <button
            type="button"
            class="btn-primary"
            :disabled="
              updateNotes.isPending.value ||
              resumeIdInput === (detail.data.value?.resume_id ?? null)
            "
            @click="onChangeResume"
          >
            {{ t('profile.save') }}
          </button>
          <span v-if="resumeMessage" class="text-xs text-ink-500">{{ resumeMessage }}</span>
        </div>
      </section>

      <section class="card p-6">
        <h2 class="mb-3 font-semibold text-ink-900">{{ t('applications.attachments') }}</h2>
        <p
          v-if="uploadError"
          class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {{ uploadError }}
        </p>

        <div class="mb-3 flex items-center gap-3">
          <input
            ref="fileInput"
            type="file"
            accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx"
            class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100"
            :disabled="uploadAttachment.isPending.value"
            @change="onFileChange"
          />
          <span v-if="uploadAttachment.isPending.value" class="text-xs text-ink-500">
            {{ t('applications.uploading') }}
          </span>
        </div>

        <ul v-if="attachments.data.value && attachments.data.value.length > 0" class="space-y-2">
          <li
            v-for="att in attachments.data.value"
            :key="att.id"
            class="flex items-center justify-between rounded-lg border border-ink-200 bg-ink-50/60 px-3 py-2"
          >
            <div class="min-w-0 flex-1">
              <a
                :href="att.url"
                target="_blank"
                rel="noopener noreferrer"
                class="block truncate text-sm font-medium text-brand-700 hover:underline"
              >
                {{ att.file_name }}
              </a>
              <p class="text-xs text-ink-400">{{ formatBytes(att.size) }}</p>
            </div>
            <button
              type="button"
              class="ml-3 text-xs font-medium text-red-600 hover:text-red-800"
              @click="onRemoveAttachment(att.id)"
            >
              {{ t('applications.remove') }}
            </button>
          </li>
        </ul>
        <p v-else class="text-sm text-ink-500">{{ t('applications.no_attachments') }}</p>
      </section>

      <section class="card p-6">
        <h2 class="mb-3 font-semibold text-ink-900">{{ t('applications.timeline') }}</h2>
        <p
          v-if="!detail.data.value.events || detail.data.value.events.length === 0"
          class="text-sm text-ink-500"
        >
          {{ t('applications.no_events') }}
        </p>
        <ol v-else class="space-y-4 border-l-2 border-ink-200 pl-5">
          <li
            v-for="ev in [...detail.data.value.events].sort((a, b) =>
              b.occurred_at.localeCompare(a.occurred_at)
            )"
            :key="ev.id"
            class="relative"
          >
            <span
              class="absolute -left-[27px] top-1 h-3.5 w-3.5 rounded-full border-2 border-white bg-brand-500 shadow-soft"
            ></span>
            <p class="text-xs text-ink-400">{{ formatDate(ev.occurred_at) }}</p>
            <p class="text-sm text-ink-800">
              <template v-if="ev.event_type === 'status_changed' && ev.payload">
                {{ t(`applications.status.${String(ev.payload.from)}`) }}
                <span class="text-ink-400">→</span>
                <strong>{{ t(`applications.status.${String(ev.payload.to)}`) }}</strong>
                <span v-if="ev.payload.note" class="mt-1 block text-xs italic text-ink-500">
                  {{ ev.payload.note }}
                </span>
              </template>
              <template v-else>{{ ev.event_type }}</template>
            </p>
          </li>
        </ol>
      </section>
    </template>
  </div>
</template>
