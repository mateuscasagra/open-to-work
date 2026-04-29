import { describe, it, expect } from 'vitest';
import {
  SuggestionSchema,
  SuggestionsPageSchema,
  SuggestionQuotaSchema,
} from '@/shared/api/schemas';

describe('SuggestionSchema', () => {
  const base = {
    id: 1,
    user: { id: 7, name: 'Diego' },
    title: 'Add dark mode',
    body: 'It would be nice to have a dark mode.',
    upvotes_count: 12,
    downvotes_count: 3,
    score: 9,
    my_vote: null as 1 | -1 | null,
    rank: null as 1 | 2 | 3 | null,
    created_at: '2026-04-29T10:00:00Z',
  };

  it('parses with my_vote = null', () => {
    const result = SuggestionSchema.parse(base);
    expect(result.my_vote).toBeNull();
  });

  it('parses with my_vote = 1', () => {
    const result = SuggestionSchema.parse({ ...base, my_vote: 1 });
    expect(result.my_vote).toBe(1);
  });

  it('parses with my_vote = -1', () => {
    const result = SuggestionSchema.parse({ ...base, my_vote: -1 });
    expect(result.my_vote).toBe(-1);
  });

  it('rejects my_vote with invalid integers (e.g. 0 or 2)', () => {
    expect(() => SuggestionSchema.parse({ ...base, my_vote: 0 })).toThrow();
    expect(() => SuggestionSchema.parse({ ...base, my_vote: 2 })).toThrow();
  });

  it('parses with rank in [1, 2, 3, null]', () => {
    expect(SuggestionSchema.parse({ ...base, rank: 1 }).rank).toBe(1);
    expect(SuggestionSchema.parse({ ...base, rank: 2 }).rank).toBe(2);
    expect(SuggestionSchema.parse({ ...base, rank: 3 }).rank).toBe(3);
    expect(SuggestionSchema.parse({ ...base, rank: null }).rank).toBeNull();
  });

  it('rejects rank with values out of [1,2,3,null]', () => {
    expect(() => SuggestionSchema.parse({ ...base, rank: 4 })).toThrow();
    expect(() => SuggestionSchema.parse({ ...base, rank: 0 })).toThrow();
  });
});

describe('SuggestionsPageSchema', () => {
  it('parses a paginated list', () => {
    const page = SuggestionsPageSchema.parse({
      data: [
        {
          id: 1,
          user: { id: 7, name: 'Diego' },
          title: 'Add dark mode',
          body: 'It would be nice.',
          upvotes_count: 0,
          downvotes_count: 0,
          score: 0,
          my_vote: null,
          rank: 1,
          created_at: '2026-04-29T10:00:00Z',
        },
      ],
      current_page: 1,
      last_page: 5,
      total: 100,
    });
    expect(page.data).toHaveLength(1);
    expect(page.total).toBe(100);
  });
});

describe('SuggestionQuotaSchema', () => {
  it('parses with next_slot_at = null', () => {
    const q = SuggestionQuotaSchema.parse({ used: 2, limit: 5, next_slot_at: null });
    expect(q.used).toBe(2);
    expect(q.next_slot_at).toBeNull();
  });

  it('parses with next_slot_at as string', () => {
    const q = SuggestionQuotaSchema.parse({ used: 5, limit: 5, next_slot_at: '2026-05-06T10:00:00Z' });
    expect(q.next_slot_at).toBe('2026-05-06T10:00:00Z');
  });
});
