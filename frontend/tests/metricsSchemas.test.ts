import { describe, it, expect } from 'vitest';
import { MetricsSummarySchema } from '@/shared/api/schemas';

const base = {
  kpis: {
    total_applications: 5,
    total_responses: 1,
    total_interviews: 0,
    total_offers: 0,
    total_rejections: 1,
    response_rate: 0.2,
    interview_rate: 0,
    offer_rate: 0,
  },
  channels: [],
  funnel: [],
  heatmap: [],
  avgDaysBetweenStages: null,
  insights: [],
  rangeFrom: '2026-01-01',
  rangeTo: '2026-04-18',
  monthly: {
    year: 2026,
    month: 4,
    days_in_month: 30,
    days: [],
    total_applications: 0,
    total_responses: 0,
    response_rate: 0,
  },
};

describe('MetricsSummarySchema', () => {
  it('parses a minimal summary', () => {
    expect(() => MetricsSummarySchema.parse(base)).not.toThrow();
  });

  it('rejects invalid weekday', () => {
    expect(() =>
      MetricsSummarySchema.parse({
        ...base,
        heatmap: [{ weekday: 9, hour: 1, count: 1 }],
      }),
    ).toThrow();
  });

  it('rejects unknown insight severity', () => {
    expect(() =>
      MetricsSummarySchema.parse({
        ...base,
        insights: [{ key: 'x', severity: 'danger', message: 'no' }],
      }),
    ).toThrow();
  });

  it('allows null avgDaysBetweenStages', () => {
    expect(() =>
      MetricsSummarySchema.parse({ ...base, avgDaysBetweenStages: null }),
    ).not.toThrow();
  });
});
