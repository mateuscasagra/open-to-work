import { describe, it, expect, vi, beforeEach } from 'vitest';
import { AxiosError, AxiosHeaders } from 'axios';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createApp, defineComponent, h } from 'vue';
import { useSubscribeToPro } from '@/modules/subscription/composables/useSubscribeToPro';
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
    }),
  );
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  app.use(VueQueryPlugin, { queryClient });
  app.mount(document.createElement('div'));
  return result;
}

function axiosErr(status: number, data: unknown): AxiosError {
  const err = new AxiosError('subscribe');
  err.response = {
    status,
    data,
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
  };
  return err;
}

describe('useSubscribeToPro', () => {
  beforeEach(() => vi.clearAllMocks());

  it('returns parsed PIX checkout payload on 201', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: {
        pix_qr_code_base64: 'iVBORw0KGgo=',
        pix_copy_paste: '00020126...',
        due_date: '2026-05-21',
        payment_id: 'pay_111',
        asaas_subscription_id: 'sub_777',
      },
    });

    const mutation = mount(() => useSubscribeToPro());
    const result = await mutation.mutateAsync({ cpf: '24971563792' });

    expect(api.post).toHaveBeenCalledWith('/api/subscriptions', { cpf: '24971563792' });
    expect(result.pix_qr_code_base64).toBe('iVBORw0KGgo=');
    expect(result.asaas_subscription_id).toBe('sub_777');
  });

  it('throws kind:already on 409', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(
      axiosErr(409, { message: 'Já tem Pro' }),
    );

    const mutation = mount(() => useSubscribeToPro());
    await expect(mutation.mutateAsync({ cpf: '24971563792' })).rejects.toMatchObject({ kind: 'already' });
  });

  it('throws kind:gateway on 502', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(axiosErr(502, { message: 'down' }));

    const mutation = mount(() => useSubscribeToPro());
    await expect(mutation.mutateAsync({ cpf: '24971563792' })).rejects.toMatchObject({ kind: 'gateway' });
  });

  it('throws kind:unknown on network error', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(new Error('Network'));

    const mutation = mount(() => useSubscribeToPro());
    await expect(mutation.mutateAsync({ cpf: '24971563792' })).rejects.toMatchObject({ kind: 'unknown' });
  });
});
