<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { JobsPageSchema, type Job, type JobsPage } from '@/shared/api/schemas';
import { useJobFilters } from '@/modules/jobs/composables/useJobFilters';
import { useApplyToJob } from '@/modules/applications/composables/useApplyToJob';
import { useResumes } from '@/modules/resumes/composables/useResumes';

const { t } = useI18n();
const { state, queryParams, reset, toggleStack } = useJobFilters();

const QUICK_STACK_TAGS = ['php', 'laravel', 'vue', 'typescript', 'python', 'node', 'react', 'go'];

async function fetchJobs(matchOnly: boolean, params: Record<string, string>): Promise<JobsPage> {
  const endpoint = matchOnly ? '/api/jobs/matching' : '/api/jobs';
  const { data } = await api.get(endpoint, { params });
  return JobsPageSchema.parse(data);
}

const queryKey = computed(() => ['jobs', state.matchOnly, queryParams.value] as const);

const { data, isLoading, error, refetch, isFetching } = useQuery({
  queryKey,
  queryFn: () => fetchJobs(state.matchOnly, queryParams.value),
});

const applyMutation = useApplyToJob();
const applyingJobId = ref<number | null>(null);
const appliedJobIds = ref<Set<number>>(new Set());
const alreadyAppliedJobIds = ref<Set<number>>(new Set());

const resumes = useResumes();
const selectedResumeId = ref<number | null>(null);

async function onApply(job: Job): Promise<void> {
  applyingJobId.value = job.id;
  const externalUrl = job.sources?.[0]?.external_url ?? null;

  try {
    await applyMutation.mutateAsync({
      jobId: job.id,
      source: 'feed',
      resumeId: selectedResumeId.value ?? undefined,
    });
    appliedJobIds.value.add(job.id);
    if (externalUrl) window.open(externalUrl, '_blank', 'noopener,noreferrer');
  } catch (e) {
    const err = e as { kind: string };
    if (err.kind === 'duplicate') {
      alreadyAppliedJobIds.value.add(job.id);
      if (externalUrl) window.open(externalUrl, '_blank', 'noopener,noreferrer');
    }
  } finally {
    applyingJobId.value = null;
  }
}

function applyLabel(jobId: number): string {
  if (applyingJobId.value === jobId) return t('jobs.applying');
  if (appliedJobIds.value.has(jobId)) return t('jobs.applied');
  if (alreadyAppliedJobIds.value.has(jobId)) return t('jobs.already_applied');
  return t('jobs.apply');
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-ink-900">{{ t('nav.jobs') }}</h1>
        <p class="mt-1 text-sm text-ink-500">
          {{ data?.data.length ?? 0 }}
          {{ (data?.data.length ?? 0) === 1 ? 'vaga encontrada' : 'vagas encontradas' }}
        </p>
      </div>

      <label
        class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-ink-200 bg-white px-3 py-1.5 text-sm text-ink-700 hover:border-brand-300"
      >
        <input
          v-model="state.matchOnly"
          type="checkbox"
          class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
        />
        {{ t('jobs.filters.matchOnly') }}
      </label>
    </header>

    <!-- Resume selector -->
    <section
      v-if="resumes.data.value && resumes.data.value.data.length > 0"
      class="card flex flex-wrap items-center gap-3 p-4"
    >
      <label class="text-sm font-medium text-ink-700">{{ t('jobs.use_resume') }}</label>
      <select v-model="selectedResumeId" class="input max-w-xs">
        <option :value="null">{{ t('jobs.no_resume') }}</option>
        <option
          v-for="resume in resumes.data.value.data"
          :key="resume.id"
          :value="resume.id"
        >
          {{ resume.title }}{{ resume.is_pdf_upload ? ' (PDF)' : '' }}
        </option>
      </select>
    </section>

    <!-- Filters -->
    <section class="card space-y-4 p-5">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <div class="md:col-span-2">
          <div class="relative">
            <svg
              class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z"
              />
            </svg>
            <input
              v-model="state.q"
              type="search"
              :placeholder="t('jobs.filters.search_placeholder')"
              class="input pl-9"
              @keyup.enter="refetch()"
            />
          </div>
        </div>

        <select v-model="state.modality" class="input">
          <option value="">{{ t('jobs.filters.any_modality') }}</option>
          <option value="remote">{{ t('modality.remote') }}</option>
          <option value="hybrid">{{ t('modality.hybrid') }}</option>
          <option value="onsite">{{ t('modality.onsite') }}</option>
        </select>

        <select v-model="state.seniority" class="input">
          <option value="">{{ t('jobs.filters.any_seniority') }}</option>
          <option value="intern">{{ t('seniority.intern') }}</option>
          <option value="junior">{{ t('seniority.junior') }}</option>
          <option value="mid">{{ t('seniority.mid') }}</option>
          <option value="senior">{{ t('seniority.senior') }}</option>
          <option value="staff">{{ t('seniority.staff') }}</option>
          <option value="principal">{{ t('seniority.principal') }}</option>
        </select>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          v-for="tag in QUICK_STACK_TAGS"
          :key="tag"
          type="button"
          class="rounded-full border px-3 py-1 text-xs font-medium transition"
          :class="
            state.stack.includes(tag)
              ? 'border-brand-600 bg-brand-600 text-white shadow-soft'
              : 'border-ink-200 bg-white text-ink-700 hover:border-brand-300 hover:text-brand-700'
          "
          @click="toggleStack(tag)"
        >
          {{ tag }}
        </button>

        <button
          v-if="state.q || state.modality || state.seniority || state.stack.length"
          type="button"
          class="ml-auto text-xs font-medium text-ink-500 hover:text-ink-900"
          @click="reset"
        >
          {{ t('jobs.filters.reset') }}
        </button>
      </div>
    </section>

    <p v-if="isLoading" class="text-ink-500">{{ t('jobs.loading') }}</p>
    <p v-else-if="error" class="text-red-600">{{ t('jobs.error') }}</p>

    <div
      v-else-if="data && data.data.length === 0"
      class="card p-10 text-center text-ink-500"
    >
      {{ state.matchOnly ? t('jobs.no_matches') : t('jobs.no_results') }}
    </div>

    <ul v-else class="space-y-3">
      <li
        v-for="job in data?.data"
        :key="job.id"
        class="card flex gap-4 p-5 transition hover:border-brand-200 hover:shadow-card"
      >
        <div
          class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-ink-100 text-sm font-semibold text-ink-500"
        >
          <img
            v-if="job.company?.logo_url"
            :src="job.company.logo_url"
            class="h-full w-full object-cover"
            :alt="job.company.name"
          />
          <span v-else>{{ (job.company?.name ?? '?')[0]?.toUpperCase() }}</span>
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <h2 class="truncate font-semibold text-ink-900">{{ job.title }}</h2>
              <p class="text-sm text-ink-500">
                {{ job.company?.name ?? '—' }} · {{ job.location ?? '—' }}
                <span v-if="job.modality"> · {{ t(`modality.${job.modality}`) }}</span>
                <span v-if="job.seniority"> · {{ t(`seniority.${job.seniority}`) }}</span>
              </p>
            </div>
            <span
              v-if="typeof job.match_score === 'number' && job.match_score > 0"
              class="chip shrink-0 bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200"
              :title="job.matched_stack?.join(', ')"
            >
              {{ t('jobs.match_score', { n: job.match_score }) }}
            </span>
          </div>
          <div class="mt-3 flex flex-wrap gap-1">
            <span
              v-for="tag in job.stack.slice(0, 8)"
              :key="tag"
              class="rounded-full px-2.5 py-0.5 text-xs font-medium"
              :class="
                job.matched_stack?.includes(tag.toLowerCase())
                  ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                  : 'bg-ink-100 text-ink-600'
              "
            >
              {{ tag }}
            </span>
          </div>
          <div class="mt-4 flex justify-end">
            <button
              type="button"
              class="btn !px-4 !py-1.5 text-sm"
              :class="
                appliedJobIds.has(job.id) || alreadyAppliedJobIds.has(job.id)
                  ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                  : 'bg-brand-600 text-white hover:bg-brand-700 shadow-soft'
              "
              :disabled="applyingJobId === job.id || appliedJobIds.has(job.id)"
              @click="onApply(job)"
            >
              {{ applyLabel(job.id) }}
            </button>
          </div>
        </div>
      </li>
    </ul>

    <p v-if="isFetching && !isLoading" class="text-xs text-ink-400">{{ t('jobs.loading') }}</p>
  </div>
</template>
