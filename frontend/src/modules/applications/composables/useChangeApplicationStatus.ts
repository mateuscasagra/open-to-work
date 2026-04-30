import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { AxiosError } from 'axios';
import { api } from '@/shared/api/client';
import { ApplicationSchema, type Application, type ApplicationStatus } from '@/shared/api/schemas';

export interface ChangeStatusPayload {
  applicationId: number;
  status: ApplicationStatus;
  note?: string;
}

export type ChangeStatusError = {
  kind: 'invalid_transition' | 'forbidden' | 'unknown';
  message: string;
};

export function useChangeApplicationStatus() {
  const qc = useQueryClient();

  return useMutation<Application, ChangeStatusError, ChangeStatusPayload, { previous: Application[] | undefined }>({
    mutationFn: async ({ applicationId, status, note }) => {
      try {
        const { data } = await api.patch(`/api/applications/${applicationId}/status`, { status, note });
        return ApplicationSchema.parse(data);
      } catch (e) {
        if (e instanceof AxiosError) {
          if (e.response?.status === 422) {
            throw {
              kind: 'invalid_transition',
              message: e.response.data?.message ?? 'Transição inválida',
            } as ChangeStatusError;
          }
          if (e.response?.status === 403) {
            throw { kind: 'forbidden', message: 'Sem permissão' } as ChangeStatusError;
          }
        }
        throw { kind: 'unknown', message: 'Erro ao mudar status' } as ChangeStatusError;
      }
    },
    onMutate: async ({ applicationId, status }) => {
      await qc.cancelQueries({ queryKey: ['applications'] });
      const activeKey = ['applications', 'active'] as const;
      const previous = qc.getQueryData<Application[]>(activeKey);
      qc.setQueryData<Application[]>(activeKey, (old) =>
        (old ?? []).map((a) => (a.id === applicationId ? { ...a, status } : a))
      );
      return { previous };
    },
    onError: (_err, _payload, context) => {
      if (context?.previous) qc.setQueryData(['applications', 'active'], context.previous);
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ['applications'] });
    },
  });
}
