<script setup lang="ts">
import { computed, ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { api } from '@/shared/api/client';
import { ApplicationSchema, type Application, type ApplicationStatus } from '@/shared/api/schemas';
import { z } from 'zod';
import { useChangeApplicationStatus } from '@/modules/applications/composables/useChangeApplicationStatus';

const { t } = useI18n();
const router = useRouter();

const COLUMNS: ApplicationStatus[] = [
  'applied',
  'screening',
  'assessment',
  'interview_hr',
  'interview_tech',
  'offer',
  'accepted',
  'rejected',
];

const STATUS_STYLE: Record<ApplicationStatus, { accent: string; bg: string; text: string; border: string }> = {
  applied:        { accent: 'bg-slate-500',   bg: 'bg-slate-50',   text: 'text-slate-700',   border: 'border-slate-200' },
  screening:      { accent: 'bg-teal-500',    bg: 'bg-teal-50',    text: 'text-teal-700',    border: 'border-teal-200' },
  assessment:     { accent: 'bg-pink-500',    bg: 'bg-pink-50',    text: 'text-pink-700',    border: 'border-pink-200' },
  interview_hr:   { accent: 'bg-cyan-500',    bg: 'bg-cyan-50',    text: 'text-cyan-700',    border: 'border-cyan-200' },
  interview_tech: { accent: 'bg-orange-500',  bg: 'bg-orange-50',  text: 'text-orange-700',  border: 'border-orange-200' },
  offer:          { accent: 'bg-amber-500',   bg: 'bg-amber-50',   text: 'text-amber-700',   border: 'border-amber-200' },
  accepted:       { accent: 'bg-emerald-500', bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-200' },
  rejected:       { accent: 'bg-red-500',     bg: 'bg-red-50',     text: 'text-red-700',     border: 'border-red-200' },
  withdrawn:      { accent: 'bg-ink-400',     bg: 'bg-ink-50',     text: 'text-ink-600',     border: 'border-ink-200' },
};

const PageSchema = z.object({ data: z.array(ApplicationSchema) });

async function fetchApplications(): Promise<Application[]> {
  const { data } = await api.get('/api/applications');
  return PageSchema.parse(data).data;
}

const { data: applications, isLoading } = useQuery({
  queryKey: ['applications'],
  queryFn: fetchApplications,
});

const changeStatus = useChangeApplicationStatus();
const draggingId = ref<number | null>(null);
const dragOverStatus = ref<ApplicationStatus | null>(null);
const errorMessage = ref<string | null>(null);

const totalCount = computed(() => applications.value?.length ?? 0);

const grouped = computed<Record<ApplicationStatus, Application[]>>(() => {
  const acc = Object.fromEntries(COLUMNS.map((s) => [s, [] as Application[]])) as Record<
    ApplicationStatus,
    Application[]
  >;
  for (const app of applications.value ?? []) {
    if (acc[app.status]) acc[app.status].push(app);
  }
  return acc;
});

function onDragStart(event: DragEvent, app: Application): void {
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

function onDragOver(event: DragEvent, status: ApplicationStatus): void {
  event.preventDefault();
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
  dragOverStatus.value = status;
}

function onDragLeave(status: ApplicationStatus): void {
  if (dragOverStatus.value === status) dragOverStatus.value = null;
}

async function onDrop(event: DragEvent, status: ApplicationStatus): Promise<void> {
  event.preventDefault();
  dragOverStatus.value = null;

  const id = Number(event.dataTransfer?.getData('text/plain'));
  if (!Number.isFinite(id) || id === 0) return;

  const current = (applications.value ?? []).find((a) => a.id === id);
  if (!current || current.status === status) return;

  errorMessage.value = null;
  try {
    await changeStatus.mutateAsync({ applicationId: id, status });
  } catch (e) {
    const err = e as { kind: string; message: string };
    errorMessage.value = err.kind === 'invalid_transition' ? t('applications.invalid_transition') : err.message;
  }
}

function openDetail(applicationId: number): void {
  router.push({ name: 'application-detail', params: { id: applicationId } }).catch(() => {});
}
</script>

<template>
  <div class="lg:flex lg:h-full lg:flex-col">
    <header class="mb-4 flex items-center justify-between lg:mb-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">{{ t('nav.applications') }}</h1>
        <p class="mt-0.5 text-xs text-ink-500">
          {{ totalCount }} {{ totalCount === 1 ? 'candidatura' : 'candidaturas' }}
        </p>
      </div>
    </header>

    <p
      v-if="errorMessage"
      class="mb-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm font-medium text-red-800"
      role="alert"
    >
      {{ errorMessage }}
    </p>

    <p v-if="isLoading" class="text-ink-500">{{ t('jobs.loading') }}</p>

    <div v-else class="flex gap-3 overflow-x-auto pb-2 lg:min-h-0 lg:flex-1">
      <div
        v-for="status in COLUMNS"
        :key="status"
        class="flex min-w-[220px] flex-col rounded-xl border bg-ink-50 transition lg:flex-1"
        :class="[
          dragOverStatus === status
            ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-300 shadow-card'
            : 'border-ink-300',
        ]"
        @dragover="onDragOver($event, status)"
        @dragleave="onDragLeave(status)"
        @drop="onDrop($event, status)"
      >
        <!-- Column header -->
        <div class="flex items-center gap-2 border-b px-3 py-2.5" :class="[STATUS_STYLE[status].border, STATUS_STYLE[status].bg]">
          <span class="h-2.5 w-2.5 rounded-full" :class="STATUS_STYLE[status].accent"></span>
          <span class="text-xs font-bold uppercase tracking-wide" :class="STATUS_STYLE[status].text">
            {{ t(`applications.status.${status}`) }}
          </span>
          <span
            class="ml-auto grid h-5 w-5 place-items-center rounded-full text-[10px] font-bold"
            :class="[STATUS_STYLE[status].bg, STATUS_STYLE[status].text]"
            style="background: rgba(0,0,0,0.06);"
          >
            {{ grouped[status].length }}
          </span>
        </div>

        <!-- Cards area -->
        <div class="flex-1 space-y-2 overflow-y-auto p-2">
          <div
            v-for="app in grouped[status]"
            :key="app.id"
            class="cursor-grab rounded-lg border border-ink-200 bg-white p-3 shadow-soft transition hover:shadow-card hover:border-ink-300 active:cursor-grabbing"
            :class="draggingId === app.id ? 'opacity-40 scale-95' : ''"
            draggable="true"
            @dragstart="onDragStart($event, app)"
            @dragend="onDragEnd"
            @click="openDetail(app.id)"
          >
            <p class="text-sm font-semibold text-ink-900 leading-snug">{{ app.job?.title ?? '—' }}</p>
            <p class="mt-1 text-xs text-ink-500">{{ app.job?.company?.name ?? '—' }}</p>
          </div>

          <div
            v-if="grouped[status].length === 0"
            class="flex items-center justify-center rounded-lg border border-dashed border-ink-300 px-3 py-6 text-xs text-ink-400"
          >
            Nenhuma
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
