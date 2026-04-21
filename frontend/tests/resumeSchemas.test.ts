import { describe, it, expect } from 'vitest';
import {
  ResumeSectionTypeEnum,
  ResumeSectionSchema,
  ResumeSchema,
  ResumesPageSchema,
} from '@/shared/api/schemas';

describe('ResumeSectionTypeEnum', () => {
  it('accepts all 6 section types', () => {
    ['summary', 'experience', 'education', 'skill', 'language', 'project'].forEach((t) =>
      expect(() => ResumeSectionTypeEnum.parse(t)).not.toThrow()
    );
  });

  it('rejects unknown section type', () => {
    expect(() => ResumeSectionTypeEnum.parse('hobby')).toThrow();
  });
});

describe('ResumeSectionSchema', () => {
  it('parses a valid section', () => {
    const parsed = ResumeSectionSchema.parse({
      id: 1,
      type: 'experience',
      order: 0,
      content: { company: 'Acme', role: 'Dev' },
    });
    expect(parsed.content.company).toBe('Acme');
  });

  it('rejects negative order', () => {
    expect(() =>
      ResumeSectionSchema.parse({ type: 'summary', order: -1, content: {} })
    ).toThrow();
  });
});

describe('ResumeSchema', () => {
  it('defaults sections to empty array', () => {
    const parsed = ResumeSchema.parse({
      id: 1,
      user_id: 2,
      title: 'Backend',
      language: 'pt_BR',
      is_pdf_upload: false,
      file_path: null,
      metadata: null,
    });
    expect(parsed.sections).toEqual([]);
  });

  it('parses a complete resume', () => {
    const parsed = ResumeSchema.parse({
      id: 1,
      user_id: 2,
      title: 'Fullstack',
      language: 'en',
      is_pdf_upload: false,
      file_path: null,
      metadata: {},
      sections: [
        { id: 10, type: 'summary', order: 0, content: { text: 'Hi' } },
      ],
      sections_count: 1,
      updated_at: '2026-04-18T12:00:00Z',
    });
    expect(parsed.sections).toHaveLength(1);
    expect(parsed.sections_count).toBe(1);
  });
});

describe('ResumesPageSchema', () => {
  it('parses a paginated response', () => {
    const parsed = ResumesPageSchema.parse({
      data: [],
      current_page: 1,
      last_page: 1,
      total: 0,
    });
    expect(parsed.data).toEqual([]);
    expect(parsed.total).toBe(0);
  });
});
