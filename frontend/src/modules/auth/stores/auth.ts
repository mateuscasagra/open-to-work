import { defineStore } from 'pinia';
import { api } from '@/shared/api/client';
import { UserSchema, type User } from '@/shared/api/schemas';

interface AuthState {
  user: User | null;
  initialized: boolean;
  pendingVerificationEmail: string | null;
}

const PENDING_EMAIL_KEY = 'auth.pending_verification_email';

function loadPendingEmail(): string | null {
  try {
    return localStorage.getItem(PENDING_EMAIL_KEY);
  } catch {
    return null;
  }
}

function savePendingEmail(email: string | null): void {
  try {
    if (email) {
      localStorage.setItem(PENDING_EMAIL_KEY, email);
    } else {
      localStorage.removeItem(PENDING_EMAIL_KEY);
    }
  } catch {
    // localStorage unavailable; verification can still happen via the form
  }
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    initialized: false,
    pendingVerificationEmail: loadPendingEmail(),
  }),

  actions: {
    async fetchMe() {
      try {
        const { data } = await api.get('/api/me');
        this.user = UserSchema.parse(data.user);
      } catch {
        this.user = null;
      } finally {
        this.initialized = true;
      }
    },

    async login(email: string, password: string, remember = false) {
      const { data } = await api.post('/api/auth/login', { email, password, remember });
      this.user = UserSchema.parse(data.user);
      this.setPendingVerificationEmail(null);
    },

    async register(payload: { name: string; email: string; password: string; password_confirmation: string }) {
      const { data } = await api.post('/api/auth/register', payload);

      // Backend toggles verification via auth.email_verification_enabled.
      // When disabled, register returns the authenticated user directly.
      if (data?.user) {
        this.user = UserSchema.parse(data.user);
        this.setPendingVerificationEmail(null);
        return null;
      }

      const email = (data?.email as string | undefined) ?? payload.email;
      this.setPendingVerificationEmail(email);
      return email;
    },

    async verifyEmail(email: string, code: string) {
      const { data } = await api.post('/api/auth/verify-email', { email, code });
      this.user = UserSchema.parse(data.user);
      this.setPendingVerificationEmail(null);
    },

    async resendVerificationCode(email: string) {
      await api.post('/api/auth/resend-code', { email });
    },

    async forgotPassword(email: string) {
      await api.post('/api/auth/forgot-password', { email });
    },

    async resetPassword(payload: { email: string; token: string; password: string; password_confirmation: string }) {
      const { data } = await api.post('/api/auth/reset-password', payload);
      this.user = UserSchema.parse(data.user);
      this.setPendingVerificationEmail(null);
    },

    setPendingVerificationEmail(email: string | null) {
      this.pendingVerificationEmail = email;
      savePendingEmail(email);
    },

    async logout() {
      await api.post('/api/auth/logout');
      this.user = null;
      this.setPendingVerificationEmail(null);
    },

    oauthUrl(provider: 'google' | 'linkedin' | 'github'): string {
      const base = import.meta.env.VITE_API_URL ?? '';
      return `${base}/api/auth/${provider}/redirect`;
    },
  },
});
