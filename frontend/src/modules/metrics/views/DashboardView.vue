<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMetrics } from '../composables/useMetrics';
import type { MetricsHeatmapCell } from '@/shared/api/schemas';

const { t } = useI18n();
const { data, loading, error, load } = useMetrics();

onMounted(() => {
  load();
});

const percent = (value: number): string => `${Math.round(value * 100)}%`;

const funnelMax = computed(() => {
  if (!data.value) return 0;
  return Math.max(1, ...data.value.funnel.map((stage) => stage.reached));
});

const weekdayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

const heatmapGrid = computed<number[][]>(() => {
  const grid: number[][] = Array.from({ length: 7 }, () => Array(24).fill(0) as number[]);
  if (!data.value) return grid;
  for (const cell of data.value.heatmap as MetricsHeatmapCell[]) {
    grid[cell.weekday][cell.hour] = cell.count;
  }
  return grid;
});

const heatmapMax = computed(() => {
  let max = 0;
  for (const row of heatmapGrid.value) {
    for (const v of row) if (v > max) max = v;
  }
  return max;
});

function heatmapColor(value: number): string {
  if (value === 0 || heatmapMax.value === 0) return 'rgb(241 245 249)';
  const intensity = Math.min(1, value / heatmapMax.value);
  const alpha = 0.15 + intensity * 0.75;
  return `rgba(5, 150, 105, ${alpha.toFixed(2)})`;
}

function severityClass(severity: 'info' | 'warning' | 'success'): string {
  if (severity === 'warning') return 'border-amber-200 bg-amber-50 text-amber-900';
  if (severity === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
  return 'border-brand-200 bg-brand-50 text-brand-900';
}

function severityIcon(severity: 'info' | 'warning' | 'success'): string {
  if (severity === 'warning') return '⚠️';
  if (severity === 'success') return '✅';
  return '💡';
}
</script>

<template>
  <div class="lg:flex lg:h-full lg:flex-col">
    <header class="mb-4 flex items-center justify-between lg:mb-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
          {{ t('nav.dashboard') }}
        </h1>
        <p class="mt-0.5 text-sm text-ink-500 lg:text-xs">
          {{ t('app.tagline') }}
        </p>
      </div>
      <p
        v-if="data && data.avgDaysBetweenStages !== null"
        class="hidden text-xs text-ink-500 lg:block"
      >
        Tempo médio até resposta:
        <span class="font-semibold text-ink-900">{{ data.avgDaysBetweenStages }}d</span>
      </p>
    </header>

    <div
      v-if="loading && !data"
      class="card p-8 text-center text-ink-500"
      data-testid="metrics-loading"
    >
      Carregando métricas…
    </div>

    <div
      v-else-if="error"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
      data-testid="metrics-error"
    >
      {{ error }}
    </div>

    <div
      v-else-if="data"
      class="flex flex-col gap-4 lg:min-h-0 lg:flex-1 lg:gap-3"
    >
      <!-- KPIs -->
      <div
        class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-2"
        data-testid="metrics-kpis"
      >
        <div class="card p-3 lg:p-2.5">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-ink-500">
              Candidaturas
            </p>
            <span class="chip-brand !px-1.5 !py-0.5 !text-[10px]">total</span>
          </div>
          <p class="mt-1 text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
            {{ data.kpis.total_applications }}
          </p>
        </div>
        <div class="card p-3 lg:p-2.5">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-ink-500">
              Taxa de resposta
            </p>
            <span class="chip-brand !px-1.5 !py-0.5 !text-[10px]">%</span>
          </div>
          <p class="mt-1 text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
            {{ percent(data.kpis.response_rate) }}
          </p>
          <p class="text-[10px] text-ink-500 lg:mt-0">
            {{ data.kpis.total_responses }} respostas
          </p>
        </div>
        <div class="card p-3 lg:p-2.5">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-ink-500">
              Entrevistas
            </p>
            <span class="chip-brand !px-1.5 !py-0.5 !text-[10px]">fase</span>
          </div>
          <p class="mt-1 text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
            {{ data.kpis.total_interviews }}
          </p>
          <p class="text-[10px] text-ink-500 lg:mt-0">
            {{ percent(data.kpis.interview_rate) }} das candidaturas
          </p>
        </div>
        <div class="card p-3 lg:p-2.5">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-ink-500">
              Propostas
            </p>
            <span class="chip-brand !px-1.5 !py-0.5 !text-[10px]">fase</span>
          </div>
          <p class="mt-1 text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
            {{ data.kpis.total_offers }}
          </p>
          <p class="text-[10px] text-ink-500 lg:mt-0">
            {{ percent(data.kpis.offer_rate) }} das candidaturas
          </p>
        </div>
      </div>

      <!-- Insights (inline) -->
      <section
        v-if="data.insights.length > 0"
        data-testid="metrics-insights"
      >
        <ul class="grid gap-2 lg:grid-cols-2">
          <li
            v-for="insight in data.insights"
            :key="insight.key"
            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-xs"
            :class="severityClass(insight.severity)"
          >
            <span class="text-sm leading-none">{{ severityIcon(insight.severity) }}</span>
            <span class="leading-snug">{{ insight.message }}</span>
          </li>
        </ul>
      </section>

      <!-- Middle row: Funnel + Channels -->
      <div class="grid gap-3 lg:min-h-0 lg:flex-1 lg:grid-cols-2">
        <section
          class="card p-4 lg:flex lg:flex-col lg:p-3"
          data-testid="metrics-funnel"
        >
          <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-500">
            Funil de etapas
          </h2>
          <div v-if="data.funnel.length > 0" class="space-y-1.5 lg:flex-1">
            <div
              v-for="stage in data.funnel"
              :key="stage.status"
              class="flex items-center gap-2"
            >
              <span class="w-24 shrink-0 truncate text-xs text-ink-700">{{ stage.label }}</span>
              <div class="h-4 flex-1 overflow-hidden rounded bg-ink-100">
                <div
                  class="h-full rounded bg-gradient-to-r from-brand-500 to-brand-600"
                  :style="{ width: `${(stage.reached / funnelMax) * 100}%` }"
                />
              </div>
              <span class="w-7 text-right text-xs font-semibold text-ink-900">
                {{ stage.reached }}
              </span>
            </div>
          </div>
          <div v-else class="flex flex-1 flex-col items-center justify-center py-6 text-center">
            <svg class="h-8 w-8 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
            </svg>
            <p class="mt-2 text-xs text-ink-400">
              {{ t('dashboard.funnel_empty') }}
            </p>
          </div>
        </section>

        <section
          class="card p-4 lg:flex lg:flex-col lg:p-3"
          data-testid="metrics-channels"
        >
          <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-500">
            Canais mais efetivos
          </h2>
          <table v-if="data.channels.length > 0" class="w-full text-xs lg:flex-1">
            <thead>
              <tr class="border-b border-ink-200 text-left text-ink-500">
                <th class="pb-1.5 font-medium">
                  Fonte
                </th>
                <th class="pb-1.5 text-right font-medium">
                  Cand.
                </th>
                <th class="pb-1.5 text-right font-medium">
                  Resp.
                </th>
                <th class="pb-1.5 text-right font-medium">
                  Taxa
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="channel in data.channels"
                :key="channel.source"
                class="border-b border-ink-100 last:border-0"
              >
                <td class="py-1.5 font-medium text-ink-900">
                  {{ channel.source }}
                </td>
                <td class="py-1.5 text-right text-ink-700">
                  {{ channel.applications }}
                </td>
                <td class="py-1.5 text-right text-ink-700">
                  {{ channel.responses }}
                </td>
                <td class="py-1.5 text-right font-semibold text-brand-700">
                  {{ percent(channel.response_rate) }}
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="flex flex-1 flex-col items-center justify-center py-6 text-center">
            <svg class="h-8 w-8 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
            </svg>
            <p class="mt-2 text-xs text-ink-400">
              {{ t('dashboard.channels_empty') }}
            </p>
          </div>
        </section>
      </div>

      <!-- Heatmap -->
      <section
        class="card p-4 lg:p-3"
        data-testid="metrics-heatmap"
      >
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-500">
          Dias & horários que você mais aplica
        </h2>
        <div class="overflow-x-auto">
          <table class="border-collapse text-[10px]">
            <thead>
              <tr>
                <th class="p-0.5" />
                <th
                  v-for="h in 24"
                  :key="h"
                  class="p-0.5 font-normal text-ink-400"
                >
                  {{ h - 1 }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, wd) in heatmapGrid"
                :key="wd"
              >
                <th class="pr-1.5 text-left font-medium text-ink-500">
                  {{ weekdayLabels[wd] }}
                </th>
                <td
                  v-for="(count, hour) in row"
                  :key="hour"
                  class="h-4 w-4 rounded-sm border border-white"
                  :style="{ backgroundColor: heatmapColor(count) }"
                  :title="`${weekdayLabels[wd]} ${hour}h: ${count} candidatura(s)`"
                />
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>
