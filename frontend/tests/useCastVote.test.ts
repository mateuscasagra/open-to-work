import { describe, it, expect, vi, beforeEach } from 'vitest';
import { AxiosError, AxiosHeaders } from 'axios';
import { QueryClient, VueQueryPlugin, useQueryClient } from '@tanstack/vue-query';
import { createApp, defineComponent, h } from 'vue';
import { useCastVote } from '@/modules/suggestions/composables/useCastVote';
import { api } from '@/shared/api/client';
import type { Suggestion, SuggestionsPage } from '@/shared/api/schemas';

vi.mock('@/shared/api/client', () => ({
  api: { post: vi.fn() },
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

function buildSuggestion(overrides: Partial<Suggestion> = {}): Suggestion {
  return {
    id: 1,
    user: { id: 7, name: 'Diego' },
    title: 'Add dark mode',
    body: 'It would be nice to have',
    upvotes_count: 0,
    downvotes_count: 0,
    score: 0,
    my_vote: null,
    rank: null,
    created_at: '2026-04-29T10:00:00Z',
    ...overrides,
  };
}

function buildPage(items: Suggestion[]): SuggestionsPage {
  return { data: items, current_page: 1, last_page: 1, total: items.length };
}

describe('useCastVote', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('POSTs vote payload and returns parsed suggestion', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: buildSuggestion({ upvotes_count: 1, score: 1, my_vote: 1 }),
    });

    const { result } = mountWithClient(() => useCastVote());
    const out = await result.mutateAsync({ suggestionId: 1, value: 'up' });

    expect(api.post).toHaveBeenCalledWith('/api/suggestions/1/vote', { value: 'up' });
    expect(out.upvotes_count).toBe(1);
    expect(out.my_vote).toBe(1);
  });

  it('optimistically increments upvote when no previous vote', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: buildSuggestion({ upvotes_count: 1, score: 1, my_vote: 1 }),
    });

    const { result, queryClient } = mountWithClient(() => useCastVote());
    queryClient.setQueryData(['suggestions', 1], buildPage([buildSuggestion()]));

    await result.mutateAsync({ suggestionId: 1, value: 'up' });

    const cache = queryClient.getQueryData<SuggestionsPage>(['suggestions', 1]);
    expect(cache?.data[0].upvotes_count).toBe(1);
    expect(cache?.data[0].my_vote).toBe(1);
    expect(cache?.data[0].score).toBe(1);
  });

  it('optimistically toggles off when same value sent twice', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: buildSuggestion({ upvotes_count: 0, score: 0, my_vote: null }),
    });

    const { result, queryClient } = mountWithClient(() => useCastVote());
    queryClient.setQueryData(
      ['suggestions', 1],
      buildPage([buildSuggestion({ upvotes_count: 1, score: 1, my_vote: 1 })])
    );

    await result.mutateAsync({ suggestionId: 1, value: 'up' });

    const cache = queryClient.getQueryData<SuggestionsPage>(['suggestions', 1]);
    expect(cache?.data[0].my_vote).toBeNull();
    expect(cache?.data[0].upvotes_count).toBe(0);
    expect(cache?.data[0].score).toBe(0);
  });

  it('optimistically replaces vote when opposite value sent (up -> down)', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: buildSuggestion({ upvotes_count: 0, downvotes_count: 1, score: -1, my_vote: -1 }),
    });

    const { result, queryClient } = mountWithClient(() => useCastVote());
    queryClient.setQueryData(
      ['suggestions', 1],
      buildPage([buildSuggestion({ upvotes_count: 1, score: 1, my_vote: 1 })])
    );

    await result.mutateAsync({ suggestionId: 1, value: 'down' });

    const cache = queryClient.getQueryData<SuggestionsPage>(['suggestions', 1]);
    expect(cache?.data[0].my_vote).toBe(-1);
    expect(cache?.data[0].upvotes_count).toBe(0);
    expect(cache?.data[0].downvotes_count).toBe(1);
    expect(cache?.data[0].score).toBe(-1);
  });

  it('rolls back on 403 (own suggestion)', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(axiosErr(403, { message: 'cannot vote own' }));

    const { result, queryClient } = mountWithClient(() => useCastVote());
    const original = buildPage([buildSuggestion()]);
    queryClient.setQueryData(['suggestions', 1], original);

    await expect(result.mutateAsync({ suggestionId: 1, value: 'up' })).rejects.toMatchObject({
      kind: 'forbidden',
    });

    const afterRollback = queryClient.getQueryData<SuggestionsPage>(['suggestions', 1]);
    expect(afterRollback?.data[0].upvotes_count).toBe(0);
    expect(afterRollback?.data[0].my_vote).toBeNull();
  });

  it('updates all paginated query caches that contain the suggestion', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: buildSuggestion({ id: 5, upvotes_count: 1, score: 1, my_vote: 1 }),
    });

    const { result, queryClient } = mountWithClient(() => useCastVote());
    queryClient.setQueryData(['suggestions', 1], buildPage([buildSuggestion({ id: 5 })]));
    queryClient.setQueryData(['suggestions', 2], buildPage([buildSuggestion({ id: 99 })]));

    await result.mutateAsync({ suggestionId: 5, value: 'up' });

    const p1 = queryClient.getQueryData<SuggestionsPage>(['suggestions', 1]);
    const p2 = queryClient.getQueryData<SuggestionsPage>(['suggestions', 2]);
    expect(p1?.data[0].my_vote).toBe(1);
    expect(p2?.data[0].my_vote).toBeNull();
  });
});
