import { useQuery } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { SuggestionQuotaSchema, type SuggestionQuota } from '@/shared/api/schemas';

export function useSuggestionsQuota() {
  return useQuery<SuggestionQuota>({
    queryKey: ['suggestions', 'quota'],
    queryFn: async () => {
      const { data } = await api.get('/api/suggestions/quota');
      return SuggestionQuotaSchema.parse(data);
    },
  });
}
