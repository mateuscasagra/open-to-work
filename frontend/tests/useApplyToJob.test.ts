import { describe, it, expect, vi, beforeEach } from 'vitest';
import { AxiosError, AxiosHeaders } from 'axios';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createApp, defineComponent, h } from 'vue';
import { useApplyToJob } from '@/modules/applications/composables/useApplyToJob';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: { post: vi.fn() },
}));

function mount<T>(composable: () => T): T {
  let result!: T;
  const app = createApp(
    defineComponent({
      setup() {
        result = composable();
        return () => h('div');
      },
    })
  );
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  app.use(VueQueryPlugin, { queryClient });
  app.mount(document.createElement('div'));
  return result;
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

describe('useApplyToJob', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('posts to /api/applications and returns the created application', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: {
        id: 10,
        status: 'applied',
        applied_at: '2026-04-18T12:00:00Z',
        notes: null,
        expected_salary: null,
        source: 'feed',
      },
    });

    const mutation = mount(() => useApplyToJob());
    const result = await mutation.mutateAsync({ jobId: 42, source: 'feed' });

    expect(api.post).toHaveBeenCalledWith('/api/applications', { jobId: 42, source: 'feed' });
    expect(result.id).toBe(10);
    expect(result.status).toBe('applied');
  });

  it('throws duplicate error on 409', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(axiosErr(409, 'You have already applied to this job.'));

    const mutation = mount(() => useApplyToJob());

    await expect(mutation.mutateAsync({ jobId: 1 })).rejects.toMatchObject({
      kind: 'duplicate',
    });
  });

  it('throws validation error on 422', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(axiosErr(422, 'Invalid'));

    const mutation = mount(() => useApplyToJob());

    await expect(mutation.mutateAsync({ jobId: 1 })).rejects.toMatchObject({
      kind: 'validation',
    });
  });

  it('throws quota_exceeded error on 402 with full payload', async () => {
    const err = new AxiosError('Quota');
    err.response = {
      status: 402,
      data: {
        message: 'Limite mensal atingido.',
        kind: 'quota_exceeded',
        used: 15,
        limit: 15,
        plan: 'free',
        reset_at: '2026-06-01T03:00:00+00:00',
      },
      statusText: '',
      headers: {},
      config: { headers: new AxiosHeaders() },
    };
    vi.mocked(api.post).mockRejectedValueOnce(err);

    const mutation = mount(() => useApplyToJob());

    await expect(mutation.mutateAsync({ jobId: 1 })).rejects.toMatchObject({
      kind: 'quota_exceeded',
      used: 15,
      limit: 15,
      plan: 'free',
      resetAt: '2026-06-01T03:00:00+00:00',
    });
  });
});
