import { describe, it, expect } from 'vitest';
import {
  ApplicationStatusSchema,
  UserSchema,
  JobSchema,
  ApplicationSchema,
} from '@/shared/api/schemas';

describe('ApplicationStatusSchema', () => {
  it('accepts all valid statuses', () => {
    const valid = [
      'applied',
      'screening',
      'assessment',
      'interview_hr',
      'interview_tech',
      'offer',
      'accepted',
      'rejected',
      'withdrawn',
    ];
    valid.forEach((s) => expect(() => ApplicationStatusSchema.parse(s)).not.toThrow());
  });

  it('rejects unknown status', () => {
    expect(() => ApplicationStatusSchema.parse('unicorn')).toThrow();
  });
});

describe('UserSchema', () => {
  it('parses a valid user', () => {
    const user = UserSchema.parse({
      id: 1,
      name: 'Diego',
      email: 'd@e.com',
      locale: 'pt_BR',
    });
    expect(user.name).toBe('Diego');
  });

  it('rejects invalid email', () => {
    expect(() =>
      UserSchema.parse({ id: 1, name: 'X', email: 'nope', locale: null }),
    ).toThrow();
  });

  it('allows null locale', () => {
    expect(() =>
      UserSchema.parse({ id: 1, name: 'X', email: 'a@b.com', locale: null }),
    ).not.toThrow();
  });
});

describe('JobSchema', () => {
  it('parses a job with company', () => {
    const job = JobSchema.parse({
      id: 1,
      title: 'Dev',
      location: 'Remote',
      modality: 'remote',
      seniority: 'senior',
      stack: ['php'],
      salary_min: 1000,
      salary_max: 2000,
      salary_currency: 'BRL',
      posted_at: '2026-04-17',
      company: { id: 1, name: 'Acme', logo_url: null },
    });
    expect(job.stack).toEqual(['php']);
  });

  it('allows null company', () => {
    expect(() =>
      JobSchema.parse({
        id: 1,
        title: 'Dev',
        location: null,
        modality: null,
        seniority: null,
        stack: [],
        salary_min: null,
        salary_max: null,
        salary_currency: null,
        posted_at: null,
        company: null,
      }),
    ).not.toThrow();
  });

  it('parses description_html when provided', () => {
    const job = JobSchema.parse({
      id: 1,
      title: 'Dev',
      description_html: '<p>Hello <strong>world</strong></p>',
      location: null,
      modality: null,
      seniority: null,
      stack: [],
      salary_min: null,
      salary_max: null,
      salary_currency: null,
      posted_at: null,
      company: null,
    });
    expect(job.description_html).toBe('<p>Hello <strong>world</strong></p>');
  });

  it('defaults description_html to null when omitted', () => {
    const job = JobSchema.parse({
      id: 1,
      title: 'Dev',
      location: null,
      modality: null,
      seniority: null,
      stack: [],
      salary_min: null,
      salary_max: null,
      salary_currency: null,
      posted_at: null,
      company: null,
    });
    expect(job.description_html).toBeNull();
  });
});

describe('ApplicationSchema', () => {
  it('parses minimal application', () => {
    const app = ApplicationSchema.parse({
      id: 1,
      status: 'applied',
      applied_at: '2026-04-17T10:00:00Z',
      notes: null,
      expected_salary: null,
      source: null,
    });
    expect(app.status).toBe('applied');
  });
});
