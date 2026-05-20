import { useMutation } from '@tanstack/vue-query';
import { AxiosError } from 'axios';
import { api, extractApiErrorMessage } from '@/shared/api/client';
import { PixCheckoutSchema, type PixCheckout } from '@/shared/api/schemas';

export type SubscribeError = {
  kind: 'already' | 'gateway' | 'validation' | 'unknown';
  message: string;
};

export interface SubscribePayload {
  cpf: string; // dígitos (11 ou 14)
}

/**
 * Inicia assinatura Pro. Backend cria customer (com CPF) + subscription
 * PIX no Asaas e retorna o primeiro PIX QR. Pro só fica ativo quando webhook
 * PAYMENT_CONFIRMED chegar — frontend faz polling via auth.fetchMe().
 */
export function useSubscribeToPro() {
  return useMutation<PixCheckout, SubscribeError, SubscribePayload>({
    mutationFn: async (payload) => {
      try {
        const { data } = await api.post('/api/subscriptions', payload);
        return PixCheckoutSchema.parse(data);
      } catch (e) {
        if (e instanceof AxiosError && e.response) {
          if (e.response.status === 409) {
            throw { kind: 'already', message: e.response.data?.message ?? '' } as SubscribeError;
          }
          if (e.response.status === 502) {
            throw { kind: 'gateway', message: e.response.data?.message ?? '' } as SubscribeError;
          }
          if (e.response.status === 422) {
            // 422 inclui CPF inválido — propaga a mensagem do backend pro modal mostrar.
            throw { kind: 'validation', message: extractApiErrorMessage(e, '') } as SubscribeError;
          }
        }
        throw { kind: 'unknown', message: 'unknown' } as SubscribeError;
      }
    },
  });
}
