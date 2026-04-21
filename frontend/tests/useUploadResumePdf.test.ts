import { describe, it, expect, vi, beforeEach } from 'vitest';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createApp, defineComponent, h } from 'vue';
import { useUploadResumePdf } from '@/modules/resumes/composables/useUploadResumePdf';
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

const baseResume = {
  id: 7,
  user_id: 1,
  title: 'Uploaded CV',
  language: 'pt_BR',
  is_pdf_upload: true,
  file_path: 'resumes/1/uuid.pdf',
  metadata: { original_name: 'cv.pdf', size: 1024 },
  sections: [],
};

describe('useUploadResumePdf', () => {
  beforeEach(() => vi.clearAllMocks());

  it('POSTs multipart form with title and file', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: baseResume });

    const mutation = mount(() => useUploadResumePdf());
    const file = new File([new Uint8Array([0x25, 0x50, 0x44, 0x46])], 'cv.pdf', {
      type: 'application/pdf',
    });

    const result = await mutation.mutateAsync({ title: 'Uploaded CV', language: 'pt_BR', file });

    expect(api.post).toHaveBeenCalledTimes(1);
    const [url, body, config] = vi.mocked(api.post).mock.calls[0];
    expect(url).toBe('/api/resumes/pdf');
    expect(body).toBeInstanceOf(FormData);
    expect((body as FormData).get('title')).toBe('Uploaded CV');
    expect((body as FormData).get('language')).toBe('pt_BR');
    expect((body as FormData).get('file')).toBeInstanceOf(File);
    expect((config as { headers: Record<string, string> }).headers['Content-Type']).toBe(
      'multipart/form-data'
    );
    expect(result.is_pdf_upload).toBe(true);
  });

  it('omits language when not provided', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: baseResume });

    const mutation = mount(() => useUploadResumePdf());
    const file = new File(['x'], 'cv.pdf', { type: 'application/pdf' });

    await mutation.mutateAsync({ title: 'X', file });

    const body = vi.mocked(api.post).mock.calls[0][1] as FormData;
    expect(body.get('language')).toBeNull();
  });
});
