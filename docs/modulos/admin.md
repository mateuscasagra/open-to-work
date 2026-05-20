# Módulo Admin

**Propósito:** painel administrativo com métricas globais do produto. Login compartilhado com a aplicação principal — o acesso é controlado pela coluna `users.is_admin` (boolean), promovido manualmente via banco.

## Endpoints / Comandos

| Método | Rota | Handler | Auth |
|---|---|---|---|
| `GET` | `/api/admin/metrics` | `Api\Admin\AdminMetricsController` (invokable) | `auth:sanctum` + `admin` |
| `GET` | `/api/admin/error-logs?limit=&level=` | `Api\Admin\AdminErrorLogsController` | `auth:sanctum` + `admin` |
| `GET` | `/api/admin/subscriptions?status=all\|active\|canceled&page=N` | `Api\Admin\AdminSubscriptionsController` — lista paginada | `auth:sanctum` + `admin` |

## Backend

**Controller:** `app/Http/Controllers/Api/Admin/AdminMetricsController.php`, `AdminErrorLogsController.php`, `AdminSubscriptionsController.php`.

**Domínio:** `app/Domain/Admin/`
- **Queries:**
  - `GetAdminMetrics` — orquestra
  - `GetUserLocationDistribution` — agregado geográfico
  - `GetSubscriptionStats` — KPIs de assinaturas (active/canceled/cancellation_rate/mrr_cents/total_revenue_cents)
  - `ListSubscriptions` — paginação + filtro `active|canceled|all`
- **DTO:** `AdminMetricsData` (campos `byLocation` + novo `subscriptions`)

**Middleware:** `app/Http/Middleware/EnsureAdmin.php` (alias `admin`)
- Lança `AccessDeniedHttpException` (403) se o usuário autenticado não for admin
- Lança 401 (Sanctum padrão) se não autenticado

**Model:** `app/Models/User.php`
- Coluna `is_admin` (boolean, default `false`) adicionada via migration
- Cast: `is_admin => 'boolean'`
- Helper: `User::isAdmin(): bool`
- **Não está em `$hidden`** → flag é exposta em `/api/me`

## Métricas devolvidas

```json
{
  "totals": {
    "users": 42,
    "applications": 318,
    "resumes": 87,
    "active_users": 9
  },
  "top_applicants": [
    { "user_id": 12, "name": "Maria", "email": "maria@…", "applications_count": 28 },
    { "user_id": 7, "name": "João", "email": "joao@…", "applications_count": 24 }
  ],
  "by_location": {
    "countries": [
      { "country_code": "BR", "count": 35 },
      { "country_code": "US", "count": 5 }
    ],
    "states": [
      { "country_code": "BR", "state_code": "SP", "state_name": "São Paulo", "count": 18 }
    ],
    "cities": [
      { "country_code": "BR", "city": "São Paulo", "count": 10 }
    ],
    "without_location": 2
  },
  "generated_at": "2026-04-24T15:00:00+00:00"
}
```

### Definições

| Métrica | Definição |
|---|---|
| `totals.users` | `COUNT(*)` em `users` (inclui o admin que está consultando) |
| `totals.applications` | `COUNT(*)` em `applications` |
| `totals.resumes` | `COUNT(*)` em `resumes` |
| `totals.active_users` | usuários distintos com **≥ 3 candidaturas nos últimos 7 dias** (janela calculada via `applied_at >= now() - 7 days`) |
| `top_applicants` | top 5 usuários com mais candidaturas (todos os tempos), ordenado desc por contagem, desempate por `users.id` |
| `by_location.countries` | TODOS os países com ao menos 1 perfil cadastrado, ordenados por `count` desc |
| `by_location.states` | top 10 estados/províncias agrupados por `(country_code, state_code, state_name)` |
| `by_location.cities` | top 15 cidades agrupadas por `(country_code, city)` |
| `by_location.without_location` | `users LEFT JOIN profiles WHERE profiles.country_code IS NULL` — cobre user sem profile e profile sem país |

### Constantes em `GetAdminMetrics`

```php
public const ACTIVE_USER_MIN_APPLICATIONS = 3;
public const ACTIVE_WINDOW_DAYS = 7;
public const TOP_APPLICANTS_LIMIT = 5;
```

Constantes em `Domain/Admin/Queries/GetUserLocationDistribution`:

```php
public const TOP_STATES_LIMIT = 10;
public const TOP_CITIES_LIMIT = 15;
```

## Promovendo um admin

A coluna é controlada pelo banco — não há endpoint para promover ou rebaixar:

```sql
UPDATE users SET is_admin = true WHERE email = 'voce@email.com';
```

Ou via tinker:

```bash
docker exec -it otw-backend php artisan tinker
>>> User::where('email', 'voce@email.com')->update(['is_admin' => true]);
```

## Frontend

**Rota:** `/app/admin` (vue-router) com guard `meta.adminOnly`
- O guard global em `router/index.ts` redireciona para `dashboard` se o usuário autenticado não for admin

**Módulo:** `frontend/src/modules/admin/`
- **View:** `views/AdminView.vue`
- **Composables:**
  - `useAdminMetrics.ts` (TanStack Query, key `['admin', 'metrics']`)
  - `useAdminErrorLogs.ts` (logs de prod)
  - `useAdminSubscriptions.ts` — paginação + filtro reativo (`status`, `page`); key `['admin', 'subscriptions', status, page]`; `keepPreviousData` pra evitar blink na troca de filtro/página

**Schemas:** `frontend/src/shared/api/schemas.ts`
- `UserSchema.is_admin: boolean` (default `false`)
- `AdminMetricsSchema` — inclui bloco `by_location` + novo `subscriptions: { active, canceled, cancellation_rate, mrr_cents, total_revenue_cents }`
- `AdminSubscriptionRowSchema` + `AdminSubscriptionsPageSchema`

**Componentes da AdminView:**
1. KPIs gerais (4 cards: users, applications, resumes, active_users)
2. **KPIs de assinatura** (4 cards novos): Assinaturas ativas, Canceladas, Taxa de cancelamento, Faturamento total (com MRR no hint)
3. **Tabela de assinaturas** com filtro de 3 botões (Todas/Ativas/Canceladas) + paginação. Colunas: usuário (name+email), status badge, fim do período, último pagamento, cancelada em
4. Top applicants (ranking com avatar + barra de progresso)
5. **Distribuição geográfica** — 3 colunas (`countries`, `states`, `cities`) com emoji de bandeira. Helper `countryFlag(code)` no `AdminView.vue`.
6. Production errors (live, polling em `useAdminErrorLogs`)

**Navegação:** o item "Admin" só aparece em `AppLayout.vue` quando `auth.user?.is_admin === true`.

## Estatísticas de assinatura — como são calculadas

**`GetSubscriptionStats::execute()`** usa o critério `asaas_subscription_id IS NOT NULL` (engajou com pagamento em algum momento) — **não** `plan='pro'`. Isso inclui quem cancelou antes do primeiro pagamento confirmar (caso comum: gerou PIX mas não pagou → cancelou → ainda assim conta como "canceled" no admin).

- **`active`**: `asaas_subscription_id IS NOT NULL AND status='active'`
- **`canceled`**: `asaas_subscription_id IS NOT NULL AND status IN ('canceled', 'past_due')`
- **`cancellation_rate`**: `canceled / (active + canceled) × 100`, 2 casas decimais
- **`mrr_cents`**: usa critério mais estrito `plan='pro' AND status='active'` × preço atual do Pro (lido de `Plan::priceCentsBySlug`). Só conta quem **realmente está pagando**, não quem só engajou.
- **`total_revenue_cents`**: SUM `(payload->'payment'->>'value')::numeric` em `webhook_logs` WHERE `event_type IN ('PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED')` AND `processed_at IS NOT NULL`. Valor BRL decimal × 100.

**Quando uma sub é "esquecida" da contagem:** depois do `subscriptions:downgrade-expired` rodar e zerar `asaas_subscription_id`, a sub sai dos KPIs e da listagem. Histórico de churn de longo prazo precisaria de tabela `subscription_events` (não previsto pra v1).

## Testes

Arquivo: `tests/Feature/Admin/AdminMetricsTest.php` (Pest) + `AdminSubscriptionsTest.php`

Cobertura:
- 401 sem autenticação
- 403 para usuário comum
- estrutura JSON completa (inclui `by_location` + `subscriptions`)
- contagem de usuários, candidaturas, currículos
- janela de "última semana" inclusiva (≤ 7 dias) e exclusiva (> 7 dias)
- ranking ordenado desc + limite de 5
- ranking exclui usuários sem candidaturas
- agregação por país/estado/cidade
- `without_location` conta users sem profile OU com profile.country_code null
- limites de top estados (10) e cidades (15)
- `/api/me` expõe `is_admin` (true/false)
- KPIs de subs com cenários mistos (active confirmado vs sem pgto, canceled, past_due)
- Listagem com filtros all/active/canceled + paginação
- Total revenue calculado a partir de payloads em webhook_logs

```bash
docker exec otw-backend php artisan test --filter=AdminMetrics
docker exec otw-backend php artisan test --filter=AdminSubscriptions
```

## Decisões de design

- **Sem login separado**: a tela compartilha o mesmo fluxo de autenticação. Acesso via flag de banco mantém a superfície de auth pequena e evita necessidade de gerenciar credenciais admin separadas.
- **Ranking sem janela temporal**: top candidatos é "all-time" para identificar power users do produto, independentemente de atividade recente. A métrica `active_users` cobre o eixo temporal.
- **Métricas em tempo real**: igual ao `MetricsController` do dashboard pessoal — sem rollup diário, agrega na hora a partir das tabelas `users`, `applications`, `resumes`, `profiles`. Para volumes muito altos, mover para um job de rollup é o próximo passo.
- **DTO simples**: agregação rasa, não há sub-recursos paginados — single-shot endpoint.
- **Agregação geográfica usa indexes em `profiles`**: `country_code`, `(country_code, state_code)`, `(country_code, city)` — definidos na migration `2026_04_27_120000`. Sem isso o GROUP BY ficaria caro à medida que a base cresce.
- **`by_location.countries` não tem limite**: o produto tem ~25 países suportados; um single-pass devolvendo todos é mais útil que paginar.
