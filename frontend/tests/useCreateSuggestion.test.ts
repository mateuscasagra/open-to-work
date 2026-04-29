import { describe, it, expect, vi, beforeEach } from 'vitest';
import { AxiosError, AxiosHeaders } from 'axios';
import { QueryClient, VueQueryPlugin, useQueryClient } from '@tanstack/vue-query';
import { createApp, defineComponent, h } from 'vue';
import { useCreateSuggestion } from '@/modules/suggestions/composables/useCreateSuggestion';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: { post: vi.fn(), get: vi.fn() },
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

function axiosErr(status: number, data: unknown): AxiosError {
  const err = new AxiosError('error');
  err.response = {
    status,
    data,
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
  };
  return err;
}

const validSuggestion = {
  id: 1,
  user: { id: 7, name: 'Diego' },
  title: 'Add dark mode',
  body: 'It would be nice to have a dark mode for late night job hunting.',
  upvotes_count: 0,
  downvotes_count: 0,
  score: 0,
  my_vote: null,
  rank: 1,
  created_at: '2026-04-29T10:00:00Z',
};

describe('useCreateSuggestion', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('POSTs to /api/suggestions and returns the parsed suggestion', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: validSuggestion });

    const { result } = mountWithClient(() => useCreateSuggestion());
    const out = await result.mutateAsync({ title: 'Add dark mode', body: 'It would be nice to have' });

    expect(api.post).toHaveBeenCalledWith('/api/suggestions', { title: 'Add dark mode', body: 'It would be nice to have' });
    expect(out.id).toBe(1);
    expect(out.rank).toBe(1);
  });

  it('throws kind=quota_exceeded with nextSlotAt on 429', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(
      axiosErr(429, { message: 'Você atingiu o limite de 5 sugestões por semana.', next_slot_at: '2026-05-06T10:00:00Z' })
    );

    const { result } = mountWithClient(() => useCreateSuggestion());

    await expect(result.mutateAsync({ title: 'Sixth', body: 'Should fail' })).rejects.toMatchObject({
      kind: 'quota_exceeded',
      nextSlotAt: '2026-05-06T10:00:00Z',
    });
  });

  it('throws kind=validation on 422', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(
      axiosErr(422, { message: 'The given data was invalid.', errors: { title: ['title is required'] } })
    );

    const { result } = mountWithClient(() => useCreateSuggestion());

    await expect(result.mutateAsync({ title: '', body: 'short' })).rejects.toMatchObject({
      kind: 'validation',
      errors: { title: ['title is required'] },
    });
  });

  it('throws kind=unknown on other errors', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(axiosErr(500, { message: 'boom' }));

    const { result } = mountWithClient(() => useCreateSuggestion());

    await expect(result.mutateAsync({ title: 'x', body: 'y' })).rejects.toMatchObject({ kind: 'unknown' });
  });
});
