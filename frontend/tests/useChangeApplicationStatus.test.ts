import { describe, it, expect, vi, beforeEach } from 'vitest';
import { AxiosError, AxiosHeaders } from 'axios';
import { QueryClient, VueQueryPlugin, useQueryClient } from '@tanstack/vue-query';
import { createApp, defineComponent, h } from 'vue';
import { useChangeApplicationStatus } from '@/modules/applications/composables/useChangeApplicationStatus';
import { api } from '@/shared/api/client';
import type { Application } from '@/shared/api/schemas';

vi.mock('@/shared/api/client', () => ({
  api: { patch: vi.fn() },
}));

function mountWithClient<T>(composable: () => T): { result: T; queryClient: QueryClient } {
  let result!: T;
  let queryClient!: QueryClient;
  const app = createApp(
    defineComponent({
      setup() {
        queryClient = useQueryClient();
        result = composable();
        return () => h('div');
      },
    })
  );
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  app.use(VueQueryPlugin, { queryClient: client });
  app.mount(document.createElement('div'));
  return { result, queryClient };
}

function axiosErr(status: number, message: string): AxiosError {
  const err = new AxiosError(message);
  err.response = {
    status,
    data: { message },
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
  };
  return err;
}

function buildApp(id: number, status: Application['status']): Application {
  return {
    id,
    status,
    applied_at: '2026-04-18T12:00:00Z',
    notes: null,
    expected_salary: null,
    source: null,
  };
}

describe('useChangeApplicationStatus', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('PATCHes status and returns updated application', async () => {
    vi.mocked(api.patch).mockResolvedValueOnce({
      data: buildApp(1, 'screening'),
    });

    const { result } = mountWithClient(() => useChangeApplicationStatus());
    const updated = await result.mutateAsync({ applicationId: 1, status: 'screening' });

    expect(api.patch).toHaveBeenCalledWith('/api/applications/1/status', {
      status: 'screening',
      note: undefined,
    });
    expect(updated.status).toBe('screening');
  });

  it('rolls back optimistic update on 422 from server', async () => {
    vi.mocked(api.patch).mockRejectedValueOnce(axiosErr(422, 'Validation failed'));

    const { result, queryClient } = mountWithClient(() => useChangeApplicationStatus());
    const initial = [buildApp(1, 'applied')];
    queryClient.setQueryData(['applications', 'active'], initial);

    await expect(
      result.mutateAsync({ applicationId: 1, status: 'offer' })
    ).rejects.toMatchObject({ kind: 'invalid_transition' });

    const afterRollback = queryClient.getQueryData<Application[]>(['applications', 'active']);
    expect(afterRollback?.[0].status).toBe('applied');
  });

});
