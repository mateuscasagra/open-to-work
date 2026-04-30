# Cross-cutting

**Propósito:** preocupações que atravessam todos os módulos — rate limiting, CSRF/Sanctum, middleware, scheduler, observabilidade.

## Rate Limiters

Definidos em `app/Providers/AppServiceProvider.php::configureRateLimiters()`. Aplicados via `->middleware('throttle:<nome>')` em `routes/api.php`.

| Nome | Limites | Onde usado |
|---|---|---|
| `auth` | 10/min por IP **+** 5/min por e-mail | `/auth/register`, `/auth/login`, `/auth/{provider}/redirect`, `/auth/{provider}/callback` |
| `uploads` | 20/min por user, 5/min por IP (anonymous) | `POST /resumes/pdf`, `POST /applications/{id}/attachments` |
| `account-sensitive` | 5/min por user, 2/min por IP | `GET /account/export`, `DELETE /account` |
| `search` | 120/min por user, 30/min por IP | `GET /skills` |
| `location-lookup` | 30/min por user, 10/min por IP | `POST /location/lookup` (ViaCEP/zippopotam) |

Resposta de violação: `429 Too Many Requests` com header `Retry-After`.

## CSRF + Sanctum SPA

**Backend:**
- `bootstrap/app.php` — `withMiddleware` chama `statefulApi()` (atalho do Sanctum) e prepende `EnsureFrontendRequestsAreStateful`.
- `config/sanctum.php` — `stateful` lê `SANCTUM_STATEFUL_DOMAINS` (ex.: `localhost:5173,opentowork.app`).
- Cookie de sessão `HttpOnly`, `SameSite=Lax`, `Secure` em prod.

**Frontend** (`frontend/src/shared/api/client.ts`):
- Axios `withCredentials: true`, `withXSRFToken: true`.
- `ensureCsrf()` faz `GET /sanctum/csrf-cookie` na **primeira mutação**, cacheado em memória.
- Request interceptor garante CSRF antes de `POST|PUT|PATCH|DELETE`.

### Diagnóstico CSRF

| Sintoma | Causa provável |
|---|---|
| `419 Page Expired` em primeira mutação | Cookie XSRF não foi setado — checar CORS, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` |
| `419` após inatividade | Sessão expirou — recarregar página força novo CSRF |
| `401` em rota autenticada após login OK | `SANCTUM_STATEFUL_DOMAINS` não inclui o domínio atual |
| Login retorna `200` mas `/api/me` volta como guest | Cookies não estão sendo persistidos — checar `withCredentials` e `SESSION_SAME_SITE` |

## Middleware

**Stack global API** (`bootstrap/app.php`):
- `statefulApi()` (Sanctum prepended)
- `SetLocale` apêndice em todas rotas API

### `SetLocale` (`app/Http/Middleware/SetLocale.php`)

Resolve o locale ativo na ordem:
1. `$user->locale` (se autenticado)
2. Header `Accept-Language` (prefix match: `pt-*` → `pt_BR`, `es-*` → `es`, default `en`)
3. Fallback `pt_BR`

Chama `App::setLocale($locale)` antes do controller. Afeta `__()`, validações, notificações.

## Scheduler

`routes/console.php`:

| Comando | Frequência | O quê |
|---|---|---|
| `jobs:aggregate` | a cada 6h (`AGGREGATOR_SCHEDULE_CRON`) | Roda todos os drivers de agregação |
| `jobs:deactivate-expired` | sáb/dom | Marca vagas expiradas como `active=false` |
| `metrics:rollup-daily` | 03:00 UTC diário | Materializa `metrics_daily` do dia anterior |
| `applications:send-followups` | 09:00 UTC diário | Manda lembrete de follow-up após 7 dias |

**Verificar em produção:** `php artisan schedule:list`. **Rodar local:** `php artisan schedule:work` (loop) ou `schedule:run` (uma vez).

## Sentry (observabilidade)

**Config:** `config/sentry.php`
- `dsn`: `SENTRY_LARAVEL_DSN` (sem DSN → no-op)
- `traces_sample_rate`: 10% prod, 100% staging (`SENTRY_TRACES_SAMPLE_RATE`)
- `profiles_sample_rate`: 0
- `environment`: `SENTRY_ENVIRONMENT` (default `APP_ENV`)
- **Breadcrumbs ligados:** logs, queue_info, command_info, http_client_requests
- **Breadcrumbs desligados:** sql, cache, views (verbosos)
- **Ignora 4xx esperadas:** `ValidationException`, `AuthenticationException`, `AuthorizationException`, `NotFoundHttpException`, `ThrottleRequestsException`

**Bootstrap:** `bootstrap/app.php#withExceptions` chama `\Sentry\Laravel\Integration::handles($exceptions)`.

## Container bindings

**Drivers de agregação** (`AppServiceProvider::register`):
- Lê `config('aggregator.drivers')` (mapa `key => Driver::class`)
- Filtra por `config('aggregator.enabled_sources')`
- Marca cada driver com `tag('job.drivers')`
- `AggregateJobsCommand` recebe via `iterable<JobSourceDriver>` injetado por `tagged('job.drivers')`

**Adicionar novo driver:**
1. Criar classe que implementa `JobSourceDriver`
2. Registrar em `config/aggregator.php#drivers`
3. Listar em `AGGREGATOR_SOURCES` (env)
4. Reiniciar workers / `config:clear`

**Clientes de location** (`AppServiceProvider::register`):
- `ViaCepClient` e `ZippopotamClient` ganham tag `'location.clients'`
- `LookupPostalCode` é construído com `iterable<PostalCodeLookupClient>` (`tagged('location.clients')`) + `cache.store`
- Veja [`location.md`](./location.md) para adicionar novo cliente

## Configs sensíveis a cache

Em prod, `config:cache` cacheia tudo de `config/`. Mudanças em env afetando configs cacheadas exigem `config:clear` + restart. Configs comuns nesse caso:
- `aggregator.php` (drivers, fontes ativas, cron)
- `sanctum.php` (`SANCTUM_STATEFUL_DOMAINS`)
- `sentry.php` (DSN, sample rates)
- `services.php` (chaves OAuth)

## Pontos de atenção

- **Throttle por user** depende de `auth:sanctum` rodar **antes** do throttle middleware. A ordem em `routes/api.php` importa.
- **`SetLocale` roda em todas rotas API** — overhead mínimo, mas se for adicionar lógica pesada (ex.: query no DB), considerar cache.
- **Sentry sem DSN é no-op,** mas `\Sentry\Laravel\Integration::handles()` ainda é chamado. Não causa overhead perceptível.
- **`config/sanctum.php` `stateful` aceita lista separada por vírgula.** Em prod, listar **todos** os domínios do front (apex, www, staging) — esquecer um quebra a auth nele.
- **Scheduler precisa de cron rodando no host** (`* * * * * cd /var/www && php artisan schedule:run`). Em Docker, isso vai num container dedicado ou via Supervisor. Confira `infra/docker-compose.prod.yml`.
- **Rate limiters não cobrem rotas web** (só API). Se adicionar rota `web.php`, configurar throttle separadamente.
- **`auth` limiter** considera IP **+** e-mail — em ambiente atrás de proxy (Cloudflare), confirme que `TrustProxies` está pegando o IP real, senão todo mundo bate o limite global do proxy.
- **FrankenPHP cacheia bytecode (opcache).** Se você adicionar uma rota nova em `routes/api.php` e `php artisan route:list` mostrar a rota, mas requests HTTP retornarem `404`, é opcache servindo o `api.php` antigo. **Solução:** `docker compose restart backend`. Em prod o deploy reinicia o container, então só pega quem desenvolve com hot-edit dos arquivos PHP.
