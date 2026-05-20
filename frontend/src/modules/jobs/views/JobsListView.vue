<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { z } from 'zod';
import { api } from '@/shared/api/client';
import { JobsPageSchema, type Job, type JobsPage } from '@/shared/api/schemas';
import { useJobFilters } from '@/modules/jobs/composables/useJobFilters';
import { useApplyToJob, type ApplyError } from '@/modules/applications/composables/useApplyToJob';
import { useResumes } from '@/modules/resumes/composables/useResumes';
import UpgradeModal from '@/modules/subscription/components/UpgradeModal.vue';

const { t, locale } = useI18n();
const { state, queryParams, reset } = useJobFilters();

const page = ref(1);

watch(
  [() => state.q, () => state.modality, () => state.seniority, () => state.country, () => state.matchOnly],
  () => {
    page.value = 1;
    selectedJob.value = null;
  },
);

const CountriesResponse = z.object({ data: z.array(z.string().length(2)) });

const countriesQuery = useQuery({
  queryKey: ['jobs', 'countries'],
  queryFn: async () => {
    const { data } = await api.get('/api/jobs/countries');
    return CountriesResponse.parse(data).data;
  },
  staleTime: 5 * 60 * 1000,
});

function countryLabel(code: string): string {
  try {
    const lang = locale.value.replace('_', '-');
    const dn = new Intl.DisplayNames([lang], { type: 'region' });
    return dn.of(code) ?? code;
  } catch {
    return code;
  }
}

const countryOptions = computed(() =>
  (countriesQuery.data.value ?? [])
    .map((code) => ({ code, label: countryLabel(code) }))
    .sort((a, b) => a.label.localeCompare(b.label, locale.value.replace('_', '-'))),
);

async function fetchJobs(
  matchOnly: boolean,
  params: Record<string, string>,
  pageNum: number,
): Promise<JobsPage> {
  const endpoint = matchOnly ? '/api/jobs/matching' : '/api/jobs';
  const { data } = await api.get(endpoint, { params: { ...params, page: pageNum } });
  return JobsPageSchema.parse(data);
}

const queryKey = computed(
  () => ['jobs', state.matchOnly, queryParams.value, page.value] as const,
);

const { data, error, refetch, isFetching } = useQuery({
  queryKey,
  queryFn: () => fetchJobs(state.matchOnly, queryParams.value, page.value),
  placeholderData: keepPreviousData,
});

const listRef = ref<HTMLElement | null>(null);

const selectedJob = ref<Job | null>(null);

function selectJob(job: Job): void {
  selectedJob.value = job;
}

function closeDetail(): void {
  selectedJob.value = null;
}

const PAGE_WINDOW = 3;

const visiblePages = computed<number[]>(() => {
  const total = data.value?.last_page ?? 1;
  if (total <= 1) return [];
  if (total <= PAGE_WINDOW) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }
  let start = Math.max(1, page.value - 1);
  if (start + PAGE_WINDOW - 1 > total) {
    start = total - PAGE_WINDOW + 1;
  }
  return Array.from({ length: PAGE_WINDOW }, (_, i) => start + i);
});

function goToPage(p: number): void {
  page.value = p;
  selectedJob.value = null;
  listRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

const applyMutation = useApplyToJob();
const applyingJobId = ref<number | null>(null);
const appliedJobIds = ref<Set<number>>(new Set());
const alreadyAppliedJobIds = ref<Set<number>>(new Set());

// Modal de upgrade (aparece quando user free atinge limite mensal).
const quotaModal = ref<{ limit: number; resetAt: string } | null>(null);

const resumes = useResumes();
const selectedResumeId = ref<number | null>(null);

async function onApply(job: Job): Promise<void> {
  applyingJobId.value = job.id;
  const externalUrl = job.sources?.[0]?.external_url ?? null;

  try {
    await applyMutation.mutateAsync({
      jobId: job.id,
      source: job.sources?.[0]?.source ?? 'feed',
      resumeId: selectedResumeId.value ?? undefined,
    });
    appliedJobIds.value.add(job.id);
    if (externalUrl) window.open(externalUrl, '_blank', 'noopener,noreferrer');
  } catch (e) {
    const err = e as ApplyError;
    if (err.kind === 'duplicate') {
      alreadyAppliedJobIds.value.add(job.id);
      if (externalUrl) window.open(externalUrl, '_blank', 'noopener,noreferrer');
    } else if (err.kind === 'quota_exceeded') {
      quotaModal.value = { limit: err.limit, resetAt: err.resetAt };
    }
  } finally {
    applyingJobId.value = null;
  }
}

function isAlreadyApplied(jobId: number): boolean {
  if (alreadyAppliedJobIds.value.has(jobId)) return true;
  return data.value?.data.some((j) => j.id === jobId && j.has_applied) ?? false;
}

function isApplied(jobId: number): boolean {
  return appliedJobIds.value.has(jobId) || isAlreadyApplied(jobId);
}

function applyLabel(jobId: number): string {
  if (applyingJobId.value === jobId) return t('jobs.applying');
  if (appliedJobIds.value.has(jobId)) return t('jobs.applied');
  if (isAlreadyApplied(jobId)) return t('jobs.already_applied');
  return t('jobs.apply');
}
</script>

<template>
  <div class="space-y-4 lg:flex lg:h-[calc(100vh-7rem)] lg:flex-col lg:gap-4 lg:space-y-0">
    <div class="space-y-2 lg:shrink-0">
      <!-- Resume selector + matchOnly toggle -->
      <section class="card flex flex-wrap items-center gap-3 px-3 py-2">
        <template v-if="resumes.data.value && resumes.data.value.data.length > 0">
          <label class="text-sm font-medium text-ink-700">{{ t('jobs.use_resume') }}</label>
          <select
            v-model="selectedResumeId"
            class="input max-w-xs"
          >
            <option :value="null">
              {{ t('jobs.no_resume') }}
            </option>
            <option
              v-for="resume in resumes.data.value.data"
              :key="resume.id"
              :value="resume.id"
            >
              {{ resume.title }}{{ resume.is_pdf_upload ? ' (PDF)' : '' }}
            </option>
          </select>
        </template>

        <label
          class="ml-auto inline-flex cursor-pointer items-center gap-2 rounded-full border border-ink-200 bg-white px-3 py-1.5 text-sm text-ink-700 hover:border-brand-300"
        >
          <input
            v-model="state.matchOnly"
            type="checkbox"
            class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
          >
          {{ t('jobs.filters.matchOnly') }}
        </label>
      </section>

      <!-- Filters -->
      <section class="card space-y-2 p-3">
        <div class="grid grid-cols-1 gap-2 md:grid-cols-4">
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
              >
            </div>
          </div>

          <select
            v-model="state.modality"
            class="input"
          >
            <option value="">
              {{ t('jobs.filters.any_modality') }}
            </option>
            <option value="remote">
              {{ t('modality.remote') }}
            </option>
            <option value="hybrid">
              {{ t('modality.hybrid') }}
            </option>
            <option value="onsite">
              {{ t('modality.onsite') }}
            </option>
          </select>

          <select
            v-if="countryOptions.length > 0"
            v-model="state.country"
            class="input"
          >
            <option value="">
              {{ t('jobs.filters.any_country') }}
            </option>
            <option
              v-for="opt in countryOptions"
              :key="opt.code"
              :value="opt.code"
            >
              {{ opt.label }}
            </option>
          </select>

          <select
            v-model="state.seniority"
            class="input"
          >
            <option value="">
              {{ t('jobs.filters.any_seniority') }}
            </option>
            <option value="intern">
              {{ t('seniority.intern') }}
            </option>
            <option value="junior">
              {{ t('seniority.junior') }}
            </option>
            <option value="mid">
              {{ t('seniority.mid') }}
            </option>
            <option value="senior">
              {{ t('seniority.senior') }}
            </option>
            <option value="staff">
              {{ t('seniority.staff') }}
            </option>
            <option value="principal">
              {{ t('seniority.principal') }}
            </option>
          </select>
        </div>

        <div
          v-if="state.q || state.modality || state.seniority || state.country"
          class="flex justify-end"
        >
          <button
            type="button"
            class="text-xs font-medium text-ink-500 hover:text-ink-900"
            @click="reset"
          >
            {{ t('jobs.filters.reset') }}
          </button>
        </div>
      </section>
    </div>

    <p class="text-xs text-ink-500 lg:shrink-0">
      {{ data?.total ?? 0 }}
      {{ (data?.total ?? 0) === 1 ? 'vaga encontrada' : 'vagas encontradas' }}
    </p>

    <div
      v-if="isFetching"
      class="flex min-h-[60vh] items-center justify-center lg:min-h-0 lg:flex-1"
      role="status"
      :aria-label="t('jobs.loading')"
    >
      <svg
        class="h-7 w-7 animate-spin text-brand-600"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
        />
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        />
      </svg>
    </div>
    <p
      v-else-if="error"
      class="text-red-600"
    >
      {{ t('jobs.error') }}
    </p>

    <div
      v-else-if="data && data.data.length === 0"
      class="card p-10 text-center text-ink-500"
    >
      {{ state.matchOnly ? t('jobs.no_matches') : t('jobs.no_results') }}
    </div>

    <!-- List + side panel -->
    <div
      v-else
      class="lg:flex lg:min-h-0 lg:flex-1 lg:gap-4"
    >
      <div
        :class="selectedJob ? 'hidden lg:flex' : ''"
        class="space-y-3 lg:flex lg:min-h-0 lg:min-w-0 lg:flex-1 lg:flex-col lg:space-y-2"
      >
        <ul
          ref="listRef"
          class="space-y-1 lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:pr-1"
        >
          <li
            v-for="job in data?.data"
            :key="job.id"
            class="card flex gap-2.5 p-2.5 transition hover:border-brand-200 hover:shadow-card"
            :class="selectedJob?.id === job.id ? 'border-brand-300 shadow-card' : ''"
          >
            <div
              class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-ink-100 text-xs font-semibold text-ink-500"
            >
              <img
                v-if="job.company?.logo_url"
                :src="job.company.logo_url"
                class="h-full w-full object-cover"
                :alt="job.company.name"
              >
              <span v-else>{{ (job.company?.name ?? '?')[0]?.toUpperCase() }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <h2 class="truncate text-sm font-semibold text-ink-900">
                    {{ job.title }}
                  </h2>
                  <p class="text-xs text-ink-500">
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
              <div
                v-if="job.stack.length > 0"
                class="mt-1.5 flex flex-wrap gap-1"
              >
                <span
                  v-for="tag in job.stack.slice(0, 8)"
                  :key="tag"
                  class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                  :class="
                    job.matched_stack?.includes(tag.toLowerCase())
                      ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                      : 'bg-ink-100 text-ink-600'
                  "
                >
                  {{ tag }}
                </span>
              </div>
              <div class="mt-2 flex justify-end gap-1.5">
                <button
                  type="button"
                  class="btn-secondary !px-2.5 !py-0.5 text-xs"
                  @click="selectJob(job)"
                >
                  {{ t('jobs.view') }}
                </button>
                <button
                  type="button"
                  class="btn !px-2.5 !py-0.5 text-xs"
                  :class="
                    isApplied(job.id)
                      ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                      : 'bg-brand-600 text-white hover:bg-brand-700 shadow-soft'
                  "
                  :disabled="applyingJobId === job.id || isApplied(job.id)"
                  @click="onApply(job)"
                >
                  {{ applyLabel(job.id) }}
                </button>
              </div>
            </div>
          </li>
        </ul>

        <nav
          v-if="data && data.last_page > 1"
          class="flex flex-wrap items-center justify-end gap-2 lg:shrink-0"
        >
          <button
            v-for="p in visiblePages"
            :key="p"
            type="button"
            class="rounded-lg border px-3 py-1.5 text-sm font-medium transition"
            :class="
              p === page
                ? 'border-brand-600 bg-brand-600 text-white shadow-soft'
                : 'border-ink-200 bg-white text-ink-700 hover:border-brand-300 hover:text-brand-700'
            "
            :disabled="isFetching && p !== page"
            @click="goToPage(p)"
          >
            {{ p }}
          </button>
          <button
            type="button"
            class="btn-secondary !px-4 !py-1.5 text-sm"
            :disabled="page >= data.last_page || isFetching"
            @click="goToPage(page + 1)"
          >
            {{ t('jobs.pagination.next') }}
          </button>
        </nav>
      </div>

      <!-- Detail panel -->
      <aside
        v-if="selectedJob"
        class="lg:flex lg:min-h-0 lg:w-[760px] lg:shrink-0"
      >
        <div class="card flex flex-col p-6 lg:h-full lg:w-full lg:overflow-y-auto">
          <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
              <div
                class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-ink-100 text-sm font-semibold text-ink-500"
              >
                <img
                  v-if="selectedJob.company?.logo_url"
                  :src="selectedJob.company.logo_url"
                  class="h-full w-full object-cover"
                  :alt="selectedJob.company.name"
                >
                <span v-else>{{ (selectedJob.company?.name ?? '?')[0]?.toUpperCase() }}</span>
              </div>
              <div class="min-w-0">
                <p class="text-sm font-medium text-ink-500">
                  {{ selectedJob.company?.name ?? '—' }}
                </p>
                <h2 class="text-lg font-semibold text-ink-900">
                  {{ selectedJob.title }}
                </h2>
              </div>
            </div>
            <button
              type="button"
              class="rounded-lg p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-700"
              :aria-label="t('jobs.detail.close')"
              @click="closeDetail"
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

          <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div v-if="selectedJob.location">
              <dt class="text-xs font-medium text-ink-400">
                {{ t('jobs.detail.location') }}
              </dt>
              <dd class="text-ink-800">
                {{ selectedJob.location }}
              </dd>
            </div>
            <div v-if="selectedJob.modality">
              <dt class="text-xs font-medium text-ink-400">
                {{ t('profile.modality') }}
              </dt>
              <dd class="text-ink-800">
                {{ t(`modality.${selectedJob.modality}`) }}
              </dd>
            </div>
            <div v-if="selectedJob.seniority">
              <dt class="text-xs font-medium text-ink-400">
                {{ t('profile.seniority') }}
              </dt>
              <dd class="text-ink-800">
                {{ t(`seniority.${selectedJob.seniority}`) }}
              </dd>
            </div>
            <div v-if="selectedJob.salary_min || selectedJob.salary_max">
              <dt class="text-xs font-medium text-ink-400">
                {{ t('jobs.detail.salary') }}
              </dt>
              <dd class="text-ink-800">
                <template v-if="selectedJob.salary_min && selectedJob.salary_max">
                  {{ selectedJob.salary_currency ?? '' }}
                  {{ selectedJob.salary_min.toLocaleString() }}
                  – {{ selectedJob.salary_max.toLocaleString() }}
                </template>
                <template v-else-if="selectedJob.salary_min">
                  {{ selectedJob.salary_currency ?? '' }} {{ selectedJob.salary_min.toLocaleString() }}+
                </template>
                <template v-else-if="selectedJob.salary_max">
                  {{ t('jobs.detail.up_to') }} {{ selectedJob.salary_currency ?? '' }} {{ selectedJob.salary_max.toLocaleString() }}
                </template>
              </dd>
            </div>
            <div v-if="selectedJob.posted_at">
              <dt class="text-xs font-medium text-ink-400">
                {{ t('jobs.detail.posted_at') }}
              </dt>
              <dd class="text-ink-800">
                {{ new Date(selectedJob.posted_at).toLocaleDateString() }}
              </dd>
            </div>
            <div v-if="selectedJob.language">
              <dt class="text-xs font-medium text-ink-400">
                {{ t('jobs.filters.language') }}
              </dt>
              <dd class="text-ink-800">
                {{ t(`languages.${selectedJob.language}`) }}
              </dd>
            </div>
          </dl>

          <div
            v-if="selectedJob.stack.length > 0"
            class="mt-4 flex flex-wrap gap-1"
          >
            <span
              v-for="tag in selectedJob.stack"
              :key="tag"
              class="rounded-full px-2.5 py-0.5 text-xs font-medium"
              :class="
                selectedJob.matched_stack?.includes(tag.toLowerCase())
                  ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                  : 'bg-ink-100 text-ink-600'
              "
            >
              {{ tag }}
            </span>
          </div>

          <div class="mt-5">
            <h3 class="text-sm font-semibold text-ink-900">
              {{ t('jobs.detail.description') }}
            </h3>
            <div
              v-if="selectedJob.description_html"
              class="mt-2 space-y-2 text-sm leading-relaxed text-ink-700 [&_a]:text-brand-700 [&_a]:underline [&_li]:ml-5 [&_li]:list-disc [&_p]:my-2 [&_strong]:font-semibold [&_ul]:my-2"
              v-html="selectedJob.description_html"
            />
            <p
              v-else
              class="mt-2 text-sm italic text-ink-400"
            >
              {{ t('jobs.detail.no_description') }}
            </p>
          </div>

          <div class="mt-5 flex justify-end gap-2 border-t border-ink-200 pt-4">
            <button
              type="button"
              class="btn !px-4 !py-1.5 text-sm"
              :class="
                isApplied(selectedJob.id)
                  ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                  : 'bg-brand-600 text-white hover:bg-brand-700 shadow-soft'
              "
              :disabled="applyingJobId === selectedJob.id || isApplied(selectedJob.id)"
              @click="onApply(selectedJob)"
            >
              {{ applyLabel(selectedJob.id) }}
            </button>
          </div>
        </div>
      </aside>
    </div>

    <UpgradeModal
      v-if="quotaModal"
      :limit="quotaModal.limit"
      :reset-at="quotaModal.resetAt"
      @close="quotaModal = null"
    />
  </div>
</template>
