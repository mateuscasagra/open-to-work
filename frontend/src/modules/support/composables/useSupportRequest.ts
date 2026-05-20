import { useMutation } from '@tanstack/vue-query';
import { AxiosError } from 'axios';
import { api, extractApiErrorMessage } from '@/shared/api/client';

export interface SupportPayload {
  name?: string;
  email: string;
  title: string;
  description: string;
}

export type SupportError = {
  kind: 'validation' | 'rate_limited' | 'unknown';
  message: string;
};

/**
 * Envia mensagem do formulário "Preciso de ajuda" da landing. Endpoint público
 * (sem auth) + rate-limit 5/hora por IP no backend. Sucesso → backend dispara
 * e-mail pra config('services.support.recipient').
 */
export function useSupportRequest() {
  return useMutation<void, SupportError, SupportPayload>({
    mutationFn: async (payload) => {
      try {
        await api.post('/api/support', payload);
      } catch (e) {
        if (e instanceof AxiosError && e.response) {
          if (e.response.status === 422) {
            throw { kind: 'validation', message: extractApiErrorMessage(e, '') } as SupportError;
          }
          if (e.response.status === 429) {
            throw { kind: 'rate_limited', message: '' } as SupportError;
          }
        }
        throw { kind: 'unknown', message: '' } as SupportError;
      }
    },
  });
}
