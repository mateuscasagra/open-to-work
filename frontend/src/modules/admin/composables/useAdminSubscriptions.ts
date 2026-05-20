import { useQuery, keepPreviousData } from '@tanstack/vue-query';
import { computed, type Ref } from 'vue';
import { api } from '@/shared/api/client';
import { AdminSubscriptionsPageSchema, type AdminSubscriptionsPage } from '@/shared/api/schemas';

export type SubscriptionStatusFilter = 'all' | 'active' | 'canceled';

export function useAdminSubscriptions(status: Ref<SubscriptionStatusFilter>, page: Ref<number>) {
  return useQuery<AdminSubscriptionsPage>({
    queryKey: computed(() => ['admin', 'subscriptions', status.value, page.value] as const),
    queryFn: async () => {
      const { data } = await api.get('/api/admin/subscriptions', {
        params: { status: status.value, page: page.value },
      });
      return AdminSubscriptionsPageSchema.parse(data);
    },
    placeholderData: keepPreviousData,
  });
}
