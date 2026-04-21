import { describe, it, expect } from 'vitest';
import { api } from '@/shared/api/client';

describe('api client', () => {
  it('is configured with credentials and CSRF', () => {
    expect(api.defaults.withCredentials).toBe(true);
    expect(api.defaults.withXSRFToken).toBe(true);
    expect(api.defaults.headers.Accept).toBe('application/json');
  });
});
