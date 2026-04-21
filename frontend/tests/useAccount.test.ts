import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useAccount } from '@/modules/account/composables/useAccount';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), delete: vi.fn() },
}));

describe('useAccount', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // jsdom ≥20 supports URL.createObjectURL if we polyfill
    if (!('createObjectURL' in URL)) {
      Object.defineProperty(URL, 'createObjectURL', { value: () => 'blob:stub', configurable: true });
      Object.defineProperty(URL, 'revokeObjectURL', { value: () => undefined, configurable: true });
    }
  });

  it('downloads an export blob and triggers the browser download', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: new Blob(['{}'], { type: 'application/json' }) });
    const anchorClick = vi.fn();
    const originalCreate = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
      const el = originalCreate(tag);
      if (tag === 'a') {
        (el as HTMLAnchorElement).click = anchorClick;
      }
      return el;
    });

    const account = useAccount();
    await account.exportData();

    expect(api.get).toHaveBeenCalledWith('/api/account/export', { responseType: 'blob' });
    expect(anchorClick).toHaveBeenCalledTimes(1);
    expect(account.exporting.value).toBe(false);
    expect(account.error.value).toBeNull();
  });

  it('calls DELETE /api/account and exposes errors', async () => {
    vi.mocked(api.delete).mockRejectedValueOnce(new Error('boom'));

    const account = useAccount();
    await expect(account.deleteAccount()).rejects.toThrow('boom');
    expect(api.delete).toHaveBeenCalledWith('/api/account');
    expect(account.error.value).toBe('boom');
    expect(account.deleting.value).toBe(false);
  });
});
