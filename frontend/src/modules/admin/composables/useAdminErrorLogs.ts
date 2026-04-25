import { useQuery } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { AdminErrorLogsResponseSchema, type AdminErrorLogsResponse } from '@/shared/api/schemas';

async function fetchAdminErrorLogs(): Promise<AdminErrorLogsResponse> {
  const { data } = await api.get('/api/admin/error-logs', { params: { limit: 50 } });
  return AdminErrorLogsResponseSchema.parse(data);
}

export function useAdminErrorLogs() {
  return useQuery({
    queryKey: ['admin', 'error-logs'],
    queryFn: fetchAdminErrorLogs,
    refetchInterval: 5_000,
    refetchIntervalInBackground: false,
  });
}
