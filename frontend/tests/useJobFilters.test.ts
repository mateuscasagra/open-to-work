import { describe, it, expect } from 'vitest';
import { useJobFilters } from '@/modules/jobs/composables/useJobFilters';

describe('useJobFilters', () => {
  it('starts with empty filters and empty query params', () => {
    const { state, queryParams } = useJobFilters();
    expect(state.q).toBe('');
    expect(state.modality).toBe('');
    expect(state.seniority).toBe('');
    expect(state.stack).toEqual([]);
    expect(state.matchOnly).toBe(false);
    expect(queryParams.value).toEqual({});
  });

  it('builds query params only from filled fields', () => {
    const { state, queryParams } = useJobFilters();
    state.q = '  Backend  ';
    state.modality = 'remote';
    state.stack.push('php', 'laravel');
    expect(queryParams.value).toEqual({
      q: 'Backend',
      modality: 'remote',
      stack: 'php,laravel',
    });
  });

  it('toggles stack tags', () => {
    const { state, toggleStack } = useJobFilters();
    toggleStack('php');
    toggleStack('vue');
    expect(state.stack).toEqual(['php', 'vue']);
    toggleStack('php');
    expect(state.stack).toEqual(['vue']);
  });

  it('resets all fields', () => {
    const { state, reset, queryParams } = useJobFilters();
    state.q = 'x';
    state.modality = 'remote';
    state.seniority = 'senior';
    state.stack.push('php');
    state.matchOnly = true;
    reset();
    expect(queryParams.value).toEqual({});
    expect(state.matchOnly).toBe(false);
  });
});
