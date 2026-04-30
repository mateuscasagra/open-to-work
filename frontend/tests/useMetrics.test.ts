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

    const [, opts] = vi.mocked(api.get).mock.calls[0];
    expect(opts?.params).toMatchObject({ tz: expect.any(String) });
    expect(metrics.data.value?.kpis.total_applications).toBe(10);
    expect(metrics.data.value?.channels[0].source).toBe('linkedin');
    expect(metrics.data.value?.insights[0].severity).toBe('success');
    expect(metrics.loading.value).toBe(false);
    expect(metrics.error.value).toBeNull();
  });

  it('forwards from/to params alongside tz', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: payload });

    const metrics = useMetrics();
    await metrics.load({ from: '2026-03-01', to: '2026-04-01' });

    const [, opts] = vi.mocked(api.get).mock.calls[0];
    expect(opts?.params).toMatchObject({
      from: '2026-03-01',
      to: '2026-04-01',
      tz: expect.any(String),
    });
  });

  it('sends browser timezone via Intl.DateTimeFormat', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: payload });
    const spy = vi.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockReturnValue({
      timeZone: 'America/Sao_Paulo',
    } as Intl.ResolvedDateTimeFormatOptions);

    await useMetrics().load();

    const [, opts] = vi.mocked(api.get).mock.calls[0];
    expect(opts?.params).toMatchObject({ tz: 'America/Sao_Paulo' });
    spy.mockRestore();
  });

  it('exposes error when the API rejects', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('boom'));

    const metrics = useMetrics();
    await expect(metrics.load()).rejects.toThrow('boom');
    expect(metrics.error.value).toBe('boom');
    expect(metrics.loading.value).toBe(false);
  });
});
