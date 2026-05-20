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

const freeSubscription = {
  plan: 'free' as const,
  status: 'active' as const,
  current_period_end: null,
  canceled_at: null,
  pro_price_cents: 2500,
  quota: {
    used: 0,
    limit: 15,
    reset_at: '2026-06-01T00:00:00Z',
    plan: 'free' as const,
  },
};

describe('authStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('fetchMe sets user on success', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({
        data: {
          user: { id: 1, name: 'Diego', email: 'd@e.com', locale: 'pt_BR' },
          subscription: freeSubscription,
        },
      })
      .mockResolvedValueOnce({
        data: { country_code: 'BR', state_name: 'SP', city: 'São Paulo' },
      });

    const auth = useAuthStore();
    await auth.fetchMe();

    expect(auth.user?.name).toBe('Diego');
    expect(auth.initialized).toBe(true);
    expect(auth.locationComplete).toBe(true);
  });

  it('fetchMe leaves user null on failure', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('401'));

    const auth = useAuthStore();
    await auth.fetchMe();

    expect(auth.user).toBeNull();
    expect(auth.initialized).toBe(true);
    expect(auth.locationComplete).toBe(false);
  });

  it('fetchMe leaves locationComplete=false when profile lacks city', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({
        data: {
          user: { id: 1, name: 'Diego', email: 'd@e.com', locale: 'pt_BR' },
          subscription: freeSubscription,
        },
      })
      .mockResolvedValueOnce({
        data: { country_code: 'BR', state_name: 'SP', city: null },
      });

    const auth = useAuthStore();
    await auth.fetchMe();

    expect(auth.locationComplete).toBe(false);
  });

  it('login stores the authenticated user and refreshes location status', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: {
        user: { id: 2, name: 'Ana', email: 'a@e.com', locale: 'en' },
        subscription: freeSubscription,
      },
    });
    vi.mocked(api.get).mockResolvedValueOnce({
      data: { country_code: 'US', state_name: 'CA', city: 'San Francisco' },
    });

    const auth = useAuthStore();
    await auth.login('a@e.com', 'secret');

    expect(auth.user?.id).toBe(2);
    expect(auth.locationComplete).toBe(true);
    expect(api.post).toHaveBeenCalledWith('/api/auth/login', {
      email: 'a@e.com',
      password: 'secret',
      remember: false,
    });
    expect(api.get).toHaveBeenCalledWith('/api/profile');
  });

  it('logout clears user and locationComplete', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: { ok: true } });

    const auth = useAuthStore();
    auth.user = { id: 1, name: 'X', email: 'x@x.com', locale: null, is_admin: false };
    auth.locationComplete = true;

    await auth.logout();

    expect(auth.user).toBeNull();
    expect(auth.locationComplete).toBe(false);
  });

  it('refreshLocationStatus sets false when no user is loaded', async () => {
    const auth = useAuthStore();
    await auth.refreshLocationStatus();

    expect(auth.locationComplete).toBe(false);
    expect(api.get).not.toHaveBeenCalled();
  });

  it('refreshLocationStatus sets false when /api/profile fails', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('500'));

    const auth = useAuthStore();
    auth.user = { id: 1, name: 'X', email: 'x@x.com', locale: null, is_admin: false };

    await auth.refreshLocationStatus();

    expect(auth.locationComplete).toBe(false);
  });

  it('refreshLocationStatus sets true when all required fields present', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      data: { country_code: 'BR', state_name: 'RJ', city: 'Rio de Janeiro' },
    });

    const auth = useAuthStore();
    auth.user = { id: 1, name: 'X', email: 'x@x.com', locale: null, is_admin: false };

    await auth.refreshLocationStatus();

    expect(auth.locationComplete).toBe(true);
  });

  it('oauthUrl builds provider redirect URL', () => {
    const auth = useAuthStore();
    const url = auth.oauthUrl('google');

    expect(url).toContain('/api/auth/google/redirect');
  });

  it('register does not authenticate user and stores pending email', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { status: 'verification_required', email: 'new@e.com' },
    });

    const auth = useAuthStore();
    const email = await auth.register({
      name: 'Diego',
      email: 'new@e.com',
      password: 'Secret123!',
      password_confirmation: 'Secret123!',
    });

    expect(auth.user).toBeNull();
    expect(email).toBe('new@e.com');
    expect(auth.pendingVerificationEmail).toBe('new@e.com');
    expect(localStorage.getItem('auth.pending_verification_email')).toBe('new@e.com');
  });

  it('verifyEmail sets the user and clears the pending email', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: {
        user: { id: 7, name: 'Verified', email: 'v@e.com', locale: 'pt_BR' },
        subscription: freeSubscription,
      },
    });
    vi.mocked(api.get).mockResolvedValueOnce({
      data: { country_code: null, state_name: null, city: null },
    });

    const auth = useAuthStore();
    auth.setPendingVerificationEmail('v@e.com');

    await auth.verifyEmail('v@e.com', '123456');

    expect(auth.user?.id).toBe(7);
    expect(auth.pendingVerificationEmail).toBeNull();
    expect(localStorage.getItem('auth.pending_verification_email')).toBeNull();
    expect(auth.locationComplete).toBe(false);
    expect(api.post).toHaveBeenCalledWith('/api/auth/verify-email', {
      email: 'v@e.com',
      code: '123456',
    });
  });

  it('resendVerificationCode posts to the resend endpoint', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: { status: 'sent' } });

    const auth = useAuthStore();
    await auth.resendVerificationCode('pending@e.com');

    expect(api.post).toHaveBeenCalledWith('/api/auth/resend-code', { email: 'pending@e.com' });
  });

  it('login clears any pending verification email', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: {
        user: { id: 3, name: 'OK', email: 'ok@e.com', locale: 'pt_BR' },
        subscription: freeSubscription,
      },
    });
    vi.mocked(api.get).mockResolvedValueOnce({
      data: { country_code: 'BR', state_name: 'SP', city: 'Campinas' },
    });

    const auth = useAuthStore();
    auth.setPendingVerificationEmail('was@pending.com');

    await auth.login('ok@e.com', 'secret');

    expect(auth.user?.id).toBe(3);
    expect(auth.pendingVerificationEmail).toBeNull();
  });

  it('forgotPassword POSTs email to /api/auth/forgot-password', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: { status: 'sent' } });

    const auth = useAuthStore();
    await auth.forgotPassword('me@e.com');

    expect(api.post).toHaveBeenCalledWith('/api/auth/forgot-password', { email: 'me@e.com' });
  });

  it('resetPassword POSTs payload and sets the user from response', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: {
        user: { id: 9, name: 'Reset', email: 'r@e.com', locale: 'pt_BR' },
        subscription: freeSubscription,
      },
    });
    vi.mocked(api.get).mockResolvedValueOnce({
      data: { country_code: 'BR', state_name: 'SP', city: 'São Paulo' },
    });

    const auth = useAuthStore();
    await auth.resetPassword({
      email: 'r@e.com',
      token: 'tok',
      password: 'NewPass1',
      password_confirmation: 'NewPass1',
    });

    expect(api.post).toHaveBeenCalledWith('/api/auth/reset-password', {
      email: 'r@e.com',
      token: 'tok',
      password: 'NewPass1',
      password_confirmation: 'NewPass1',
    });
    expect(auth.user?.id).toBe(9);
    expect(auth.locationComplete).toBe(true);
  });
});
