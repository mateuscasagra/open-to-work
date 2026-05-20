import { computed, type ComputedRef } from 'vue';
import { useAuthStore } from '@/modules/auth/stores/auth';
import type { Subscription } from '@/shared/api/schemas';

/**
 * Acessa a subscription do user logado (lida no auth store via /api/me).
 * Reativo: muda automaticamente quando fetchMe() rerruna após pagamento.
 */
export function useSubscription(): ComputedRef<Subscription | null> {
  const auth = useAuthStore();
  return computed(() => auth.subscription);
}
