import { computed, reactive } from 'vue';
import type { Modality, Seniority } from '@/shared/api/schemas';

export interface JobFilters {
  q: string;
  modality: Modality | '';
  seniority: Seniority | '';
  matchOnly: boolean;
}

function makeDefaults(): JobFilters {
  return {
    q: '',
    modality: '',
    seniority: '',
    matchOnly: false,
  };
}

export function useJobFilters() {
  const state = reactive<JobFilters>(makeDefaults());

  const queryParams = computed(() => {
    const params: Record<string, string> = {};
    if (state.q.trim() !== '') params.q = state.q.trim();
    if (state.modality !== '') params.modality = state.modality;
    if (state.seniority !== '') params.seniority = state.seniority;
    return params;
  });

  function reset(): void {
    state.q = '';
    state.modality = '';
    state.seniority = '';
    state.matchOnly = false;
  }

  return { state, queryParams, reset };
}
