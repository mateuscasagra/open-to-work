# Módulos do Sistema — Índice

Cada módulo tem seu próprio arquivo em `docs/modulos/`. Quando for caçar um bug, leia **apenas** o arquivo do módulo afetado (e, se necessário, `cross-cutting.md` ou `shared-frontend.md`).

## Por módulo

| Módulo | Arquivo | Cobre |
|---|---|---|
| **Auth** | [`modulos/auth.md`](./modulos/auth.md) | Login/Register/Logout, OAuth (Google/LinkedIn/GitHub), Sanctum SPA, sessão |
| **Profile** | [`modulos/profile.md`](./modulos/profile.md) | Perfil do candidato, skills, autocomplete |
| **Jobs** | [`modulos/jobs.md`](./modulos/jobs.md) | Agregação (drivers + pipeline), matching, busca, feed |
| **Applications** | [`modulos/applications.md`](./modulos/applications.md) | Candidaturas, Kanban, status state machine, anexos, follow-up |
| **Resumes** | [`modulos/resumes.md`](./modulos/resumes.md) | Builder, export PDF cliente, upload PDF privado, vinculação |
| **Metrics** | [`modulos/metrics.md`](./modulos/metrics.md) | Rollup diário, dashboard, KPIs, funil, heatmap, insights |
| **Admin** | [`modulos/admin.md`](./modulos/admin.md) | Painel admin, métricas globais, flag `is_admin`, ranking de usuários |
| **Account (LGPD)** | [`modulos/account.md`](./modulos/account.md) | Export de dados, delete de conta |
| **Location** | [`modulos/location.md`](./modulos/location.md) | Lookup CEP/ZIP (ViaCEP, zippopotam), lista de países suportados |
| **Suggestions** | [`modulos/suggestions.md`](./modulos/suggestions.md) | Sugestões da comunidade, votação up/down, quota semanal (5/7d), ranking top-3 com coroa/troféu |
| **Subscription** | [`modulos/subscription.md`](./modulos/subscription.md) | Plano Pro via Asaas PIX, quota free 15 candidaturas/mês, webhook + downgrade automático, tabela `plans` configurável |
| **Support** | [`modulos/support.md`](./modulos/support.md) | Formulário público "Preciso de ajuda" no rodapé da landing, envia e-mail pra SUPPORT_EMAIL |
| **Cross-cutting** | [`modulos/cross-cutting.md`](./modulos/cross-cutting.md) | Rate limiters, Sentry, scheduler, CSRF, middleware |
| **Frontend compartilhado** | [`modulos/shared-frontend.md`](./modulos/shared-frontend.md) | Axios client, Zod schemas, layout, router, i18n, landing |

## Roteamento por sintoma

Use isto pra decidir qual arquivo abrir antes de mergulhar no código.

| Sintoma | Abra primeiro |
|---|---|
| `401` em rota `/api/*` autenticada | `auth.md` + `cross-cutting.md` (CSRF/Sanctum) |
| `419` (Page Expired / token mismatch) | `cross-cutting.md` (CSRF) → `shared-frontend.md` (client.ts) |
| `429` Too Many Requests | `cross-cutting.md` (RateLimiters) |
| Rota nova retorna 404 mesmo com `route:list` listando ela | `cross-cutting.md` (FrankenPHP opcache) — `docker compose restart backend` |
| Login/register retorna sucesso mas front mostra erro | `auth.md` (parse Zod, refresh do User) |
| Login retorna `403` com `email_unverified` / usuário travado em `/verify-email` | `auth.md` (fluxo Verify Email — code expirou ou attempts ≥5? checar `email_verification_*` no DB) |
| E-mail de confirmação não chega na caixa | `auth.md` (Pontos de atenção — Resend domínio verificado, MailHog em dev em http://localhost:8025) |
| OAuth callback não autentica | `auth.md` (Socialite stateless, redirect URL) |
| Link de "Esqueci minha senha" não funciona / e-mail não chega | `auth.md` (Forgot/Reset Password) — checar `APP_FRONTEND_URL` no .env (URL do link), TTL `config('auth.passwords.users.expire')` (60min default), MailHog em http://localhost:8025 em dev |
| Erro de coluna inexistente no SQL | módulo da rota + verificar migrations |
| Vagas não aparecem / drivers quebrados | `jobs.md` (driver contract + pipeline) |
| Tela de vagas dá erro no front mas API retorna 200 | `jobs.md` — `language` no DB precisa ser `pt_BR\|en\|es` (enum canônico); `LocaleEnum` Zod do front rejeita `'pt'` cru |
| Matching retorna lista vazia | `jobs.md` (`ListMatchingJobs`) + `profile.md` (skills do user) |
| Kanban não move card / 422 ao mudar status | `applications.md` (state machine) |
| Anexos não fazem upload | `applications.md` (MediaLibrary + S3) + `cross-cutting.md` (`throttle:uploads`) |
| Candidatura arquivada continua aparecendo no Kanban "Ativas" | `applications.md` — verificar `archived_at` no DB; query default usa `whereNull('archived_at')`. Cache stale: invalidar `['applications']` (prefix match cobre `active`/`archived`) |
| Follow-up não é enviado | `applications.md` (`SendApplicationFollowUpsCommand`) + `cross-cutting.md` (scheduler) |
| Export PDF do currículo trava | `resumes.md` (jsPDF + html2canvas, multi-página) |
| Download de PDF retorna 403/expirado | `resumes.md` (`temporaryUrl` TTL 5min) |
| Edição de seção do currículo não persiste (só título salva) | `resumes.md` — vue-query 5 retorna data readonly; seeding precisa deep clone (`map(s => ({ ...s, content: { ...s.content } }))`), senão `setContent` falha silencioso |
| POST de currículo retorna 201 mas front mostra "Falha ao salvar" | `resumes.md` — `CreateResume` precisa setar `file_path: null`/`metadata: null` no `create()`, senão Zod do front faz throw em `ResumeSchema.parse` |
| Dashboard sem dados | `metrics.md` (rollup diário rodou? período correto?) |
| Heatmap/funil errado | `metrics.md` (`GetUserMetrics`) |
| Heatmap mostra hora errada (offset de timezone) | `metrics.md` — front envia `?tz=` via `Intl.DateTimeFormat().resolvedOptions().timeZone`; conferir se composable `useMetrics` está enviando |
| Candidatura arquivada aparece no dashboard | `metrics.md` — `GetUserMetrics` filtra `whereNull('archived_at')`. Se rollup antigo, reprocessar com `metrics:rollup-daily --date=...` |
| CEP/ZIP não preenche estado/cidade no Profile | `location.md` (ViaCEP/zippopotam, cache, regex) |
| Lista de países não aparece no select | `location.md` (`/api/location/countries` + `useSupportedCountries`) |
| Distribuição geográfica zerada no Admin | `admin.md` (`by_location`) — usuários precisam ter `country_code` preenchido em `profiles` |
| Export LGPD vazio | `account.md` (`ExportUserData`) |
| Delete de conta deixa lixo no S3 | `account.md` (`DeleteAccount` cascade) |
| Painel admin retorna 403 mesmo logado | `admin.md` (flag `users.is_admin` no banco) |
| Tela de admin não aparece no menu | `admin.md` (frontend lê `auth.user.is_admin` do `/api/me`) |
| Rota nova retorna 404 | `routes/api.php` + `shared-frontend.md` (router guard) |
| Tradução faltando | `shared-frontend.md` (i18n) + `lang/{pt_BR,en,es}/` no backend |
| Usuário preso em `/profile` mesmo com localização salva | `profile.md` (Gating de localização) — `ProfileView.onSubmit` chamou `refreshLocationStatus()`? Conferir Pinia state `auth.locationComplete` |
| Abas do menu com cadeado / não consigo navegar | `profile.md` — esperado para usuário novo sem `country_code/state_name/city` preenchidos. Preencher e salvar destrava |
| Mensagem de validação do backend vindo em inglês mesmo com navegador em pt/es | `auth.md` (i18n das mensagens de validação) — checar se `lang/{pt_BR,es}/validation.php` existe e se `SetLocale` está no pipeline |
| 429 ao criar sugestão / "Você atingiu o limite de 5 sugestões por semana" | `suggestions.md` (quota rolling 7d em `CreateSuggestion`) — checar `next_slot_at` no payload da resposta |
| Não consigo votar na minha própria sugestão (botões disabled / 403) | `suggestions.md` — comportamento esperado (`CannotVoteOwnSuggestionException`); front desabilita botões via `auth.user.id === s.user.id` |
| Coroa/troféu não aparece para o top-3 de sugestões | `suggestions.md` — `rank` é calculado **globalmente** no controller via query separada `topIds()`; verificar se `score` do registro está na top-3 da tabela inteira |
| Voto não persiste após reload | `suggestions.md` — verificar `my_vote` no payload do `index` (subquery `addSelect`) e schema Zod aceitando 1/-1/null; pode ser cookie de sessão expirado |
| Score da sugestão não bate com upvotes_count - downvotes_count | `suggestions.md` (Pontos de atenção — usar `COUNT(*)` + `update()` em `CastVote`, NUNCA `loadCount` que não marca dirty) |
| **402 Payment Required** ao criar candidatura ("Limite mensal de 15...") | `subscription.md` (Quota enforcement) — usuário free passou de 15 no mês. Body tem `kind: 'quota_exceeded'` + `used/limit/reset_at`. Pro = `limit: null` |
| Usuário pagou PIX mas continua como Free | `subscription.md` (Webhook flow) — checar `webhook_logs` (event_id, processed_at, error). Asaas tem retry 13x/24h; idempotência protege. Se webhook NUNCA chegou: tunnel/ngrok caiu? Header `asaas-access-token` bate com env var? |
| Pro deveria ter virado Free após `current_period_end` mas continua Pro | `subscription.md` — scheduler `subscriptions:downgrade-expired` rodou? Conferir `php artisan schedule:list`. Fallback manual: `php artisan subscriptions:downgrade-expired` |
| `/api/me` retornando shape antigo (sem `subscription`) | `auth.md` + `subscription.md` — backend mudou pra envelope `{user, subscription}`. Frontend parseia via `parseEnvelope()`. Se quebrou: subscription da migration backfilled? `SELECT count(*) FROM subscriptions` vs `count(*) FROM users` |
| Webhook Asaas retornando 401 | `subscription.md` — header `asaas-access-token` precisa bater **exatamente** com `config('services.asaas.webhook_token')`. `hash_equals` é timing-safe |
| Modal de PIX abre mas fica em "Aguardando" pra sempre | `subscription.md` — `PixCheckoutModal` faz polling a cada 5s via `auth.fetchMe()`. Se backend não reativa: webhook não chegou. Se trava em loading: `pix_qr_code_base64` veio vazio do Asaas |
| `AsaasClientException` / 502 ao assinar | `subscription.md` — Asaas indisponível, timeout (15s) ou retornou payload inesperado. Conferir `Http::fake` em testes pra cenário; em prod, checar Sentry |
| Asaas retorna "CPF/CNPJ do cliente é obrigatório" | `subscription.md` (CPF obrigatório) — frontend abre `CpfPromptModal` antes do checkout; backend `StoreSubscriptionRequest::normalizedCpf()` valida e normaliza |
| Preço do Pro mostrado errado no frontend | `subscription.md` (Tabela `plans`) — frontend lê do `auth.subscription.pro_price_cents`; valor vem de `Plan::priceCentsBySlug('pro')`. Trocar valor com `UPDATE plans SET price_cents=...` + cache (60s TTL) ou `cache:forget plans.pro.price_cents` |
| `__PHP_Incomplete_Class` em `Plan::*` | `subscription.md` — esse foi o motivo de cachear **só o int** em vez do model. Se voltar a aparecer, conferir cache driver + `php artisan cache:clear` |
| Formulário "Preciso de ajuda" não envia e-mail | `support.md` — checar `SUPPORT_EMAIL` env, conferir log em `support.mail_failed`, MailHog em dev (`http://localhost:8025`), Resend domain verificado em prod |
| Admin "Assinaturas ativas" = 0 mas tem subs no DB | `admin.md` — critério é `asaas_subscription_id IS NOT NULL` (não `plan='pro'`). Se a sub não tem Asaas ID, não entra na contagem. Confirmar com `SELECT plan, status, asaas_subscription_id FROM subscriptions` |

## Convenção dos arquivos

Cada `modulos/*.md` segue o mesmo template:

1. **Propósito** — o que o módulo resolve em uma frase
2. **Endpoints / comandos** — tabela de rotas e comandos artisan
3. **Backend** — controllers, actions/queries, models, validações, fluxo
4. **Frontend** — views, composables, stores, fluxo
5. **Efeitos colaterais** — DB, eventos, queue, S3, e-mails
6. **Testes** — onde estão os testes de regressão
7. **Pontos de atenção** — bugs típicos e o que verificar primeiro

## Documentos relacionados

- [`ARQUITETURA.md`](../ARQUITETURA.md) — visão de alto nível, decisões arquiteturais
- [`adr/`](./adr/) — ADRs de decisões não óbvias
