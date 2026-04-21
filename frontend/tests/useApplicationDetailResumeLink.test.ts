import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ref } from 'vue';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createApp, defineComponent, h } from 'vue';
import { useApplicationDetail } from '@/modules/applications/composables/useApplicationDetail';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), put: vi.fn() },
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
  id: 1,
  status: 'applied',
  applied_at: '2026-04-18T12:00:00Z',
  notes: null,
  expected_salary: null,
  source: 'feed',
  resume_id: null,
  resume: null,
};

describe('useApplicationDetail.updateNotes with resumeId', () => {
  beforeEach(() => vi.clearAllMocks());

  it('sends only resume_id when only resumeId is provided', async () => {
    vi.mocked(api.put).mockResolvedValueOnce({ data: { ...baseApp, resume_id: 7 } });

    const { updateNotes } = mount(() => useApplicationDetail(ref(1)));
    const result = await updateNotes.mutateAsync({ resumeId: 7 });

    const [url, body] = vi.mocked(api.put).mock.calls[0];
    expect(url).toBe('/api/applications/1');
    expect(body).toEqual({ resume_id: 7 });
    expect(result.resume_id).toBe(7);
  });

  it('sends resume_id: null to clear the link', async () => {
    vi.mocked(api.put).mockResolvedValueOnce({ data: baseApp });

    const { updateNotes } = mount(() => useApplicationDetail(ref(1)));
    await updateNotes.mutateAsync({ resumeId: null });

    const [, body] = vi.mocked(api.put).mock.calls[0];
    expect(body).toEqual({ resume_id: null });
  });

  it('does not send resume_id when only notes are provided', async () => {
    vi.mocked(api.put).mockResolvedValueOnce({ data: { ...baseApp, notes: 'ok' } });

    const { updateNotes } = mount(() => useApplicationDetail(ref(1)));
    await updateNotes.mutateAsync({ notes: 'ok' });

    const [, body] = vi.mocked(api.put).mock.calls[0] as [string, Record<string, unknown>];
    expect(body.notes).toBe('ok');
    expect('resume_id' in body).toBe(false);
  });
});
