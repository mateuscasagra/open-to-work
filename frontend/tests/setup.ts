import { beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// jsdom doesn't ship matchMedia. Default to desktop in tests; individual
// tests can still override via window.matchMedia = vi.fn(...).
if (typeof window !== 'undefined' && !window.matchMedia) {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: true,
      media: query,
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  });
}

beforeEach(() => {
  setActivePinia(createPinia());
});
