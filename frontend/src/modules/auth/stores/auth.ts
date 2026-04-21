import { defineStore } from 'pinia';
import { api } from '@/shared/api/client';
import { UserSchema, type User } from '@/shared/api/schemas';

interface AuthState {
  user: User | null;
  initialized: boolean;
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    initialized: false,
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
    },

    async register(payload: { name: string; email: string; password: string; password_confirmation: string }) {
      const { data } = await api.post('/api/auth/register', payload);
      this.user = UserSchema.parse(data.user);
    },

    async logout() {
      await api.post('/api/auth/logout');
      this.user = null;
    },

    oauthUrl(provider: 'google' | 'linkedin' | 'github'): string {
      const base = import.meta.env.VITE_API_URL ?? '';
      return `${base}/api/auth/${provider}/redirect`;
    },
  },
});
