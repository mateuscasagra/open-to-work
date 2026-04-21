import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';

export function useDeleteResume() {
  const qc = useQueryClient();

  return useMutation<void, Error, number>({
    mutationFn: async (id) => {
      await api.delete(`/api/resumes/${id}`);
    },
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: ['resumes'] });
      qc.removeQueries({ queryKey: ['resume', id] });
    },
  });
}
