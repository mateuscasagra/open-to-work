import { useQuery } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { AdminMetricsSchema, type AdminMetrics } from '@/shared/api/schemas';

async function fetchAdminMetrics(): Promise<AdminMetrics> {
  const { data } = await api.get('/api/admin/metrics');
  return AdminMetricsSchema.parse(data);
}

export function useAdminMetrics() {
  return useQuery({
    queryKey: ['admin', 'metrics'],
    queryFn: fetchAdminMetrics,
  });
}
