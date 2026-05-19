import { describe, it, expect } from 'vitest';
import { useJobFilters } from '@/modules/jobs/composables/useJobFilters';

describe('useJobFilters', () => {
  it('starts with empty filters and empty query params', () => {
    const { state, queryParams } = useJobFilters();
    expect(state.q).toBe('');
    expect(state.modality).toBe('');
    expect(state.seniority).toBe('');
    expect(state.country).toBe('');
    expect(state.matchOnly).toBe(false);
    expect(queryParams.value).toEqual({});
  });

  it('builds query params only from filled fields', () => {
    const { state, queryParams } = useJobFilters();
    state.q = '  Backend  ';
    state.modality = 'remote';
    state.seniority = 'senior';
    state.country = 'BR';
    expect(queryParams.value).toEqual({
      q: 'Backend',
      modality: 'remote',
      seniority: 'senior',
      country: 'BR',
    });
  });

  it('resets all fields', () => {
    const { state, reset, queryParams } = useJobFilters();
    state.q = 'x';
    state.modality = 'remote';
    state.seniority = 'senior';
    state.country = 'DE';
    state.matchOnly = true;
    reset();
    expect(queryParams.value).toEqual({});
    expect(state.country).toBe('');
    expect(state.matchOnly).toBe(false);
  });
});
