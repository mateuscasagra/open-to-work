import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { nextTick } from 'vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import { createI18n } from 'vue-i18n';
import ApplicationsKanbanView from '@/modules/applications/views/ApplicationsKanbanView.vue';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}));

beforeEach(() => {
  localStorage.clear();
  vi.clearAllMocks();
});

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/applications', name: 'applications', component: ApplicationsKanbanView },
      { path: '/applications/:id', name: 'application-detail', component: { template: '<div/>' } },
    ],
  });
  const i18n = createI18n({ legacy: false, locale: 'pt_BR', messages: { pt_BR: {} } });
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  await router.push('/applications');
  await router.isReady();

  const wrapper = mount(ApplicationsKanbanView, {
    global: { plugins: [router, i18n, [VueQueryPlugin, { queryClient }]] },
  });

  return { wrapper, queryClient };
}

function findArchiveToggle(wrapper: ReturnType<typeof mount>) {
  return wrapper
    .findAll('button')
    .find(b => /filter_active|filter_archived/.test(b.text()));
}

describe('ApplicationsKanbanView — archived filter button', () => {
  it('starts in active mode (no ?archived param) and shows label "filter_archived"', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });

    const { wrapper } = await mountView();
    await flushPromises();

    expect(api.get).toHaveBeenCalledWith('/api/applications', { params: {} });

    const btn = findArchiveToggle(wrapper);
    expect(btn?.exists()).toBe(true);
    expect(btn!.text()).toBe('applications.filter_archived');
  });

  it('switches to archived mode on click and refetches with ?archived=1', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });

    const { wrapper } = await mountView();
    await flushPromises();

    const btn = findArchiveToggle(wrapper);
    await btn!.trigger('click');
    await nextTick();
    await flushPromises();

    // Label flipped
    expect(findArchiveToggle(wrapper)!.text()).toBe('applications.filter_active');

    // API was called with archived=1
    const calls = vi.mocked(api.get).mock.calls;
    const archivedCall = calls.find(([, opts]) => (opts as { params?: { archived?: number } })?.params?.archived === 1);
    expect(archivedCall).toBeDefined();
  });

  it('disables "Nova candidatura" button in archived mode', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });

    const { wrapper } = await mountView();
    await flushPromises();

    const newAppBtn = wrapper.findAll('button').find(b => /add_manual/.test(b.text()));
    expect(newAppBtn?.attributes('disabled')).toBeUndefined();

    await findArchiveToggle(wrapper)!.trigger('click');
    await nextTick();
    await flushPromises();

    const newAppBtnAfter = wrapper.findAll('button').find(b => /add_manual/.test(b.text()));
    expect(newAppBtnAfter?.attributes('disabled')).toBeDefined();
  });

  it('renders cards as non-draggable in archived mode', async () => {
    const archivedApp = {
      id: 99,
      status: 'applied',
      applied_at: '2026-04-18T12:00:00Z',
      archived_at: '2026-04-30T10:00:00Z',
      notes: null,
      expected_salary: null,
      source: null,
      manual_title: 'Test app',
      manual_company: 'Co',
    };
    vi.mocked(api.get).mockImplementation((_url, opts) => {
      const archived = (opts as { params?: { archived?: number } })?.params?.archived === 1;
      return Promise.resolve({ data: { data: archived ? [archivedApp] : [] } });
    });

    const { wrapper } = await mountView();
    await flushPromises();

    await findArchiveToggle(wrapper)!.trigger('click');
    await nextTick();
    await flushPromises();

    const card = wrapper.find('[draggable]');
    expect(card.exists()).toBe(true);
    expect(card.attributes('draggable')).toBe('false');
  });

  it('disables drag-drop on mobile (viewport < lg)', async () => {
    // Mock matchMedia to return mobile viewport
    const original = window.matchMedia;
    window.matchMedia = vi.fn().mockImplementation((query: string) => ({
      matches: false, // not desktop
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      onchange: null,
      dispatchEvent: vi.fn(),
    }));

    const app = {
      id: 50,
      status: 'applied',
      applied_at: '2026-04-18T12:00:00Z',
      archived_at: null,
      notes: null,
      expected_salary: null,
      source: null,
      manual_title: 'Test',
      manual_company: 'Co',
    };
    vi.mocked(api.get).mockResolvedValue({ data: { data: [app] } });

    const { wrapper } = await mountView();
    await flushPromises();

    const card = wrapper.find('[draggable]');
    expect(card.exists()).toBe(true);
    expect(card.attributes('draggable')).toBe('false');

    window.matchMedia = original;
  });
});
