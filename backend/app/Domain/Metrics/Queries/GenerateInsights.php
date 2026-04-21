<?php

declare(strict_types=1);

namespace App\Domain\Metrics\Queries;

/**
 * Regras simples para transformar KPIs + funnel + channels em dicas acionáveis.
 *
 * As regras são heurísticas (não um modelo); cada insight traz uma `severity`
 * (`info`, `warning`, `success`) para o frontend destacar visualmente.
 */
final class GenerateInsights
{
    private const LOW_RESPONSE_THRESHOLD = 0.10;
    private const MIN_VOLUME_FOR_CHANNEL_INSIGHT = 3;

    /**
     * @param  array{total_applications: int, total_responses: int, total_interviews: int, total_offers: int, total_rejections: int, response_rate: float, interview_rate: float, offer_rate: float}  $kpis
     * @param  list<array{source: string, applications: int, responses: int, response_rate: float}>  $channels
     * @param  list<array{status: string, label: string, reached: int}>  $funnel
     * @return list<array{key: string, severity: string, message: string}>
     */
    public function execute(array $kpis, array $channels, array $funnel): array
    {
        $insights = [];

        if ($kpis['total_applications'] === 0) {
            $insights[] = [
                'key' => 'no_data',
                'severity' => 'info',
                'message' => 'Comece a registrar candidaturas para ver suas métricas aqui.',
            ];

            return $insights;
        }

        if ($kpis['total_applications'] >= 5 && $kpis['response_rate'] < self::LOW_RESPONSE_THRESHOLD) {
            $rate = (int) round($kpis['response_rate'] * 100);
            $insights[] = [
                'key' => 'low_response_rate',
                'severity' => 'warning',
                'message' => "Sua taxa de resposta está em {$rate}%. Revise o resumo do seu currículo e as palavras-chave para a stack que você busca.",
            ];
        }

        if ($kpis['total_applications'] >= 10 && $kpis['response_rate'] >= 0.30) {
            $rate = (int) round($kpis['response_rate'] * 100);
            $insights[] = [
                'key' => 'healthy_response_rate',
                'severity' => 'success',
                'message' => "Excelente taxa de resposta ({$rate}%). Continue aplicando no mesmo volume.",
            ];
        }

        $biggestDrop = $this->biggestFunnelDrop($funnel);
        if ($biggestDrop !== null) {
            $insights[] = [
                'key' => 'biggest_funnel_drop',
                'severity' => 'warning',
                'message' => "Você perde mais candidaturas entre {$biggestDrop['from']} e {$biggestDrop['to']} (queda de {$biggestDrop['loss']}). Foque nessa etapa.",
            ];
        }

        $bestChannel = $this->bestChannel($channels);
        if ($bestChannel !== null) {
            $rate = (int) round($bestChannel['response_rate'] * 100);
            $insights[] = [
                'key' => 'best_channel',
                'severity' => 'success',
                'message' => "{$bestChannel['source']} é seu canal mais efetivo ({$rate}% de resposta). Priorize vagas por lá.",
            ];
        }

        return $insights;
    }

    /**
     * @param  list<array{status: string, label: string, reached: int}>  $funnel
     * @return array{from: string, to: string, loss: int}|null
     */
    private function biggestFunnelDrop(array $funnel): ?array
    {
        if (count($funnel) < 2) {
            return null;
        }

        $worst = null;
        for ($i = 1; $i < count($funnel); $i++) {
            $loss = $funnel[$i - 1]['reached'] - $funnel[$i]['reached'];
            if ($funnel[$i - 1]['reached'] === 0) {
                continue;
            }
            if ($worst === null || $loss > $worst['loss']) {
                $worst = [
                    'from' => $funnel[$i - 1]['label'],
                    'to' => $funnel[$i]['label'],
                    'loss' => $loss,
                ];
            }
        }

        if ($worst === null || $worst['loss'] <= 0) {
            return null;
        }

        return $worst;
    }

    /**
     * @param  list<array{source: string, applications: int, responses: int, response_rate: float}>  $channels
     * @return array{source: string, response_rate: float}|null
     */
    private function bestChannel(array $channels): ?array
    {
        $eligible = array_values(array_filter(
            $channels,
            static fn (array $c): bool => $c['applications'] >= self::MIN_VOLUME_FOR_CHANNEL_INSIGHT && $c['response_rate'] > 0.0,
        ));

        if ($eligible === []) {
            return null;
        }

        usort($eligible, static fn (array $a, array $b): int => $b['response_rate'] <=> $a['response_rate']);

        return [
            'source' => $eligible[0]['source'],
            'response_rate' => $eligible[0]['response_rate'],
        ];
    }
}
