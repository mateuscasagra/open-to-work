import { describe, it, expect, vi, beforeEach } from 'vitest';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createApp, defineComponent, h, nextTick } from 'vue';
import { useQuota } from '@/modules/subscription/composables/useQuota';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn() },
}));

function mount<T>(composable: () => T): T {
  let result!: T;
  const app = createApp(
    defineComponent({
      setup() {
        result = composable();
        return () => h('div');
      },
    }),
  );
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  app.use(VueQueryPlugin, { queryClient });
  app.mount(document.createElement('div'));
  return result;
}

async function flushQuery(query: ReturnType<typeof useQuota>): Promise<void> {
  // Aguarda o queryFn rodar e a refMutation atualizar.
  await new Promise((r) => setTimeout(r, 0));
  await nextTick();
  while (query.isFetching.value) {
    await new Promise((r) => setTimeout(r, 0));
  }
  await nextTick();
}

describe('useQuota', () => {
  beforeEach(() => vi.clearAllMocks());

  it('parses /api/me/quota response for a free plan', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      data: {
        used: 7,
        limit: 15,
        reset_at: '2026-06-01T03:00:00+00:00',
        plan: 'free',
      },
    });

    const query = mount(() => useQuota());
    await flushQuery(query);

    expect(api.get).toHaveBeenCalledWith('/api/me/quota');
    expect(query.data.value).toEqual({
      used: 7,
      limit: 15,
      reset_at: '2026-06-01T03:00:00+00:00',
      plan: 'free',
    });
  });

  it('parses Pro quota with null limit (unlimited)', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      data: {
        used: 42,
        limit: null,
        reset_at: '2026-06-01T03:00:00+00:00',
        plan: 'pro',
      },
    });

    const query = mount(() => useQuota());
    await flushQuery(query);

    expect(query.data.value?.limit).toBeNull();
    expect(query.data.value?.plan).toBe('pro');
  });
});
