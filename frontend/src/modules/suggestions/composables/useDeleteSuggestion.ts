import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { AxiosError } from 'axios';
import { api } from '@/shared/api/client';

export type DeleteSuggestionError = { kind: 'forbidden' | 'unknown'; message: string };

export function useDeleteSuggestion() {
  const qc = useQueryClient();

  return useMutation<void, DeleteSuggestionError, number>({
    mutationFn: async (id) => {
      try {
        await api.delete(`/api/suggestions/${id}`);
      } catch (e) {
        if (e instanceof AxiosError && e.response?.status === 403) {
          throw { kind: 'forbidden', message: 'Forbidden' } as DeleteSuggestionError;
        }
        throw { kind: 'unknown', message: 'Failed to delete suggestion' } as DeleteSuggestionError;
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['suggestions'] });
    },
  });
}
