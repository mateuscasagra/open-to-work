<script setup lang="ts">
import { computed, ref, reactive, onMounted, onBeforeUnmount } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { api } from '@/shared/api/client';
import { ApplicationSchema, type Application, type ApplicationStatus } from '@/shared/api/schemas';
import { z } from 'zod';
import { useChangeApplicationStatus } from '@/modules/applications/composables/useChangeApplicationStatus';
import {
  useKanbanConfig, MAX_COLUMNS,
  type ColumnConfig,
} from '@/modules/applications/composables/useKanbanConfig';
import { useResumes } from '@/modules/resumes/composables/useResumes';

const { t } = useI18n();
const router = useRouter();
const qc = useQueryClient();

const {
  columns, visibleColumns, styleFor, rename, setColor,
  moveColumn, resetDefaults, isLocked, isCustom, addColumn, removeColumn,
} = useKanbanConfig();

const PageSchema = z.object({ data: z.array(ApplicationSchema) });

const viewArchived = ref(false);

async function fetchApplications(archived: boolean): Promise<Application[]> {
  const { data } = await api.get('/api/applications', {
    params: archived ? { archived: 1 } : {},
  });
  return PageSchema.parse(data).data;
}

const { data: applications, isLoading } = useQuery({
  queryKey: computed(() => ['applications', viewArchived.value ? 'archived' : 'active'] as const),
  queryFn: () => fetchApplications(viewArchived.value),
});

const changeStatus = useChangeApplicationStatus();
const draggingId = ref<number | null>(null);
const dragOverStatus = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

// Drag-drop só em desktop (lg breakpoint do Tailwind = 1024px).
// HTML5 drag em touch não é confiável, então mobile usa o detail view.
const isDesktop = ref(typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches);
let mql: MediaQueryList | null = null;
function onMqlChange(e: MediaQueryListEvent): void {
  isDesktop.value = e.matches;
}
const dragEnabled = computed(() => isDesktop.value && !viewArchived.value);

const totalCount = computed(() => applications.value?.length ?? 0);

const grouped = computed<Record<string, Application[]>>(() => {
  const acc: Record<string, Application[]> = {};
  for (const col of columns) acc[col.status] = [];
  for (const app of applications.value ?? []) {
    if (acc[app.status]) acc[app.status].push(app);
  }
  return acc;
});

function onDragStart(event: DragEvent, app: Application): void {
  if (!dragEnabled.value) {
    event.preventDefault();
    return;
  }
  draggingId.value = app.id;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(app.id));
  }
}

function onDragEnd(): void {
  draggingId.value = null;
  dragOverStatus.value = null;
}

function onDragOver(event: DragEvent, status: string): void {
  if (!dragEnabled.value || isCustom(status)) return;
  event.preventDefault();
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
  dragOverStatus.value = status;
}

function onDragLeave(status: string): void {
  if (dragOverStatus.value === status) dragOverStatus.value = null;
}

async function onDrop(event: DragEvent, status: string): Promise<void> {
  event.preventDefault();
  dragOverStatus.value = null;
  if (!dragEnabled.value || isCustom(status)) return;
  const id = Number(event.dataTransfer?.getData('text/plain'));
  if (!Number.isFinite(id) || id === 0) return;
  const current = (applications.value ?? []).find((a) => a.id === id);
  if (!current || current.status === status) return;
  errorMessage.value = null;
  try {
    await changeStatus.mutateAsync({ applicationId: id, status: status as ApplicationStatus });
  } catch (e) {
    const err = e as { kind: string; message: string };
    errorMessage.value = err.kind === 'invalid_transition' ? t('applications.invalid_transition') : err.message;
  }
}

function openDetail(applicationId: number): void {
  router.push({ name: 'application-detail', params: { id: applicationId } }).catch(() => {});
}

function appTitle(app: Application): string {
  return app.job?.title ?? app.manual_title ?? '—';
}

function appCompany(app: Application): string {
  return app.job?.company?.name ?? app.manual_company ?? '—';
}

function colLabel(col: ColumnConfig): string {
  if (col.label) return col.label;
  if (isCustom(col.status)) return col.status;
  return t(`applications.status.${col.status}`);
}

// --- Manual application modal ---
const showManualModal = ref(false);
const manualForm = reactive({
  title: '',
  company: '',
  jobUrl: '',
  description: '',
  source: '',
  resumeId: null as number | null,
});
const manualSaving = ref(false);
const manualError = ref('');

const { data: resumesPage } = useResumes();
const resumesList = computed(() => resumesPage.value?.data ?? []);

const SOURCE_OPTIONS = [
  'LinkedIn', 'Indeed', 'Catho', 'Glassdoor', 'Gupy', 'InfoJobs',
  'Vagas.com.br', 'Trampos', 'GitHub Jobs', 'Stack Overflow',
] as const;

const manualMutation = useMutation({
  mutationFn: async () => {
    const { data } = await api.post('/api/applications', {
      manualTitle: manualForm.title,
      manualCompany: manualForm.company || null,
      jobUrl: manualForm.jobUrl || null,
      notes: manualForm.description || null,
      source: manualForm.source || 'manual',
      resumeId: manualForm.resumeId,
    });
    return ApplicationSchema.parse(data);
  },
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['applications'] });
    showManualModal.value = false;
    manualForm.title = '';
    manualForm.company = '';
    manualForm.jobUrl = '';
    manualForm.description = '';
    manualForm.source = '';
    manualForm.resumeId = null;
    manualError.value = '';
  },
  onError: () => {
    manualError.value = t('applications.manual_error');
  },
});

function submitManual(): void {
  manualError.value = '';
  if (!manualForm.title.trim()) {
    manualError.value = t('applications.manual_title_required');
    return;
  }
  manualSaving.value = true;
  manualMutation.mutate(undefined, { onSettled: () => { manualSaving.value = false; } });
}

// --- Stage settings modal ---
const settingsOpen = ref(false);
const editingColumn = ref<string | null>(null);
const editLabelDraft = ref('');

const vFocus = { mounted: (el: HTMLElement) => el.focus() };

function startRename(col: ColumnConfig): void {
  if (isLocked(col.status)) return;
  editingColumn.value = col.status;
  editLabelDraft.value = col.label || (isCustom(col.status) ? col.status : t(`applications.status.${col.status}`));
}

function commitRename(status: string): void {
  const isDefault = !isCustom(status);
  const defaultLabel = isDefault ? t(`applications.status.${status}`) : '';
  const trimmed = editLabelDraft.value.trim();
  rename(status, trimmed === defaultLabel ? '' : trimmed);
  editingColumn.value = null;
}

function onColorInput(status: string, event: Event): void {
  setColor(status, (event.target as HTMLInputElement).value);
}

function addNewStage(): void {
  const label = t('applications.kanban_new_stage');
  const newId = addColumn(label, '#3b82f6');
  if (newId) {
    editingColumn.value = newId;
    editLabelDraft.value = label;
  }
}

// --- Kanban scroll fade ---
const kanbanRef = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);
let resizeObs: ResizeObserver | null = null;

function updateScrollFade(): void {
  const el = kanbanRef.value;
  if (!el) return;
  canScrollLeft.value = el.scrollLeft > 8;
  canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 8;
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    mql = window.matchMedia('(min-width: 1024px)');
    mql.addEventListener('change', onMqlChange);
  }
  const el = kanbanRef.value;
  if (!el) return;
  updateScrollFade();
  resizeObs = new ResizeObserver(updateScrollFade);
  resizeObs.observe(el);
});

onBeforeUnmount(() => {
  resizeObs?.disconnect();
  mql?.removeEventListener('change', onMqlChange);
});

const kanbanMask = computed(() => {
  const l = canScrollLeft.value;
  const r = canScrollRight.value;
  if (!l && !r) return undefined;
  const start = l ? 'transparent, black 48px' : 'black';
  const end = r ? 'black calc(100% - 48px), transparent' : 'black';
  return `linear-gradient(to right, ${start}, ${end})`;
});

function closeSettings(): void {
  settingsOpen.value = false;
  editingColumn.value = null;
}

function canMoveLeft(status: string): boolean {
  const idx = columns.findIndex(c => c.status === status);
  return idx > 0 && !isLocked(columns[idx - 1].status);
}

function canMoveRight(status: string): boolean {
  const idx = columns.findIndex(c => c.status === status);
  return idx < columns.length - 1 && !isLocked(columns[idx + 1].status);
}

</script>

<template>
  <div class="lg:flex lg:h-[calc(100vh-7rem)] lg:flex-col lg:overflow-hidden">
    <!-- Header -->
    <header class="mb-4 flex flex-wrap items-center justify-between gap-3 lg:mb-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
          {{ t('nav.applications') }}
        </h1>
        <p class="mt-0.5 text-xs text-ink-500">
          {{ totalCount }} {{ totalCount === 1 ? 'candidatura' : 'candidaturas' }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-sm font-medium text-ink-700 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700"
          @click="viewArchived = !viewArchived"
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
              stroke-width="1.75"
              d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"
            />
          </svg>
          {{ viewArchived ? t('applications.filter_active') : t('applications.filter_archived') }}
        </button>
        <button
          type="button"
          class="btn-primary !px-4 !py-2 text-sm"
          :disabled="viewArchived"
          @click="showManualModal = true"
        >
          + {{ t('applications.add_manual') }}
        </button>
        <button
          type="button"
          class="btn-secondary !p-2"
          :title="t('applications.kanban_settings')"
          @click="settingsOpen = true"
        >
          <svg
            class="h-5 w-5 text-ink-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826-3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
            />
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
            />
          </svg>
        </button>
      </div>
    </header>

    <p
      v-if="errorMessage"
      class="mb-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm font-medium text-red-800"
      role="alert"
    >
      {{ errorMessage }}
    </p>

    <p
      v-if="isLoading"
      class="text-ink-500"
    >
      {{ t('jobs.loading') }}
    </p>

    <!-- Kanban board -->
    <div
      v-else
      class="lg:min-h-0 lg:flex-1"
    >
      <div
        ref="kanbanRef"
        class="flex flex-col gap-3 pb-2 lg:h-full lg:flex-row lg:overflow-x-auto"
        :style="{ maskImage: kanbanMask, WebkitMaskImage: kanbanMask }"
        @scroll="updateScrollFade"
      >
        <div
          v-for="col in visibleColumns()"
          :key="col.status"
          class="flex flex-col rounded-xl border transition lg:min-w-[280px] lg:flex-1 lg:min-h-0"
          :class="[
            dragOverStatus === col.status
              ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-300 shadow-card'
              : 'border-ink-300 bg-ink-50',
          ]"
          @dragover="onDragOver($event, col.status)"
          @dragleave="onDragLeave(col.status)"
          @drop="onDrop($event, col.status)"
        >
          <!-- Column header -->
          <div
            class="flex items-center gap-2 border-b px-4 py-3"
            :style="{ borderColor: styleFor(col).border, backgroundColor: styleFor(col).bg }"
          >
            <span
              class="h-3 w-3 shrink-0 rounded-full"
              :style="{ backgroundColor: styleFor(col).accent }"
            />
            <span
              class="text-xs font-bold uppercase tracking-wide"
              :style="{ color: styleFor(col).text }"
            >
              {{ colLabel(col) }}
            </span>
            <span
              class="ml-auto grid h-6 w-6 place-items-center rounded-full text-xs font-bold"
              :style="{ color: styleFor(col).text, backgroundColor: 'rgba(0,0,0,0.06)' }"
            >
              {{ grouped[col.status]?.length ?? 0 }}
            </span>
          </div>

          <!-- Cards area -->
          <div class="flex flex-1 flex-col gap-1.5 p-2 lg:overflow-y-auto">
            <div
              v-for="app in grouped[col.status]"
              :key="app.id"
              class="rounded-lg border border-ink-200 bg-white px-3 py-2 shadow-soft transition hover:shadow-card hover:border-ink-300"
              :class="[
                dragEnabled ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer',
                draggingId === app.id ? 'opacity-40 scale-95' : '',
              ]"
              :draggable="dragEnabled"
              @dragstart="onDragStart($event, app)"
              @dragend="onDragEnd"
              @click="openDetail(app.id)"
            >
              <p class="text-sm font-semibold text-ink-900 leading-snug">
                {{ appTitle(app) }}
              </p>
              <p class="mt-0.5 text-xs text-ink-500">
                {{ appCompany(app) }}
              </p>
              <div class="mt-1.5 flex items-center justify-between">
                <span class="text-[11px] text-ink-400">
                  {{ new Date(app.applied_at).toLocaleDateString() }}
                </span>
                <span
                  v-if="app.source === 'manual'"
                  class="rounded-full bg-ink-100 px-1.5 py-0.5 text-[9px] font-medium text-ink-500"
                >
                  manual
                </span>
                <span
                  v-else-if="app.source === 'email'"
                  class="rounded-full bg-blue-50 px-1.5 py-0.5 text-[9px] font-medium text-blue-600"
                >
                  e-mail
                </span>
                <span
                  v-else-if="app.source"
                  class="rounded-full bg-ink-100 px-1.5 py-0.5 text-[9px] font-medium text-ink-500"
                >
                  {{ app.source }}
                </span>
              </div>
            </div>

            <div
              v-if="(grouped[col.status]?.length ?? 0) === 0"
              class="flex flex-1 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-ink-300 px-3 py-8 text-center text-xs text-ink-400"
            >
              <svg
                class="h-6 w-6 text-ink-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="1.5"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                />
              </svg>
              <span class="leading-snug">{{ t('applications.kanban_no_apps_in_stage') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Manual application modal -->
    <Teleport to="body">
      <div
        v-if="showManualModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="showManualModal = false"
      >
        <div class="w-full max-w-lg rounded-2xl border border-ink-200 bg-white shadow-card">
          <div class="border-b border-ink-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-ink-900">
              {{ t('applications.add_manual') }}
            </h2>
          </div>

          <div class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-5">
            <div>
              <label class="label">{{ t('applications.manual_title') }} *</label>
              <input
                v-model="manualForm.title"
                class="input"
                :placeholder="t('applications.manual_title')"
              >
            </div>

            <div>
              <label class="label">{{ t('applications.manual_company') }}</label>
              <input
                v-model="manualForm.company"
                class="input"
                :placeholder="t('applications.manual_company')"
              >
            </div>

            <div>
              <label class="label">{{ t('applications.manual_job_url') }}</label>
              <input
                v-model="manualForm.jobUrl"
                type="url"
                class="input"
                placeholder="https://..."
              >
            </div>

            <div>
              <label class="label">{{ t('applications.manual_description') }}</label>
              <textarea
                v-model="manualForm.description"
                class="input"
                rows="3"
                :placeholder="t('applications.manual_description')"
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">{{ t('applications.manual_source') }}</label>
                <select
                  v-model="manualForm.source"
                  class="input"
                >
                  <option value="">
                    {{ t('applications.manual_source_placeholder') }}
                  </option>
                  <option
                    v-for="src in SOURCE_OPTIONS"
                    :key="src"
                    :value="src"
                  >
                    {{ src }}
                  </option>
                  <option value="manual">
                    {{ t('applications.manual_source_other') }}
                  </option>
                </select>
              </div>

              <div>
                <label class="label">{{ t('applications.manual_resume') }}</label>
                <select
                  v-model="manualForm.resumeId"
                  class="input"
                >
                  <option :value="null">
                    {{ t('applications.manual_resume_placeholder') }}
                  </option>
                  <option
                    v-for="resume in resumesList"
                    :key="resume.id"
                    :value="resume.id"
                  >
                    {{ resume.title }}
                  </option>
                </select>
              </div>
            </div>

            <p
              v-if="manualError"
              class="text-sm text-red-600"
            >
              {{ manualError }}
            </p>
          </div>

          <div class="flex justify-end gap-2 border-t border-ink-100 px-6 py-4">
            <button
              type="button"
              class="btn-secondary"
              @click="showManualModal = false"
            >
              {{ t('applications.manual_cancel') }}
            </button>
            <button
              type="button"
              class="btn-primary"
              :disabled="manualSaving"
              @click="submitManual"
            >
              {{ manualSaving ? '...' : t('applications.manual_save') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Stage settings modal -->
    <Teleport to="body">
      <div
        v-if="settingsOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="closeSettings"
      >
        <div class="w-full max-w-5xl mx-4 rounded-2xl border border-ink-200 bg-white shadow-card">
          <!-- Modal header -->
          <div class="flex items-center justify-between border-b border-ink-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-ink-900">
              {{ t('applications.kanban_settings') }}
            </h2>
            <div class="flex items-center gap-3">
              <button
                type="button"
                class="text-xs text-ink-500 hover:text-ink-700 transition"
                @click="resetDefaults()"
              >
                {{ t('applications.kanban_reset') }}
              </button>
              <button
                type="button"
                class="rounded-lg p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-700 transition"
                @click="closeSettings"
              >
                <svg
                  class="h-5 w-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>

          <!-- Stages row -->
          <div class="px-6 py-6 overflow-x-auto">
            <div class="flex justify-center gap-2.5 min-w-min">
              <div
                v-for="col in visibleColumns()"
                :key="col.status"
                class="flex flex-col items-center rounded-xl border px-3 py-3 min-w-[100px] max-w-[120px] transition relative"
                :class="[
                  isLocked(col.status)
                    ? 'border-ink-100 bg-ink-50/60'
                    : 'border-ink-200 bg-white shadow-soft hover:shadow-card',
                ]"
              >
                <!-- Color dot with native color picker -->
                <div class="mb-2">
                  <label
                    v-if="!isLocked(col.status)"
                    class="relative block cursor-pointer"
                  >
                    <span
                      class="block h-7 w-7 rounded-full border-2 border-white shadow-sm ring-1 ring-ink-200 transition hover:scale-110 hover:ring-ink-300"
                      :style="{ backgroundColor: col.color }"
                    />
                    <input
                      type="color"
                      class="absolute inset-0 h-0 w-0 cursor-pointer opacity-0"
                      :value="col.color"
                      @input="onColorInput(col.status, $event)"
                    >
                  </label>
                  <span
                    v-else
                    class="block h-7 w-7 rounded-full ring-1 ring-ink-200"
                    :style="{ backgroundColor: col.color }"
                  />
                </div>

                <!-- Name -->
                <template v-if="editingColumn === col.status">
                  <input
                    v-model="editLabelDraft"
                    v-focus
                    class="mb-1.5 w-full rounded border border-brand-400 bg-white px-1 py-0.5 text-center text-[11px] font-medium text-ink-800 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    @keyup.enter="commitRename(col.status)"
                    @blur="commitRename(col.status)"
                    @keyup.escape="editingColumn = null"
                  >
                </template>
                <button
                  v-else
                  type="button"
                  class="mb-1.5 w-full text-center text-[11px] font-semibold leading-tight transition"
                  :class="[
                    isLocked(col.status)
                      ? 'text-ink-400 cursor-default'
                      : 'text-ink-700 hover:text-brand-700 cursor-pointer',
                  ]"
                  :disabled="isLocked(col.status)"
                  @click="startRename(col)"
                >
                  {{ colLabel(col) }}
                </button>

                <!-- Lock icon for locked stages -->
                <svg
                  v-if="isLocked(col.status)"
                  class="mb-1 h-3 w-3 text-ink-300"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                  />
                </svg>

                <!-- Arrows in row (left / right) -->
                <div
                  v-if="!isLocked(col.status)"
                  class="flex items-center gap-1"
                >
                  <button
                    type="button"
                    class="rounded p-0.5 text-ink-300 hover:bg-ink-100 hover:text-ink-600 disabled:opacity-20 disabled:hover:bg-transparent disabled:hover:text-ink-300 transition"
                    :disabled="!canMoveLeft(col.status)"
                    @click="moveColumn(col.status, -1)"
                  >
                    <svg
                      class="h-3.5 w-3.5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        d="M15 19l-7-7 7-7"
                      />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="rounded p-0.5 text-ink-300 hover:bg-ink-100 hover:text-ink-600 disabled:opacity-20 disabled:hover:bg-transparent disabled:hover:text-ink-300 transition"
                    :disabled="!canMoveRight(col.status)"
                    @click="moveColumn(col.status, 1)"
                  >
                    <svg
                      class="h-3.5 w-3.5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        d="M9 5l7 7-7 7"
                      />
                    </svg>
                  </button>
                </div>

                <!-- Delete button (any non-locked stage) -->
                <button
                  v-if="!isLocked(col.status)"
                  type="button"
                  class="mt-1.5 rounded p-0.5 text-ink-300 hover:bg-red-50 hover:text-red-500 transition"
                  :title="t('applications.kanban_remove_stage')"
                  @click="removeColumn(col.status)"
                >
                  <svg
                    class="h-3.5 w-3.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                    />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Modal footer -->
          <div class="flex items-center justify-between border-t border-ink-100 px-6 py-3">
            <button
              v-if="columns.length < MAX_COLUMNS"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-ink-300 px-3 py-1.5 text-sm font-medium text-ink-600 transition hover:border-brand-400 hover:text-brand-700 hover:bg-brand-50"
              @click="addNewStage"
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
                  d="M12 4v16m8-8H4"
                />
              </svg>
              {{ t('applications.kanban_add_stage') }}
            </button>
            <span
              v-else
              class="text-xs font-medium text-amber-600"
            >
              {{ t('applications.kanban_max_stages') }}
            </span>
            <span class="text-xs text-ink-400">
              {{ columns.length }}/{{ MAX_COLUMNS }}
            </span>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
