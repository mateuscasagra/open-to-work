import { useQuery } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { QuotaSchema, type Quota } from '@/shared/api/schemas';

/**
 * Refresh leve da quota (used/limit/reset_at). Usar quando precisar invalidar
 * apenas o contador sem refazer /api/me inteiro. Stale time = 30s pra
 * evitar polling agressivo em dashboards.
 */
export function useQuota() {
  return useQuery<Quota>({
    queryKey: ['subscription', 'quota'],
    queryFn: async () => {
      const { data } = await api.get('/api/me/quota');
      return QuotaSchema.parse(data);
    },
    staleTime: 30_000,
  });
}
