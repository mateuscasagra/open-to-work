import { ref } from 'vue';
import { api } from '@/shared/api/client';
import { MetricsSummarySchema, type MetricsSummary } from '@/shared/api/schemas';

export function useMetrics() {
  const data = ref<MetricsSummary | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function load(params: { from?: string; to?: string } = {}): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.get('/api/metrics', { params });
      data.value = MetricsSummarySchema.parse(response.data);
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Erro ao carregar métricas';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  return { data, loading, error, load };
}
