<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import type { ApplicationStatus } from '@/shared/api/schemas';
import { useApplicationDetail } from '@/modules/applications/composables/useApplicationDetail';
import { useChangeApplicationStatus } from '@/modules/applications/composables/useChangeApplicationStatus';
import { useAttachments } from '@/modules/applications/composables/useAttachments';
import { useResumes } from '@/modules/resumes/composables/useResumes';
import { useKanbanConfig, type ColumnConfig } from '@/modules/applications/composables/useKanbanConfig';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const applicationId = computed(() => Number(route.params.id));
const { detail, updateNotes } = useApplicationDetail(applicationId);
const changeStatus = useChangeApplicationStatus();
const { list: attachments, upload: uploadAttachment, remove: removeAttachment } = useAttachments(applicationId);
const { columns, visibleColumns, styleFor, isCustom } = useKanbanConfig();

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
const saveMessage = ref<string | null>(null);
const statusError = ref<string | null>(null);

const resumes = useResumes();

function colLabel(col: ColumnConfig): string {
  if (col.label) return col.label;
  if (isCustom(col.status)) return col.status;
  return t(`applications.status.${col.status}`);
}

function statusLabel(status: string): string {
  const col = columns.find(c => c.status === status);
  if (col) return colLabel(col);
  return t(`applications.status.${status}`);
}

function statusBadgeStyle(status: string): Record<string, string> {
  const col = columns.find(c => c.status === status);
  if (!col) return {};
  const s = styleFor(col);
  return { backgroundColor: s.bg, color: s.text, borderColor: s.border };
}

function statusAccent(status: string): string {
  const col = columns.find(c => c.status === status);
  return col?.color ?? '#64748b';
}

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
  if (!current) return [];
  const allowed = ALLOWED_TRANSITIONS[current] ?? [];
  const visible = new Set(visibleColumns().map(c => c.status));
  return allowed.filter(s => visible.has(s));
});

const progressStages = computed(() => {
  const current = detail.data.value?.status;
  if (!current) return [];
  const vis = visibleColumns().filter(c => c.status !== 'withdrawn');
  const currentIdx = vis.findIndex(c => c.status === current);
  const isTerminal = current === 'rejected' || current === 'withdrawn';
  return vis.map((col, idx) => ({
    col,
    label: colLabel(col),
    isCurrent: col.status === current,
    isPast: !isTerminal && currentIdx >= 0 && idx < currentIdx,
  }));
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

async function onSave(): Promise<void> {
  saveMessage.value = null;
  try {
    await updateNotes.mutateAsync({
      notes: notesInput.value,
      expectedSalary: expectedSalaryInput.value ?? undefined,
      resumeId: resumeIdInput.value,
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
  <div class="mx-auto max-w-3xl lg:flex lg:h-full lg:flex-col">
    <div class="mb-3 flex items-center gap-3 lg:mb-2">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition hover:text-brand-700"
        @click="goBack"
      >
        <svg
          class="h-4 w-4"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"
          />
        </svg>
        {{ t('applications.back') }}
      </button>
    </div>

    <p
      v-if="detail.isLoading.value"
      class="text-ink-500"
    >
      {{ t('jobs.loading') }}
    </p>
    <p
      v-else-if="detail.error.value"
      class="text-red-600"
    >
      {{ t('applications.load_failed') }}
    </p>

    <div
      v-else-if="detail.data.value"
      class="space-y-4 lg:min-h-0 lg:flex-1 lg:overflow-y-auto"
    >
      <!-- Header -->
      <header class="card overflow-hidden border-l-4 border-brand-500">
        <div class="p-5">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <h1 class="text-xl font-bold tracking-tight text-ink-900">
                {{ detail.data.value.job?.title ?? detail.data.value.manual_title ?? '—' }}
              </h1>
              <p class="mt-1 text-sm text-ink-600">
                {{ detail.data.value.job?.company?.name ?? detail.data.value.manual_company ?? '—' }}
                <span v-if="detail.data.value.job?.location"> · {{ detail.data.value.job.location }}</span>
              </p>
              <p class="mt-1.5 text-xs text-ink-400">
                {{ t('applications.applied_at', { date: formatDate(detail.data.value.applied_at) }) }}
              </p>
            </div>
            <span
              class="shrink-0 rounded-lg border px-3 py-1.5 text-xs font-semibold"
              :style="statusBadgeStyle(detail.data.value.status)"
            >
              {{ statusLabel(detail.data.value.status) }}
            </span>
          </div>
        </div>

        <div
          v-if="progressStages.length > 1"
          class="border-t border-ink-100 bg-ink-50/50 px-5 py-3"
        >
          <div class="flex items-center">
            <template
              v-for="(step, idx) in progressStages"
              :key="step.col.status"
            >
              <div
                v-if="idx > 0"
                class="h-0.5 flex-1 transition-colors"
                :class="step.isPast || step.isCurrent ? 'bg-brand-400' : 'bg-ink-200'"
              />
              <div class="flex flex-col items-center gap-1">
                <div
                  class="h-3 w-3 rounded-full transition"
                  :class="step.isCurrent ? 'ring-2 ring-brand-200 ring-offset-1' : ''"
                  :style="{ backgroundColor: step.isPast || step.isCurrent ? styleFor(step.col).accent : '#cbd5e1' }"
                />
                <span
                  class="hidden max-w-[72px] truncate text-center text-[10px] leading-tight sm:block"
                  :class="step.isCurrent ? 'font-semibold text-ink-900' : 'text-ink-400'"
                >
                  {{ step.label }}
                </span>
              </div>
            </template>
          </div>
        </div>
      </header>

      <!-- Advance status -->
      <section
        v-if="nextStatuses.length"
        class="card border-l-4 border-brand-500 p-5"
      >
        <h2 class="mb-3 font-semibold text-ink-900">
          {{ t('applications.advance') }}
        </h2>
        <p
          v-if="statusError"
          class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {{ statusError }}
        </p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in nextStatuses"
            :key="status"
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-sm font-medium text-ink-700 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700 hover:shadow-soft disabled:opacity-60"
            :disabled="changeStatus.isPending.value"
            @click="onChangeStatus(status)"
          >
            <span
              class="h-2.5 w-2.5 shrink-0 rounded-full"
              :style="{ backgroundColor: statusAccent(status) }"
            />
            {{ statusLabel(status) }}
          </button>
        </div>
      </section>

      <!-- Notes, salary & resume -->
      <section class="card border-l-4 border-brand-500 p-5">
        <h2 class="mb-3 font-semibold text-ink-900">
          {{ t('applications.notes') }}
        </h2>
        <textarea
          v-model="notesInput"
          rows="5"
          class="input"
          :placeholder="t('applications.notes_placeholder')"
        />

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="label">{{ t('applications.expected_salary') }}</span>
            <input
              v-model.number="expectedSalaryInput"
              type="number"
              min="0"
              class="input mt-1.5"
            >
          </label>

          <div>
            <span class="label">{{ t('applications.resume') }}</span>
            <p
              v-if="resumes.data.value && resumes.data.value.data.length === 0"
              class="mt-1.5 text-sm text-ink-500"
            >
              {{ t('applications.no_resumes_yet') }}
            </p>
            <select
              v-else
              v-model="resumeIdInput"
              class="input mt-1.5"
            >
              <option :value="null">
                {{ t('applications.no_resume') }}
              </option>
              <option
                v-for="resume in resumes.data.value?.data ?? []"
                :key="resume.id"
                :value="resume.id"
              >
                {{ resume.title }}{{ resume.is_pdf_upload ? ' (PDF)' : '' }}
              </option>
            </select>
          </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
          <button
            type="button"
            class="btn-primary"
            :disabled="updateNotes.isPending.value"
            @click="onSave"
          >
            {{ t('profile.save') }}
          </button>
          <span
            v-if="saveMessage"
            class="text-xs text-brand-600"
          >{{ saveMessage }}</span>
        </div>
      </section>

      <!-- Attachments -->
      <section class="card border-l-4 border-brand-500 p-5">
        <h2 class="mb-3 font-semibold text-ink-900">
          {{ t('applications.attachments') }}
        </h2>
        <p
          v-if="uploadError"
          class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
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
          >
          <span
            v-if="uploadAttachment.isPending.value"
            class="text-xs text-ink-500"
          >
            {{ t('applications.uploading') }}
          </span>
        </div>

        <ul
          v-if="attachments.data.value && attachments.data.value.length > 0"
          class="space-y-2"
        >
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
              <p class="text-xs text-ink-400">
                {{ formatBytes(att.size) }}
              </p>
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
        <p
          v-else
          class="text-sm text-ink-500"
        >
          {{ t('applications.no_attachments') }}
        </p>
      </section>

      <!-- Timeline -->
      <section class="card border-l-4 border-brand-500 p-5">
        <h2 class="mb-3 font-semibold text-ink-900">
          {{ t('applications.timeline') }}
        </h2>
        <p
          v-if="!detail.data.value.events || detail.data.value.events.length === 0"
          class="text-sm text-ink-500"
        >
          {{ t('applications.no_events') }}
        </p>
        <div
          v-else
          class="-mx-5 overflow-x-auto px-5 pb-1"
        >
          <div class="flex min-w-min items-start">
            <template
              v-for="(ev, idx) in [...detail.data.value.events].sort((a, b) =>
                a.occurred_at.localeCompare(b.occurred_at)
              )"
              :key="ev.id"
            >
              <div
                v-if="idx > 0"
                class="mt-[6px] h-0.5 w-10 shrink-0 bg-brand-200"
              />
              <div class="flex shrink-0 flex-col items-center text-center">
                <div class="h-3.5 w-3.5 shrink-0 rounded-full bg-brand-500 shadow-soft" />
                <div class="mt-1.5 max-w-[110px]">
                  <p class="text-[10px] leading-tight text-ink-400">
                    {{ formatDate(ev.occurred_at) }}
                  </p>
                  <p class="mt-0.5 text-xs leading-snug text-ink-800">
                    <template v-if="ev.event_type === 'status_changed' && ev.payload">
                      {{ statusLabel(String(ev.payload.from)) }}
                      <span class="text-ink-400">→</span>
                      <strong>{{ statusLabel(String(ev.payload.to)) }}</strong>
                    </template>
                    <template v-else>
                      {{ ev.event_type }}
                    </template>
                  </p>
                  <p
                    v-if="ev.event_type === 'status_changed' && ev.payload?.note"
                    class="mt-0.5 truncate text-[10px] italic text-ink-500"
                    :title="String(ev.payload.note)"
                  >
                    {{ ev.payload.note }}
                  </p>
                </div>
              </div>
            </template>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
