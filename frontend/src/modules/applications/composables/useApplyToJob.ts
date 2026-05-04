import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { AxiosError } from 'axios';
import { api } from '@/shared/api/client';
import { ApplicationSchema, type Application } from '@/shared/api/schemas';

export interface ApplyPayload {
  jobId: number;
  notes?: string;
  expectedSalary?: number;
  resumeId?: number;
  source?: string;
}

export type ApplyError = { kind: 'duplicate' | 'validation' | 'unknown'; message: string };

export function useApplyToJob() {
  const qc = useQueryClient();

  return useMutation<Application, ApplyError, ApplyPayload>({
    mutationFn: async (payload) => {
      try {
        const { data } = await api.post('/api/applications', payload);
        return ApplicationSchema.parse(data);
      } catch (e) {
        if (e instanceof AxiosError) {
          if (e.response?.status === 409) {
            throw { kind: 'duplicate', message: e.response.data?.message ?? 'Já aplicada' } as ApplyError;
          }
          if (e.response?.status === 422) {
            throw { kind: 'validation', message: 'Dados inválidos' } as ApplyError;
          }
        }
        throw { kind: 'unknown', message: 'Erro ao aplicar' } as ApplyError;
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['applications'] });
    },
  });
}
