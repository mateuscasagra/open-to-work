# Módulo Metrics

**Propósito:** dashboard com KPIs, funil, canais, heatmap e insights — **KPIs calculados em tempo real** a partir de `applications` e `application_events`. Rollup diário (`metrics_daily`) é mantido para histórico.

## Endpoints / Comandos

| Método | Rota / Comando | Handler |
|---|---|---|
| `GET` | `/api/metrics?from=&to=` | `MetricsController` (invokable) |
| CLI | `php artisan metrics:rollup-daily [--date=YYYY-MM-DD] [--from=... --to=...]` | `RollupDailyMetricsCommand` |

Scheduler: **03:00 UTC** diário.

## Backend

**Controller:** `app/Http/Controllers/Api/MetricsController.php` (invokable)

**Domínio:** `app/Domain/Metrics/`
- **Action:** `RollupDailyMetrics`
- **Queries:** `GetUserMetrics`, `GenerateInsights`
- **DTO:** `MetricsSummaryData`

**Command:** `app/Console/Commands/RollupDailyMetricsCommand.php`

**Model:** `app/Models/MetricsDaily.php` — **sem cast de `date`** (estabilidade em SQLite nos testes)

### Rollup diário

`RollupDailyMetrics`:
- Agrega por `(user_id, date)` em `metrics_daily`:
  - `applications_count` (do dia, via `applications.applied_at`)
  - `responses_count`, `interviews_count`, `offers_count`, `rejections_count` (via `application_events.occurred_at` filtrando por tipo de transição)
  - `channels` (breakdown por `applications.source` em JSON)
- **Idempotente** via `updateOrCreate(['user_id', 'date'], $values)` — pode rodar múltiplas vezes no mesmo dia sem duplicar
- **Backfill:** `--from=YYYY-MM-DD --to=YYYY-MM-DD` itera dia a dia
- **Dia específico:** `--date=YYYY-MM-DD`
- **Default (sem flags):** processa **ontem**

### Query do dashboard

`GetUserMetrics` consulta `applications` + `application_events` **em tempo real** (sem depender de `metrics_daily`), devolve:
- **KPIs:** total/responses/interviews/offers/rejections + taxas (real-time via query direto nas tabelas fonte)
- **Channels:** por `source` com `applications`, `responses`, `response_rate`
- **Funnel:** `reached_count` por `ApplicationStatus` (inclui aplicações que **passaram** pela etapa, não só as que estão lá agora)
- **Heatmap:** matriz `weekday × hour` baseada em `applied_at`
- **avgDaysBetweenStages:** tempo médio do `applied_at` até o **1º** `status_changed` (em dias)
- Range default: 90 dias atrás → hoje

### Insights heurísticos

`GenerateInsights` produz mensagens com `severity ∈ {info, warning, success}`:
- **warning** se `response_rate < 10%` com `≥5` aplicações
- **success** se `response_rate ≥ 30%` com `≥10` aplicações
- **warning** apontando a maior queda no funil
- **info** com canal mais efetivo (`min 3` aplicações)
- **info** "sem dados" quando vazio

## Frontend

**Arquivos:** `frontend/src/modules/metrics/`

- **`useMetrics`** (`composables/useMetrics.ts`):
  - `load(params?)` → `GET /api/metrics?from=&to=`
  - Parse `MetricsSummarySchema`
  - State: `data, loading, error`
- **`DashboardView`** (`views/`):
  - 4 KPI cards (total apps, response_rate, interviews, offers)
  - **Insights** coloridos por `severity`
  - **Funnel** com barras (largura proporcional ao maior estágio) — sempre visível; mostra empty state com CTA quando não há candidaturas
  - **Channels table** (source, applications, responses, response_rate %) — sempre visível; mostra empty state com CTA quando não há candidaturas
  - **Heatmap 7×24** (weekday × hour) pintado por intensidade indigo
  - Loading skeleton + error message

## Efeitos colaterais

- Escritas: `metrics_daily` (via `updateOrCreate`)
- Cache Redis (chave por `user_id` + range) — TTL configurável

## Testes

- `backend/tests/Feature/Metrics/` — `ModelTest`, `RollupDailyTest` (4 casos), `GetUserMetricsTest` (6 casos)
- `frontend/tests/metricsSchemas.test.ts`
- `frontend/tests/useMetrics.test.ts`

## Pontos de atenção

- **Dashboard vazio?** Verifique nesta ordem:
  1. Há `applications` com `applied_at` no range pedido?
  2. Range `from`/`to` está correto? Default é 90 dias.
  3. Funil e canais mostram empty state informativo quando não há candidaturas — isso é comportamento esperado.
- **Rollup é idempotente, mas dependente de `application_events` corretos.** Se um event for inserido com `event_type` errado, a contagem (responses/interviews/offers/rejections) sai errada. Confirme tipos via state machine antes de inserir eventos manualmente.
- **`MetricsDaily` sem cast de `date`** — comparar como string `Y-m-d`. Se adicionar `'date' => 'date'` no `casts()`, alguns testes em SQLite quebram (driver retorna formato diferente).
- **Funil "passou pela etapa":** olha `application_events` de `status_changed`. Aplicação que pulou direto de `applied → rejected` **não** aparece em `screening`/`assessment`. Comportamento correto, mas pode confundir.
- **Heatmap por hora:** baseia-se em `applied_at` no timezone do servidor. Em prod (`UTC`), candidatura criada às 23h BRT aparece como 02h do dia seguinte. Se virar UX problem, fazer conversão para `user.locale` no client.
- **Insights são heurísticas, não estatísticas.** Thresholds (`<10%`, `≥30%`, etc.) estão hardcoded em `GenerateInsights` — se quiser tunar por user, mover para config.
- **Backfill em massa:** `--from=2026-01-01 --to=2026-04-19` itera dia a dia em loop. Para >365 dias, considerar batch ou queue.
- **Cache de `/api/metrics`:** se mudar a forma do JSON retornado, **invalidar o cache** (`php artisan cache:clear`) ou subir a versão da chave.
