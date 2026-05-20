import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { useAuthStore } from '@/modules/auth/stores/auth';

/**
 * Cancela assinatura Pro. Backend marca canceled_at no DB e chama Asaas
 * DELETE — usuário mantém Pro até current_period_end (não cai imediato).
 * Após sucesso, refazemos fetchMe() pra refletir status=canceled na UI.
 */
export function useCancelSubscription() {
  const qc = useQueryClient();
  const auth = useAuthStore();

  return useMutation<void, Error, void>({
    mutationFn: async () => {
      await api.delete('/api/subscriptions');
    },
    onSuccess: async () => {
      await auth.fetchMe();
      qc.invalidateQueries({ queryKey: ['subscription'] });
    },
  });
}
