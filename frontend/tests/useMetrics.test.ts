import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useMetrics } from '@/modules/metrics/composables/useMetrics';
import { api } from '@/shared/api/client';

vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn() },
}));

const payload = {
  kpis: {
    total_applications: 10,
    total_responses: 3,
    total_interviews: 1,
    total_offers: 0,
    total_rejections: 2,
    response_rate: 0.3,
    interview_rate: 0.1,
    offer_rate: 0,
  },
  channels: [
    { source: 'linkedin', applications: 6, responses: 2, response_rate: 0.333 },
  ],
  funnel: [
    { status: 'applied', label: 'Aplicada', reached: 10 },
    { status: 'screening', label: 'Triagem', reached: 3 },
  ],
  heatmap: [{ weekday: 1, hour: 9, count: 4 }],
  avgDaysBetweenStages: 3.5,
  insights: [
    { key: 'healthy_response_rate', severity: 'success', message: 'ok' },
  ],
  rangeFrom: '2026-01-18',
  rangeTo: '2026-04-18',
};

describe('useMetrics', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('parses metrics response through Zod schema', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: payload });

    const metrics = useMetrics();
    await metrics.load();

    expect(api.get).toHaveBeenCalledWith('/api/metrics', { params: {} });
    expect(metrics.data.value?.kpis.total_applications).toBe(10);
    expect(metrics.data.value?.channels[0].source).toBe('linkedin');
    expect(metrics.data.value?.insights[0].severity).toBe('success');
    expect(metrics.loading.value).toBe(false);
    expect(metrics.error.value).toBeNull();
  });

  it('forwards from/to params', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: payload });

    const metrics = useMetrics();
    await metrics.load({ from: '2026-03-01', to: '2026-04-01' });

    expect(api.get).toHaveBeenCalledWith('/api/metrics', {
      params: { from: '2026-03-01', to: '2026-04-01' },
    });
  });

  it('exposes error when the API rejects', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('boom'));

    const metrics = useMetrics();
    await expect(metrics.load()).rejects.toThrow('boom');
    expect(metrics.error.value).toBe('boom');
    expect(metrics.loading.value).toBe(false);
  });
});
