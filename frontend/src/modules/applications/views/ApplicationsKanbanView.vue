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

const STATUS_ACCENT: Record<ApplicationStatus, string> = {
  applied: 'bg-slate-400',
  screening: 'bg-teal-400',
  assessment: 'bg-pink-400',
  interview_hr: 'bg-teal-400',
  interview_tech: 'bg-orange-500',
  offer: 'bg-amber-400',
  accepted: 'bg-emerald-500',
  rejected: 'bg-red-400',
  withdrawn: 'bg-ink-300',
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
  router.push({ name: 'application-detail', params: { id: applicationId } }).catch(() => {
    /* route may not exist during transition */
  });
}
</script>

<template>
  <div>
    <header class="mb-6">
      <h1 class="text-3xl font-bold tracking-tight text-ink-900">{{ t('nav.applications') }}</h1>
      <p class="mt-1 text-sm text-ink-500">
        {{ applications?.length ?? 0 }}
        {{ (applications?.length ?? 0) === 1 ? 'candidatura' : 'candidaturas' }}
      </p>
    </header>

    <p
      v-if="errorMessage"
      class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      role="alert"
    >
      {{ errorMessage }}
    </p>

    <p v-if="isLoading" class="text-ink-500">{{ t('jobs.loading') }}</p>

    <div v-else class="flex gap-4 overflow-x-auto pb-4">
      <div
        v-for="status in COLUMNS"
        :key="status"
        class="min-w-[280px] rounded-xl border border-ink-200 bg-ink-100/60 p-3 transition"
        :class="
          dragOverStatus === status
            ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-300'
            : ''
        "
        @dragover="onDragOver($event, status)"
        @dragleave="onDragLeave(status)"
        @drop="onDrop($event, status)"
      >
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-ink-800">
          <span class="h-2 w-2 rounded-full" :class="STATUS_ACCENT[status]"></span>
          {{ t(`applications.status.${status}`) }}
          <span class="ml-auto rounded-full bg-white px-2 py-0.5 text-xs font-medium text-ink-500">
            {{ grouped[status].length }}
          </span>
        </h2>

        <div class="space-y-2">
          <div
            v-for="app in grouped[status]"
            :key="app.id"
            class="cursor-grab rounded-lg border border-ink-200 bg-white p-3 shadow-soft transition hover:border-brand-300 hover:shadow-card active:cursor-grabbing"
            :class="draggingId === app.id ? 'opacity-50' : ''"
            draggable="true"
            @dragstart="onDragStart($event, app)"
            @dragend="onDragEnd"
            @click="openDetail(app.id)"
          >
            <p class="text-sm font-semibold text-ink-900">{{ app.job?.title ?? '—' }}</p>
            <p class="mt-0.5 text-xs text-ink-500">{{ app.job?.company?.name ?? '—' }}</p>
          </div>

          <p
            v-if="grouped[status].length === 0"
            class="rounded-lg border border-dashed border-ink-300 px-3 py-4 text-center text-xs text-ink-400"
          >
            —
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
