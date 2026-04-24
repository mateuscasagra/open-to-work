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
| **Cross-cutting** | [`modulos/cross-cutting.md`](./modulos/cross-cutting.md) | Rate limiters, Sentry, scheduler, CSRF, middleware |
| **Frontend compartilhado** | [`modulos/shared-frontend.md`](./modulos/shared-frontend.md) | Axios client, Zod schemas, layout, router, i18n, landing |

## Roteamento por sintoma

Use isto pra decidir qual arquivo abrir antes de mergulhar no código.

| Sintoma | Abra primeiro |
|---|---|
| E-mail de candidatura não enviado | `applications.md` (SendApplicationEmail) + `profile.md` (email_apply settings) + `jobs.md` (contact_email) |
| `401` em rota `/api/*` autenticada | `auth.md` + `cross-cutting.md` (CSRF/Sanctum) |
| `419` (Page Expired / token mismatch) | `cross-cutting.md` (CSRF) → `shared-frontend.md` (client.ts) |
| `429` Too Many Requests | `cross-cutting.md` (RateLimiters) |
| Login/register retorna sucesso mas front mostra erro | `auth.md` (parse Zod, refresh do User) |
| OAuth callback não autentica | `auth.md` (Socialite stateless, redirect URL) |
| Erro de coluna inexistente no SQL | módulo da rota + verificar migrations |
| Vagas não aparecem / drivers quebrados | `jobs.md` (driver contract + pipeline) |
| Matching retorna lista vazia | `jobs.md` (`ListMatchingJobs`) + `profile.md` (skills do user) |
| Kanban não move card / 422 ao mudar status | `applications.md` (state machine) |
| Anexos não fazem upload | `applications.md` (MediaLibrary + S3) + `cross-cutting.md` (`throttle:uploads`) |
| Follow-up não é enviado | `applications.md` (`SendApplicationFollowUpsCommand`) + `cross-cutting.md` (scheduler) |
| Export PDF do currículo trava | `resumes.md` (jsPDF + html2canvas, multi-página) |
| Download de PDF retorna 403/expirado | `resumes.md` (`temporaryUrl` TTL 5min) |
| Dashboard sem dados | `metrics.md` (rollup diário rodou? período correto?) |
| Heatmap/funil errado | `metrics.md` (`GetUserMetrics`) |
| Export LGPD vazio | `account.md` (`ExportUserData`) |
| Delete de conta deixa lixo no S3 | `account.md` (`DeleteAccount` cascade) |
| Painel admin retorna 403 mesmo logado | `admin.md` (flag `users.is_admin` no banco) |
| Tela de admin não aparece no menu | `admin.md` (frontend lê `auth.user.is_admin` do `/api/me`) |
| Rota nova retorna 404 | `routes/api.php` + `shared-frontend.md` (router guard) |
| Tradução faltando | `shared-frontend.md` (i18n) + `lang/{pt_BR,en,es}/` no backend |

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
- [`PROGRESSO.md`](../PROGRESSO.md) — status das sprints, o que está feito
- [`adr/`](./adr/) — ADRs de decisões não óbvias
