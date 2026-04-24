import { computed, reactive } from 'vue';
import type { Locale, Modality, Seniority } from '@/shared/api/schemas';

export interface JobFilters {
  q: string;
  modality: Modality | '';
  seniority: Seniority | '';
  stack: string[];
  language: Locale[];
  matchOnly: boolean;
}

function makeDefaults(): JobFilters {
  return {
    q: '',
    modality: '',
    seniority: '',
    stack: [],
    language: [],
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
    if (state.stack.length > 0) params.stack = state.stack.join(',');
    if (state.language.length > 0) params.language = state.language.join(',');
    return params;
  });

  function reset(): void {
    state.q = '';
    state.modality = '';
    state.seniority = '';
    state.stack = [];
    state.language = [];
    state.matchOnly = false;
  }

  function toggleStack(tag: string): void {
    const i = state.stack.indexOf(tag);
    if (i >= 0) state.stack.splice(i, 1);
    else state.stack.push(tag);
  }

  return { state, queryParams, reset, toggleStack };
}
