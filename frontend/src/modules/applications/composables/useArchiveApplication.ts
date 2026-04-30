import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { ApplicationSchema, type Application } from '@/shared/api/schemas';

export function useArchiveApplication() {
  const qc = useQueryClient();

  return useMutation<Application, Error, { id: number; archive: boolean }>({
    mutationFn: async ({ id, archive }) => {
      const action = archive ? 'archive' : 'unarchive';
      const { data } = await api.post(`/api/applications/${id}/${action}`);
      return ApplicationSchema.parse(data);
    },
    onSuccess: (app) => {
      qc.invalidateQueries({ queryKey: ['applications'] });
      qc.invalidateQueries({ queryKey: ['application', app.id] });
    },
  });
}
