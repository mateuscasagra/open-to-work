import axios, { AxiosError, type AxiosInstance } from 'axios';

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

// Extrai mensagens de validação Laravel (422 -> errors{field:[msg]}) ou
// a mensagem do backend (409, etc.). Retorna `fallback` se não conseguir extrair.
// Mensagens vêm com newline entre si — renderize com `whitespace-pre-line`.
export function extractApiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof AxiosError && error.response) {
    const { status, data } = error.response;

    if (status === 422 && data?.errors && typeof data.errors === 'object') {
      const messages = Object.values(data.errors as Record<string, string[]>)
        .flat()
        .filter((m): m is string => typeof m === 'string');
      if (messages.length > 0) return messages.join('\n');
    }

    if (typeof data?.message === 'string' && data.message.length > 0) {
      return data.message;
    }
  }

  return fallback;
}
