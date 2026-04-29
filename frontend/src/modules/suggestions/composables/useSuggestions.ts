import { useQuery } from '@tanstack/vue-query';
import { computed, type Ref } from 'vue';
import { api } from '@/shared/api/client';
import { SuggestionsPageSchema, type SuggestionsPage } from '@/shared/api/schemas';

export function useSuggestions(page: Ref<number>) {
  return useQuery<SuggestionsPage>({
    queryKey: computed(() => ['suggestions', page.value] as const),
    queryFn: async () => {
      const { data } = await api.get('/api/suggestions', {
        params: { page: page.value },
      });
      return SuggestionsPageSchema.parse(data);
    },
  });
}
