import { defineStore } from 'pinia';
import { api } from '@/shared/api/client';
import {
  SubscriptionSchema,
  UserSchema,
  type Subscription,
  type User,
} from '@/shared/api/schemas';

interface AuthState {
  user: User | null;
  subscription: Subscription | null;
  initialized: boolean;
  pendingVerificationEmail: string | null;
  locationComplete: boolean;
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

// Parse user + subscription do envelope retornado por todos os endpoints
// autenticados. Backend sempre devolve `subscription` junto, então centralizamos
// o parsing aqui pra manter store sincronizado.
function parseEnvelope(data: unknown): { user: User; subscription: Subscription } {
  const obj = data as { user: unknown; subscription: unknown };
  return {
    user: UserSchema.parse(obj.user),
    subscription: SubscriptionSchema.parse(obj.subscription),
  };
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    subscription: null,
    initialized: false,
    pendingVerificationEmail: loadPendingEmail(),
    locationComplete: false,
  }),

  actions: {
    async fetchMe() {
      try {
        const { data } = await api.get('/api/me');
        const env = parseEnvelope(data);
        this.user = env.user;
        this.subscription = env.subscription;
        await this.refreshLocationStatus();
      } catch {
        this.user = null;
        this.subscription = null;
        this.locationComplete = false;
      } finally {
        this.initialized = true;
      }
    },

    async refreshLocationStatus() {
      if (!this.user) {
        this.locationComplete = false;
        return;
      }
      try {
        const { data } = await api.get('/api/profile');
        this.locationComplete = Boolean(
          data?.country_code && data?.state_name && data?.city,
        );
      } catch {
        this.locationComplete = false;
      }
    },

    async login(email: string, password: string, remember = false) {
      const { data } = await api.post('/api/auth/login', { email, password, remember });
      const env = parseEnvelope(data);
      this.user = env.user;
      this.subscription = env.subscription;
      this.setPendingVerificationEmail(null);
      await this.refreshLocationStatus();
    },

    async register(payload: { name: string; email: string; password: string; password_confirmation: string }) {
      const { data } = await api.post('/api/auth/register', payload);

      // Backend toggles verification via auth.email_verification_enabled.
      // When disabled, register returns the authenticated user directly.
      if (data?.user) {
        const env = parseEnvelope(data);
        this.user = env.user;
        this.subscription = env.subscription;
        this.setPendingVerificationEmail(null);
        await this.refreshLocationStatus();
        return null;
      }

      const email = (data?.email as string | undefined) ?? payload.email;
      this.setPendingVerificationEmail(email);
      return email;
    },

    async verifyEmail(email: string, code: string) {
      const { data } = await api.post('/api/auth/verify-email', { email, code });
      const env = parseEnvelope(data);
      this.user = env.user;
      this.subscription = env.subscription;
      this.setPendingVerificationEmail(null);
      await this.refreshLocationStatus();
    },

    async resendVerificationCode(email: string) {
      await api.post('/api/auth/resend-code', { email });
    },

    async forgotPassword(email: string) {
      await api.post('/api/auth/forgot-password', { email });
    },

    async resetPassword(payload: { email: string; token: string; password: string; password_confirmation: string }) {
      const { data } = await api.post('/api/auth/reset-password', payload);
      const env = parseEnvelope(data);
      this.user = env.user;
      this.subscription = env.subscription;
      this.setPendingVerificationEmail(null);
      await this.refreshLocationStatus();
    },

    setPendingVerificationEmail(email: string | null) {
      this.pendingVerificationEmail = email;
      savePendingEmail(email);
    },

    async logout() {
      await api.post('/api/auth/logout');
      this.user = null;
      this.subscription = null;
      this.locationComplete = false;
      this.setPendingVerificationEmail(null);
    },

    oauthUrl(provider: 'google' | 'linkedin' | 'github'): string {
      const base = import.meta.env.VITE_API_URL ?? '';
      return `${base}/api/auth/${provider}/redirect`;
    },
  },
});
