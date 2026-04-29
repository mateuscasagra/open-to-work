import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { AxiosError } from 'axios';
import { api } from '@/shared/api/client';
import { SuggestionSchema, type Suggestion } from '@/shared/api/schemas';

export interface CreateSuggestionPayload {
  title: string;
  body: string;
}

export type CreateSuggestionError =
  | { kind: 'quota_exceeded'; message: string; nextSlotAt: string | null }
  | { kind: 'validation'; message: string; errors: Record<string, string[]> }
  | { kind: 'unknown'; message: string };

export function useCreateSuggestion() {
  const qc = useQueryClient();

  return useMutation<Suggestion, CreateSuggestionError, CreateSuggestionPayload>({
    mutationFn: async (payload) => {
      try {
        const { data } = await api.post('/api/suggestions', payload);
        return SuggestionSchema.parse(data);
      } catch (e) {
        if (e instanceof AxiosError) {
          if (e.response?.status === 429) {
            throw {
              kind: 'quota_exceeded',
              message: e.response.data?.message ?? 'Quota exceeded',
              nextSlotAt: e.response.data?.next_slot_at ?? null,
            } as CreateSuggestionError;
          }
          if (e.response?.status === 422) {
            throw {
              kind: 'validation',
              message: e.response.data?.message ?? 'Validation error',
              errors: e.response.data?.errors ?? {},
            } as CreateSuggestionError;
          }
        }
        throw { kind: 'unknown', message: 'Failed to create suggestion' } as CreateSuggestionError;
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['suggestions'] });
    },
  });
}
