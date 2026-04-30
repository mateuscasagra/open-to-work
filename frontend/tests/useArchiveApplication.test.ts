import { describe, it, expect, vi, beforeEach } from 'vitest';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createApp, defineComponent, h } from 'vue';
import { useArchiveApplication } from '@/modules/applications/composables/useArchiveApplication';
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

const baseApp = {
  id: 7,
  status: 'applied',
  applied_at: '2026-04-18T12:00:00Z',
  archived_at: null,
  notes: null,
  expected_salary: null,
  source: null,
};

describe('useArchiveApplication', () => {
  beforeEach(() => vi.clearAllMocks());

  it('POSTs to /archive when archive=true', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { ...baseApp, archived_at: '2026-04-30T10:00:00Z' },
    });

    const mutation = mount(() => useArchiveApplication());
    const result = await mutation.mutateAsync({ id: 7, archive: true });

    expect(api.post).toHaveBeenCalledWith('/api/applications/7/archive');
    expect(result.archived_at).toBe('2026-04-30T10:00:00Z');
  });

  it('POSTs to /unarchive when archive=false', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { ...baseApp, archived_at: null },
    });

    const mutation = mount(() => useArchiveApplication());
    const result = await mutation.mutateAsync({ id: 7, archive: false });

    expect(api.post).toHaveBeenCalledWith('/api/applications/7/unarchive');
    expect(result.archived_at).toBeNull();
  });
});
