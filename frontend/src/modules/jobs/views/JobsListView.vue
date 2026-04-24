<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { JobsPageSchema, type Job, type JobsPage, type Locale } from '@/shared/api/schemas';
import { useJobFilters } from '@/modules/jobs/composables/useJobFilters';
import { useApplyToJob } from '@/modules/applications/composables/useApplyToJob';
import { useResumes } from '@/modules/resumes/composables/useResumes';
import { useProfile } from '@/modules/profile/composables/useProfile';
import ApplyByEmailModal from '@/modules/jobs/components/ApplyByEmailModal.vue';

const { t } = useI18n();
const { state, queryParams, reset, toggleStack } = useJobFilters();

const BASE_STACK_TAGS = ['php', 'laravel', 'vue', 'typescript', 'python', 'node', 'react', 'go'];

const visibleStackTags = computed(() => {
  const extra = state.stack.filter(t => !BASE_STACK_TAGS.includes(t));
  return [...extra, ...BASE_STACK_TAGS];
});

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

const { profile, fetch: fetchProfile } = useProfile();
fetchProfile();

watch(profile, (p) => {
  if (!p) return;
  if (p.skills.length) {
    state.stack = p.skills.map(s => s.name.toLowerCase());
  }
  if (p.seniority) state.seniority = p.seniority;
  if (p.modality) state.modality = p.modality;
  if (p.languages?.length) state.language = [...p.languages];
}, { once: true });

const LANGUAGE_OPTIONS: { value: Locale; labelKey: string }[] = [
  { value: 'pt_BR', labelKey: 'languages.pt_BR' },
  { value: 'en', labelKey: 'languages.en' },
  { value: 'es', labelKey: 'languages.es' },
];

function toggleLanguage(lang: Locale): void {
  const i = state.language.indexOf(lang);
  if (i >= 0) state.language.splice(i, 1);
  else state.language.push(lang);
}

const emailModalOpen = ref(false);
const emailModalJob = ref<Job | null>(null);

function needsEmailModal(job: Job): boolean {
  if (!job.contact_email) return false;
  if (!profile.value?.email_apply_enabled) return false;
  return (
    profile.value.email_apply_message_mode === 'variable' ||
    profile.value.email_apply_resume_mode === 'variable'
  );
}

async function onApply(job: Job): Promise<void> {
  // If email apply with variable fields, show modal
  if (needsEmailModal(job)) {
    emailModalJob.value = job;
    emailModalOpen.value = true;
    return;
  }

  applyingJobId.value = job.id;
  const externalUrl = job.sources?.[0]?.external_url ?? null;

  try {
    await applyMutation.mutateAsync({
      jobId: job.id,
      source: job.contact_email && profile.value?.email_apply_enabled ? 'email' : 'feed',
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

function onEmailApplied(): void {
  if (emailModalJob.value) {
    appliedJobIds.value.add(emailModalJob.value.id);
  }
  emailModalJob.value = null;
}

function applyLabel(jobId: number): string {
  if (applyingJobId.value === jobId) return t('jobs.applying');
  if (appliedJobIds.value.has(jobId)) return t('jobs.applied');
  if (alreadyAppliedJobIds.value.has(jobId)) return t('jobs.already_applied');
  return t('jobs.apply');
}
</script>

<template>
  <div class="lg:flex lg:h-full lg:flex-col lg:gap-3">
    <header class="flex flex-wrap items-center justify-between gap-3 mb-4 lg:mb-0">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
          {{ t('nav.jobs') }}
        </h1>
        <p class="mt-0.5 text-xs text-ink-500">
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
        >
        {{ t('jobs.filters.matchOnly') }}
      </label>
    </header>

    <!-- Resume selector -->
    <section
      v-if="resumes.data.value && resumes.data.value.data.length > 0"
      class="card flex flex-wrap items-center gap-3 p-3 mt-4 lg:mt-0"
    >
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
    </section>

    <!-- Filters -->
    <section class="card space-y-3 p-4 mt-4 lg:mt-0">
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

      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-ink-500">{{ t('jobs.filters.language') }}</span>
        <button
          v-for="lang in LANGUAGE_OPTIONS"
          :key="lang.value"
          type="button"
          class="rounded-full border px-3 py-1 text-xs font-medium transition"
          :class="
            state.language.includes(lang.value)
              ? 'border-brand-600 bg-brand-600 text-white shadow-soft'
              : 'border-ink-200 bg-white text-ink-700 hover:border-brand-300 hover:text-brand-700'
          "
          @click="toggleLanguage(lang.value)"
        >
          {{ t(lang.labelKey) }}
        </button>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-ink-500">Stack</span>
        <button
          v-for="tag in visibleStackTags"
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
          v-if="state.q || state.modality || state.seniority || state.stack.length || state.language.length"
          type="button"
          class="ml-auto text-xs font-medium text-ink-500 hover:text-ink-900"
          @click="reset"
        >
          {{ t('jobs.filters.reset') }}
        </button>
      </div>
    </section>

    <p
      v-if="isLoading"
      class="text-ink-500 mt-4 lg:mt-0"
    >
      {{ t('jobs.loading') }}
    </p>
    <p
      v-else-if="error"
      class="text-red-600 mt-4 lg:mt-0"
    >
      {{ t('jobs.error') }}
    </p>

    <div
      v-else-if="data && data.data.length === 0"
      class="card p-10 text-center text-ink-500 mt-4 lg:mt-0"
    >
      {{ state.matchOnly ? t('jobs.no_matches') : t('jobs.no_results') }}
    </div>

    <ul
      v-else
      class="space-y-2 mt-4 lg:mt-0 lg:min-h-0 lg:flex-1 lg:overflow-y-auto"
    >
      <li
        v-for="job in data?.data"
        :key="job.id"
        class="card flex gap-3 p-4 transition hover:border-brand-200 hover:shadow-card"
      >
        <div
          class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-ink-100 text-sm font-semibold text-ink-500"
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
              <h2 class="truncate font-semibold text-ink-900">
                {{ job.title }}
              </h2>
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
              v-if="job.contact_email"
              class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-200"
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
                  d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
                />
              </svg>
              {{ t('jobs.email_tag') }}
            </span>
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

    <p
      v-if="isFetching && !isLoading"
      class="text-xs text-ink-400"
    >
      {{ t('jobs.loading') }}
    </p>

    <ApplyByEmailModal
      v-if="emailModalJob && profile"
      v-model="emailModalOpen"
      :job="emailModalJob"
      :profile="profile"
      @applied="onEmailApplied"
    />
  </div>
</template>
