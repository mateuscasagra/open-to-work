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
  <div>
    <header class="mb-8">
      <h1 class="text-3xl font-bold tracking-tight text-ink-900">{{ t('nav.dashboard') }}</h1>
      <p class="mt-1 text-ink-500">{{ t('app.tagline') }}</p>
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

    <div v-else-if="data" class="space-y-8">
      <!-- KPIs -->
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4" data-testid="metrics-kpis">
        <div class="card p-5">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-ink-500">Candidaturas</p>
            <span class="chip-brand">total</span>
          </div>
          <p class="mt-3 text-3xl font-bold tracking-tight text-ink-900">
            {{ data.kpis.total_applications }}
          </p>
        </div>
        <div class="card p-5">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-ink-500">Taxa de resposta</p>
            <span class="chip-brand">%</span>
          </div>
          <p class="mt-3 text-3xl font-bold tracking-tight text-ink-900">
            {{ percent(data.kpis.response_rate) }}
          </p>
          <p class="mt-1 text-xs text-ink-500">{{ data.kpis.total_responses }} respostas</p>
        </div>
        <div class="card p-5">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-ink-500">Entrevistas</p>
            <span class="chip-brand">fase</span>
          </div>
          <p class="mt-3 text-3xl font-bold tracking-tight text-ink-900">
            {{ data.kpis.total_interviews }}
          </p>
          <p class="mt-1 text-xs text-ink-500">
            {{ percent(data.kpis.interview_rate) }} das candidaturas
          </p>
        </div>
        <div class="card p-5">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-ink-500">Propostas</p>
            <span class="chip-brand">fase</span>
          </div>
          <p class="mt-3 text-3xl font-bold tracking-tight text-ink-900">
            {{ data.kpis.total_offers }}
          </p>
          <p class="mt-1 text-xs text-ink-500">
            {{ percent(data.kpis.offer_rate) }} das candidaturas
          </p>
        </div>
      </div>

      <!-- Insights -->
      <section v-if="data.insights.length > 0" data-testid="metrics-insights">
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-500">Insights</h2>
        <ul class="grid gap-3 md:grid-cols-2">
          <li
            v-for="insight in data.insights"
            :key="insight.key"
            class="flex items-start gap-3 rounded-xl border p-4 text-sm"
            :class="severityClass(insight.severity)"
          >
            <span class="text-lg leading-none">{{ severityIcon(insight.severity) }}</span>
            <span class="leading-relaxed">{{ insight.message }}</span>
          </li>
        </ul>
      </section>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Funnel -->
        <section v-if="data.funnel.length > 0" class="card p-6" data-testid="metrics-funnel">
          <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-500">
            Funil de etapas
          </h2>
          <div class="space-y-3">
            <div v-for="stage in data.funnel" :key="stage.status" class="flex items-center gap-3">
              <span class="w-32 shrink-0 text-sm text-ink-700">{{ stage.label }}</span>
              <div class="h-6 flex-1 overflow-hidden rounded-lg bg-ink-100">
                <div
                  class="h-full rounded-lg bg-gradient-to-r from-brand-500 to-brand-600"
                  :style="{ width: `${(stage.reached / funnelMax) * 100}%` }"
                />
              </div>
              <span class="w-10 text-right text-sm font-semibold text-ink-900">
                {{ stage.reached }}
              </span>
            </div>
          </div>
        </section>

        <!-- Channels -->
        <section v-if="data.channels.length > 0" class="card p-6" data-testid="metrics-channels">
          <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-500">
            Canais mais efetivos
          </h2>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-ink-200 text-left text-ink-500">
                <th class="pb-2 font-medium">Fonte</th>
                <th class="pb-2 text-right font-medium">Cand.</th>
                <th class="pb-2 text-right font-medium">Resp.</th>
                <th class="pb-2 text-right font-medium">Taxa</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="channel in data.channels"
                :key="channel.source"
                class="border-b border-ink-100 last:border-0"
              >
                <td class="py-2.5 font-medium text-ink-900">{{ channel.source }}</td>
                <td class="py-2.5 text-right text-ink-700">{{ channel.applications }}</td>
                <td class="py-2.5 text-right text-ink-700">{{ channel.responses }}</td>
                <td class="py-2.5 text-right font-semibold text-brand-700">
                  {{ percent(channel.response_rate) }}
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      <!-- Heatmap -->
      <section class="card p-6" data-testid="metrics-heatmap">
        <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-500">
          Dias & horários que você mais aplica
        </h2>
        <div class="overflow-x-auto">
          <table class="border-collapse text-xs">
            <thead>
              <tr>
                <th class="p-1"></th>
                <th v-for="h in 24" :key="h" class="p-1 font-normal text-ink-400">{{ h - 1 }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, wd) in heatmapGrid" :key="wd">
                <th class="pr-2 text-left font-medium text-ink-500">{{ weekdayLabels[wd] }}</th>
                <td
                  v-for="(count, hour) in row"
                  :key="hour"
                  class="h-5 w-5 rounded-sm border border-white"
                  :style="{ backgroundColor: heatmapColor(count) }"
                  :title="`${weekdayLabels[wd]} ${hour}h: ${count} candidatura(s)`"
                />
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <p v-if="data.avgDaysBetweenStages !== null" class="text-sm text-ink-500">
        Tempo médio entre candidatura e primeira resposta:
        <span class="font-semibold text-ink-900">{{ data.avgDaysBetweenStages }} dias</span>
      </p>
    </div>
  </div>
</template>
