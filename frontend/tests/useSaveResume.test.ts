import { describe, it, expect, vi, beforeEach } from 'vitest';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createApp, defineComponent, h } from 'vue';
import { useSaveResume } from '@/modules/resumes/composables/useSaveResume';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: { post: vi.fn(), put: vi.fn() },
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

const baseResume = {
  id: 5,
  user_id: 1,
  title: 'Backend',
  language: 'pt_BR',
  is_pdf_upload: false,
  file_path: null,
  metadata: null,
  sections: [],
};

describe('useSaveResume', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('POSTs to /api/resumes when no id is provided', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: baseResume });

    const mutation = mount(() => useSaveResume());
    const result = await mutation.mutateAsync({
      title: 'Backend',
      language: 'pt_BR',
      sections: [
        { type: 'summary', order: 0, content: { text: 'hi' } },
      ],
    });

    expect(api.post).toHaveBeenCalledWith('/api/resumes', {
      title: 'Backend',
      language: 'pt_BR',
      sections: [{ type: 'summary', order: 0, content: { text: 'hi' } }],
    });
    expect(api.put).not.toHaveBeenCalled();
    expect(result.id).toBe(5);
  });

  it('PUTs to /api/resumes/{id} when id is provided', async () => {
    vi.mocked(api.put).mockResolvedValueOnce({ data: { ...baseResume, title: 'Updated' } });

    const mutation = mount(() => useSaveResume());
    const result = await mutation.mutateAsync({
      id: 5,
      title: 'Updated',
      language: 'pt_BR',
      sections: [],
    });

    expect(api.put).toHaveBeenCalledWith('/api/resumes/5', {
      title: 'Updated',
      language: 'pt_BR',
      sections: [],
    });
    expect(api.post).not.toHaveBeenCalled();
    expect(result.title).toBe('Updated');
  });

  it('strips id from section payload (only type/order/content sent)', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: baseResume });

    const mutation = mount(() => useSaveResume());
    await mutation.mutateAsync({
      title: 'X',
      language: 'en',
      sections: [
        { id: 999, type: 'skill', order: 0, content: { name: 'PHP' } },
      ],
    });

    const [, body] = vi.mocked(api.post).mock.calls[0];
    expect(body).toEqual({
      title: 'X',
      language: 'en',
      sections: [{ type: 'skill', order: 0, content: { name: 'PHP' } }],
    });
  });
});
