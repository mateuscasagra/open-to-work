import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/modules/auth/stores/auth';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  ensureCsrf: vi.fn().mockResolvedValue(undefined),
}));

describe('authStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('fetchMe sets user on success', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      data: { user: { id: 1, name: 'Diego', email: 'd@e.com', locale: 'pt_BR' } },
    });

    const auth = useAuthStore();
    await auth.fetchMe();

    expect(auth.user?.name).toBe('Diego');
    expect(auth.initialized).toBe(true);
  });

  it('fetchMe leaves user null on failure', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('401'));

    const auth = useAuthStore();
    await auth.fetchMe();

    expect(auth.user).toBeNull();
    expect(auth.initialized).toBe(true);
  });

  it('login stores the authenticated user', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { user: { id: 2, name: 'Ana', email: 'a@e.com', locale: 'en' } },
    });

    const auth = useAuthStore();
    await auth.login('a@e.com', 'secret');

    expect(auth.user?.id).toBe(2);
    expect(api.post).toHaveBeenCalledWith('/api/auth/login', {
      email: 'a@e.com',
      password: 'secret',
      remember: false,
    });
  });

  it('logout clears user state', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: { ok: true } });

    const auth = useAuthStore();
    auth.user = { id: 1, name: 'X', email: 'x@x.com', locale: null };

    await auth.logout();

    expect(auth.user).toBeNull();
  });

  it('oauthUrl builds provider redirect URL', () => {
    const auth = useAuthStore();
    const url = auth.oauthUrl('google');

    expect(url).toContain('/api/auth/google/redirect');
  });
});
