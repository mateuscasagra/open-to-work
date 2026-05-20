import { useQuery } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { PricingSchema, type Pricing } from '@/shared/api/schemas';

/**
 * Preços públicos (free + pro). Endpoint sem auth — usado na landing page.
 * Cache 5min porque preços mudam raramente; backend já tem cache de 60s
 * por cima da tabela `plans`.
 */
export function usePricing() {
  return useQuery<Pricing>({
    queryKey: ['pricing'],
    queryFn: async () => {
      const { data } = await api.get('/api/pricing');
      return PricingSchema.parse(data);
    },
    staleTime: 5 * 60 * 1000,
  });
}
