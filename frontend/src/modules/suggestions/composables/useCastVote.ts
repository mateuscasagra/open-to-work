import { useMutation, useQueryClient, type QueryKey } from '@tanstack/vue-query';
import { AxiosError } from 'axios';
import { api } from '@/shared/api/client';
import {
  SuggestionSchema,
  type Suggestion,
  type SuggestionsPage,
  type SuggestionVoteValue,
} from '@/shared/api/schemas';

export interface CastVotePayload {
  suggestionId: number;
  value: 'up' | 'down';
}

export type CastVoteError = { kind: 'forbidden' | 'unknown'; message: string };

type Snapshot = Array<[QueryKey, SuggestionsPage | undefined]>;

function applyVote(
  s: Suggestion,
  newValue: SuggestionVoteValue,
): Suggestion {
  const previous = s.my_vote;
  let upvotes = s.upvotes_count;
  let downvotes = s.downvotes_count;
  let myVote: 1 | -1 | null;

  if (previous === newValue) {
    if (previous === 1) upvotes -= 1;
    else downvotes -= 1;
    myVote = null;
  } else if (previous === null) {
    if (newValue === 1) upvotes += 1;
    else downvotes += 1;
    myVote = newValue;
  } else {
    if (previous === 1) {
      upvotes -= 1;
      downvotes += 1;
    } else {
      downvotes -= 1;
      upvotes += 1;
    }
    myVote = newValue;
  }

  return {
    ...s,
    upvotes_count: upvotes,
    downvotes_count: downvotes,
    score: upvotes - downvotes,
    my_vote: myVote,
  };
}

export function useCastVote() {
  const qc = useQueryClient();

  return useMutation<Suggestion, CastVoteError, CastVotePayload, { snapshot: Snapshot }>({
    mutationFn: async ({ suggestionId, value }) => {
      try {
        const { data } = await api.post(`/api/suggestions/${suggestionId}/vote`, { value });
        return SuggestionSchema.parse(data);
      } catch (e) {
        if (e instanceof AxiosError && e.response?.status === 403) {
          throw { kind: 'forbidden', message: e.response.data?.message ?? 'Forbidden' } as CastVoteError;
        }
        throw { kind: 'unknown', message: 'Failed to cast vote' } as CastVoteError;
      }
    },
    onMutate: async ({ suggestionId, value }) => {
      await qc.cancelQueries({ queryKey: ['suggestions'] });

      const newValue: SuggestionVoteValue = value === 'up' ? 1 : -1;
      const snapshot: Snapshot = qc.getQueriesData<SuggestionsPage>({ queryKey: ['suggestions'] });

      qc.setQueriesData<SuggestionsPage>({ queryKey: ['suggestions'] }, (page) => {
        if (!page || !Array.isArray(page.data)) return page;
        return {
          ...page,
          data: page.data.map((s) => (s.id === suggestionId ? applyVote(s, newValue) : s)),
        };
      });

      return { snapshot };
    },
    onError: (_err, _payload, context) => {
      if (!context?.snapshot) return;
      for (const [key, page] of context.snapshot) {
        qc.setQueryData(key, page);
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ['suggestions'] });
    },
  });
}
