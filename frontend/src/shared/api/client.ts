import axios, { type AxiosInstance } from 'axios';

const baseURL = import.meta.env.VITE_API_URL ?? '';

export const api: AxiosInstance = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

// Garante que o cookie CSRF foi inicializado antes de qualquer request de mutação
let csrfInitialized = false;

export async function ensureCsrf(): Promise<void> {
  if (csrfInitialized) return;
  await api.get('/sanctum/csrf-cookie');
  csrfInitialized = true;
}

api.interceptors.request.use(async (config) => {
  const method = config.method?.toUpperCase() ?? 'GET';
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    await ensureCsrf();
  }
  return config;
});
