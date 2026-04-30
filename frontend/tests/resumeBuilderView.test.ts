import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { nextTick } from 'vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import { createI18n } from 'vue-i18n';
import ResumeBuilderView from '@/modules/resumes/views/ResumeBuilderView.vue';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}));

const mkResume = (overrides = {}) => ({
  id: 7,
  user_id: 1,
  title: 'Meu Curriculo',
  language: 'pt_BR',
  is_pdf_upload: false,
  file_path: null,
  metadata: null,
  sections: [
    { id: 1, type: 'contact', order: 0, content: { email: 'old@a.com', phone: '', linkedin: '', github: '', website: '', address: '' } },
    { id: 2, type: 'summary', order: 1, content: { text: 'old summary' } },
    { id: 3, type: 'experience', order: 2, content: { company: 'Old Co', role: 'Dev', startDate: '', endDate: '', current: false, description: '' } },
  ],
  ...overrides,
});

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/resumes', name: 'resumes', component: { template: '<div/>' } },
      { path: '/resumes/:id/edit', name: 'resume-edit', component: ResumeBuilderView },
    ],
  });

  const i18n = createI18n({ legacy: false, locale: 'pt_BR', messages: { pt_BR: {} } });
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  await router.push('/resumes/7/edit');
  await router.isReady();

  const wrapper = mount(ResumeBuilderView, {
    global: {
      plugins: [router, i18n, [VueQueryPlugin, { queryClient }]],
    },
  });

  return { wrapper, queryClient };
}

describe('ResumeBuilderView — section edits round-trip to PUT body', () => {
  beforeEach(() => vi.clearAllMocks());

  it('sends edited contact email in PUT body', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: mkResume() });
    vi.mocked(api.put).mockResolvedValue({ data: mkResume() });

    const { wrapper } = await mountView();
    await flushPromises();
    await nextTick();
    await flushPromises();

    // Find contact email input (type=email)
    const emailInput = wrapper.find('input[type="email"]');
    expect(emailInput.exists()).toBe(true);
    expect((emailInput.element as HTMLInputElement).value).toBe('old@a.com');

    // Simulate user typing a new email
    await emailInput.setValue('NEW@a.com');
    await nextTick();

    // Click save button (btn-primary)
    const saveBtn = wrapper.findAll('button').find(b => b.attributes('class')?.includes('btn-primary'));
    await saveBtn!.trigger('click');
    await flushPromises();

    expect(api.put).toHaveBeenCalled();
    const [url, body] = vi.mocked(api.put).mock.calls[0] as [string, { sections: Array<{ type: string; content: Record<string, unknown> }> }];
    expect(url).toBe('/api/resumes/7');

    const contactSection = body.sections.find(s => s.type === 'contact');
    expect(contactSection?.content.email).toBe('NEW@a.com');
  });

  it('sends edited experience description in PUT body', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: mkResume() });
    vi.mocked(api.put).mockResolvedValue({ data: mkResume() });

    const { wrapper } = await mountView();
    await flushPromises();
    await nextTick();
    await flushPromises();

    // Find description textarea
    const textareas = wrapper.findAll('textarea');
    const descTextarea = textareas[textareas.length - 1]; // last textarea is exp description
    await descTextarea.setValue('NEW description');
    await nextTick();

    const saveBtn = wrapper.findAll('button').find(b => b.attributes('class')?.includes('btn-primary'));
    await saveBtn!.trigger('click');
    await flushPromises();

    expect(api.put).toHaveBeenCalled();
    const [, body] = vi.mocked(api.put).mock.calls[0] as [string, { sections: Array<{ type: string; content: Record<string, unknown> }> }];
    const expSection = body.sections.find(s => s.type === 'experience');
    expect(expSection?.content.description).toBe('NEW description');
  });

  it('sends edited summary text in PUT body', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: mkResume() });
    vi.mocked(api.put).mockResolvedValue({ data: mkResume() });

    const { wrapper } = await mountView();
    await flushPromises();
    await nextTick();
    await flushPromises();

    const textareas = wrapper.findAll('textarea');
    const summaryTextarea = textareas[0]; // first textarea is summary
    await summaryTextarea.setValue('NEW summary');
    await nextTick();

    const saveBtn = wrapper.findAll('button').find(b => b.attributes('class')?.includes('btn-primary'));
    await saveBtn!.trigger('click');
    await flushPromises();

    expect(api.put).toHaveBeenCalled();
    const [, body] = vi.mocked(api.put).mock.calls[0] as [string, { sections: Array<{ type: string; content: Record<string, unknown> }> }];
    const sumSection = body.sections.find(s => s.type === 'summary');
    expect(sumSection?.content.text).toBe('NEW summary');
  });
});
