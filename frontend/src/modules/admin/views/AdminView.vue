<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAdminMetrics } from '../composables/useAdminMetrics';
import { useAdminErrorLogs } from '../composables/useAdminErrorLogs';

const { t } = useI18n();
const { data, isLoading, error } = useAdminMetrics();
const { data: errorsData } = useAdminErrorLogs();

const userInitial = (name: string): string => (name?.[0] ?? '?').toUpperCase();

const generatedAt = computed(() => {
  const iso = data.value?.generated_at;
  if (!iso) return '';
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
});

const maxRanking = computed(() => {
  const top = data.value?.top_applicants ?? [];
  return Math.max(1, ...top.map((u) => u.applications_count));
});

const expandedStacks = ref<Set<number>>(new Set());

function toggleStack(id: number): void {
  const next = new Set(expandedStacks.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  expandedStacks.value = next;
}

function timeAgo(iso: string): string {
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return '';
  const diffMs = Date.now() - then;
  const seconds = Math.max(0, Math.floor(diffMs / 1000));
  if (seconds < 60) return t('admin.errors.ago_now');
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return t('admin.errors.ago_minute', { n: minutes });
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return t('admin.errors.ago_hour', { n: hours });
  const days = Math.floor(hours / 24);
  return t('admin.errors.ago_day', { n: days });
}

function shortClass(fqcn: string): string {
  const idx = fqcn.lastIndexOf('\\');
  return idx >= 0 ? fqcn.slice(idx + 1) : fqcn;
}
</script>

<template>
  <div class="lg:flex lg:h-full lg:flex-col">
    <header class="mb-4 flex items-center justify-between lg:mb-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
          {{ t('admin.title') }}
        </h1>
        <p class="mt-0.5 text-sm text-ink-500 lg:text-xs">
          {{ t('admin.subtitle') }}
        </p>
      </div>
      <p
        v-if="generatedAt"
        class="hidden text-xs text-ink-500 lg:block"
      >
        {{ t('admin.generated_at') }}:
        <span class="font-semibold text-ink-900">{{ generatedAt }}</span>
      </p>
    </header>

    <div
      v-if="isLoading && !data"
      class="card p-8 text-center text-ink-500"
      data-testid="admin-loading"
    >
      {{ t('admin.loading') }}
    </div>

    <div
      v-else-if="error"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
      data-testid="admin-error"
    >
      {{ t('admin.load_failed') }}
    </div>

    <div
      v-else-if="data"
      class="flex flex-col gap-4 lg:min-h-0 lg:flex-1 lg:gap-3"
    >
      <!-- KPIs -->
      <div
        class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-3"
        data-testid="admin-kpis"
      >
        <div class="card p-4">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-ink-500">
              {{ t('admin.kpi.users') }}
            </p>
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
              </svg>
            </span>
          </div>
          <p class="mt-2 text-3xl font-bold tracking-tight text-ink-900">
            {{ data.totals.users }}
          </p>
        </div>

        <div class="card p-4">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-ink-500">
              {{ t('admin.kpi.applications') }}
            </p>
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-sky-50 text-sky-600 ring-1 ring-inset ring-sky-100"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
              </svg>
            </span>
          </div>
          <p class="mt-2 text-3xl font-bold tracking-tight text-ink-900">
            {{ data.totals.applications }}
          </p>
        </div>

        <div class="card p-4">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-ink-500">
              {{ t('admin.kpi.resumes') }}
            </p>
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-violet-50 text-violet-600 ring-1 ring-inset ring-violet-100"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
            </span>
          </div>
          <p class="mt-2 text-3xl font-bold tracking-tight text-ink-900">
            {{ data.totals.resumes }}
          </p>
        </div>

        <div class="card p-4">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-ink-500">
              {{ t('admin.kpi.active_users') }}
            </p>
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-100"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
              </svg>
            </span>
          </div>
          <p class="mt-2 text-3xl font-bold tracking-tight text-ink-900">
            {{ data.totals.active_users }}
          </p>
          <p class="mt-1 text-[10px] text-ink-500">
            {{ t('admin.kpi.active_users_hint') }}
          </p>
        </div>
      </div>

      <!-- Top applicants ranking -->
      <section
        class="card p-5 lg:flex lg:flex-col lg:p-4"
        data-testid="admin-top-applicants"
      >
        <header class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-500">
              {{ t('admin.ranking.title') }}
            </h2>
            <p class="mt-0.5 text-xs text-ink-400">
              {{ t('admin.ranking.subtitle') }}
            </p>
          </div>
          <span class="chip-brand">Top {{ data.top_applicants.length }}</span>
        </header>

        <div
          v-if="data.top_applicants.length === 0"
          class="flex flex-col items-center justify-center rounded-xl border border-dashed border-ink-200 py-10 text-center"
        >
          <svg class="h-8 w-8 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0" />
          </svg>
          <p class="mt-3 text-sm text-ink-500">
            {{ t('admin.ranking.empty') }}
          </p>
        </div>

        <ol v-else class="space-y-2">
          <li
            v-for="(user, idx) in data.top_applicants"
            :key="user.user_id"
            class="group flex items-center gap-4 rounded-xl border border-ink-200 bg-white p-3 transition hover:border-brand-200"
          >
            <span
              class="grid h-9 w-9 flex-none place-items-center rounded-lg text-sm font-bold"
              :class="{
                'bg-amber-100 text-amber-700': idx === 0,
                'bg-ink-100 text-ink-700': idx === 1,
                'bg-orange-100 text-orange-700': idx === 2,
                'bg-ink-50 text-ink-500': idx > 2,
              }"
            >
              {{ idx + 1 }}º
            </span>
            <span
              class="grid h-9 w-9 flex-none place-items-center rounded-full bg-brand-50 font-semibold text-brand-700"
            >
              {{ userInitial(user.name) }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-ink-900">
                {{ user.name }}
              </p>
              <p class="truncate text-xs text-ink-500">
                {{ user.email }}
              </p>
            </div>
            <div class="flex w-1/3 items-center gap-3">
              <div class="h-2 flex-1 overflow-hidden rounded-full bg-ink-100">
                <div
                  class="h-full rounded-full bg-gradient-to-r from-brand-500 to-brand-600 transition-all"
                  :style="{ width: `${(user.applications_count / maxRanking) * 100}%` }"
                />
              </div>
              <span class="w-12 text-right text-sm font-bold tabular-nums text-ink-900">
                {{ user.applications_count }}
              </span>
            </div>
          </li>
        </ol>
      </section>

      <!-- Production errors (live) -->
      <section
        class="card p-5 lg:p-4"
        data-testid="admin-errors"
      >
        <header class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-500">
              {{ t('admin.errors.title') }}
            </h2>
            <p class="mt-0.5 text-xs text-ink-400">
              {{ t('admin.errors.subtitle', { n: errorsData?.data.length ?? 0 }) }}
            </p>
          </div>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
            <span class="relative flex h-2 w-2">
              <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
              <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
            </span>
            {{ t('admin.errors.live') }}
          </span>
        </header>

        <div
          v-if="!errorsData || errorsData.data.length === 0"
          class="flex flex-col items-center justify-center rounded-xl border border-dashed border-ink-200 py-10 text-center"
        >
          <p class="text-sm text-ink-500">
            {{ t('admin.errors.empty') }}
          </p>
        </div>

        <ul
          v-else
          class="max-h-[420px] space-y-2 overflow-y-auto pr-1"
        >
          <li
            v-for="err in errorsData.data"
            :key="err.id"
            class="rounded-xl border border-ink-200 bg-white p-3 transition hover:border-ink-300"
          >
            <div class="flex items-start gap-3">
              <span
                class="grid h-7 flex-none place-items-center rounded-md px-2 text-[10px] font-bold uppercase tracking-wider"
                :class="err.level === 'error'
                  ? 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200'
                  : 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200'"
              >
                {{ err.level === 'error' ? t('admin.errors.level_error') : t('admin.errors.level_warning') }}
              </span>
              <div class="min-w-0 flex-1">
                <p class="truncate font-mono text-xs font-semibold text-ink-900">
                  {{ shortClass(err.exception_class) }}
                </p>
                <p class="mt-0.5 break-words text-sm text-ink-700">
                  {{ err.message }}
                </p>
                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-500">
                  <span>{{ timeAgo(err.occurred_at) }}</span>
                  <span v-if="err.method && err.url" class="truncate">
                    <span class="font-mono font-medium text-ink-600">{{ err.method }}</span>
                    {{ err.url }}
                  </span>
                  <span v-if="err.file" class="truncate font-mono">
                    {{ err.file }}<span v-if="err.line">:{{ err.line }}</span>
                  </span>
                  <span v-if="err.user">
                    {{ err.user.name }} ({{ err.user.email }})
                  </span>
                  <span v-else class="italic">{{ t('admin.errors.anonymous') }}</span>
                </div>
              </div>
              <button
                v-if="err.stack_trace"
                type="button"
                class="flex-none rounded-md px-2 py-1 text-xs font-medium text-ink-500 hover:bg-ink-50 hover:text-ink-700"
                @click="toggleStack(err.id)"
              >
                {{ expandedStacks.has(err.id) ? t('admin.errors.hide_stack') : t('admin.errors.view_stack') }}
              </button>
            </div>
            <pre
              v-if="expandedStacks.has(err.id) && err.stack_trace"
              class="mt-3 max-h-64 overflow-auto rounded-lg bg-ink-900 p-3 text-[11px] leading-relaxed text-ink-100"
            >{{ err.stack_trace }}</pre>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
