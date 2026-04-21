# Progresso — Open to Work

**Atualizado:** 2026-04-18

---

## Onde estamos

| Etapa | Status |
|---|---|
| 1. Arquitetura & decisões | ✅ Concluída |
| 2. Design patterns | ✅ Concluída |
| 3. Estrutura do repositório | ✅ Concluída |
| 4. Testes dos módulos (TDD base) | ✅ Concluída |
| 5. **Desenvolvimento das funcionalidades** | 🟡 **Sprint 1 ✅ · Sprint 2 ✅ · Sprint 3 ✅ · Sprint 4 ✅ · Sprint 5 ✅ · Sprint 6 🟡 (código pronto, infra pendente)** |
| 6. Deploy em staging | ⏳ Pendente (provisionar Hetzner) |
| 7. Lançamento MVP | ⏳ Pendente |

### Sprint 6 — Polimento pré-launch (🟡 código pronto; infra externa pendente)

| Item | Status |
|---|---|
| Rate limiting nas rotas sensíveis | ✅ RateLimiters nomeados (`auth`, `uploads`, `account-sensitive`, `search`) no `AppServiceProvider@boot`; throttle em `/auth/*`, `POST /resumes/pdf`, `POST /applications/{id}/attachments`, `/skills`, `/account/*` |
| LGPD: export + delete de conta | ✅ `app/Domain/Account/Actions/` (`ExportUserData`, `DeleteAccount`); `AccountController` com `GET /api/account/export` (JSON download) e `DELETE /api/account`; logout antes de `$user->delete()` (evita `cycleRememberToken` reinserir o usuário); remove blobs S3 + MediaLibrary; frontend `AccountView` com confirmação "digite EXCLUIR" + composable `useAccount` |
| Integração Sentry | ✅ `config/sentry.php` publicado (ignora 4xx esperadas), `bootstrap/app.php` registra `Sentry\Laravel\Integration::handles($exceptions)`; `.env.example` com `SENTRY_LARAVEL_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_TRACES_SAMPLE_RATE` |
| Script de restore | ✅ `infra/scripts/restore.sh` (download R2 → gpg decrypt → drop/create DB → psql restore + sanity check); complementa o `backup.sh` existente |
| Smoke tests end-to-end | ✅ `tests/Feature/SmokeTest.php` (health + fluxo register → me → apply → status → metrics → export); `RateLimitingTest` valida throttle em auth/register |
| Uptime Kuma rodando | ⏳ Depende de provisionamento do VPS |
| Backup automatizado testado (restore mensal) | ⏳ Depende de staging real |
| Provisionamento do VPS Hetzner | ⏳ Pendente |
| Primeiro deploy de staging | ⏳ Pendente |

**Critério de pronto (código):** rate limits aplicados, endpoints LGPD funcionando, Sentry configurado, restore.sh escrito, smoke tests cobrindo o fluxo crítico — **suíte verde (156 Pest + 52 Vitest + vue-tsc clean)**.

### Sprint 5 — Métricas (✅ concluída)

| Item | Status |
|---|---|
| Rollup diário `metrics:rollup-daily` | ✅ `RollupDailyMetrics` action + `RollupDailyMetricsCommand` (`--date`, `--from/--to` backfill); agrega applications/events do dia em `metrics_daily` (apps, respostas positivas, entrevistas, ofertas, rejeições, canais); idempotente via `updateOrCreate` |
| Endpoint `/api/metrics` com agregados | ✅ `GetUserMetrics` query (KPIs de metrics_daily; canais com taxa de resposta; funil incluindo aplicações já passadas por etapa; heatmap dia-da-semana × hora; tempo médio aplicação → 1ª resposta); `MetricsController` invokable |
| Insights | ✅ `GenerateInsights` com regras heurísticas (taxa baixa/saudável, maior queda no funil, canal mais efetivo, sem dados); severity `info`/`warning`/`success` |
| Dashboard frontend | ✅ `DashboardView` refeito: 4 KPIs, insights coloridos, funil com barras, tabela de canais, heatmap 7×24 pintado por intensidade indigo; composable `useMetrics` com parse Zod |
| Testes | ✅ 10 Pest novos (4 Rollup + 6 GetUserMetrics) + 7 Vitest novos (4 schemas + 3 composable) |

**Critério de pronto:** dashboard consome `/api/metrics` com KPIs, canais, funil, heatmap, avgDaysBetweenStages e insights — **suíte verde (146 Pest + 50 Vitest + vue-tsc clean)**.

### Sprint 4 — Currículos (✅ concluída)

| Item | Status |
|---|---|
| Builder estruturado (experiências, formação, skills, idiomas, projetos, resumo) | ✅ `app/Domain/Resume/` (Actions `CreateResume`/`UpdateResume`, DTOs, query `ListUserResumes`), `ResumeController` CRUD, `ResumePolicy`, enum `ResumeSectionType`; `ResumeBuilderView` com editor dinâmico (add/remove/↑↓, campos por tipo) + `ResumesListView` com cards (9 Pest + 3 Vitest) |
| Export PDF no cliente (jsPDF + html2canvas) com 2 templates | ✅ `ClassicTemplate.vue` (serif) e `ModernTemplate.vue` (sidebar indigo); `useResumePdfExport` com imports dinâmicos + multi-página automática; `ResumeExportView` com seletor de template |
| Upload de PDF externo com URL assinada | ✅ `UploadResumePdf` action grava em disk `s3` (MinIO/R2) com UUID + visibility private; `POST /api/resumes/pdf` (multipart, mimes:pdf, max 5MB); `GET /api/resumes/{id}/download` retorna `temporaryUrl` TTL 5min com fallback (7 Pest + 2 Vitest) |
| Vinculação currículo ↔ candidatura | ✅ `resumeId` validado com ownership no Store + Update (`Rule::exists where user_id`); seletor de currículo no `JobsListView`; seção "Currículo vinculado" na `ApplicationDetailView`; `useApplicationDetail.updateNotes` aceita `resumeId` (5 Pest + 3 Vitest) |

**Critério de pronto:** usuário cria currículo no builder, baixa PDF em 2 layouts, faz upload de PDF externo (URL assinada) e vincula à candidatura — **suíte verde (136 Pest + 43 Vitest + vue-tsc clean + PHPStan nível 8)**.

### Sprint 3 — Candidaturas (✅ concluída)

| Item | Status |
|---|---|
| Aplicar direto do feed (botão + abre link externo + 409 em duplicata) | ✅ `POST /api/applications` com `DuplicateApplicationException`; `JobsListView` com botão "Aplicar" e `useApplyToJob` (3 Vitest) |
| Kanban drag-and-drop com optimistic update | ✅ HTML5 drag nativo no `ApplicationsKanbanView`; `useChangeApplicationStatus` com rollback em 422 (2 Vitest) |
| Detalhe da candidatura com timeline + notas | ✅ Rota `/applications/:id`, `ApplicationDetailView` com status controls + timeline ordenada + `PUT` de notes (3 Pest) |
| Anexos (spatie/medialibrary) | ✅ `HasMedia` no `Application`, `ApplicationAttachmentController` (upload/list/delete), event `attachment_added` (5 Pest) |
| Follow-up por e-mail após 7 dias sem atividade | ✅ `ApplicationFollowUpNotification` + command `applications:send-followups` agendado 09:00 UTC; ignora terminais e renotificações recentes (4 Pest) |

**Critério de pronto:** usuário aplica no feed, arrasta cards no Kanban, edita notas, anexa documentos e recebe follow-up automático — **suíte verde (108 Pest + 28 Vitest + vue-tsc clean)**.

### Sprint 2 — Agregação de vagas (✅ concluída)

| Item | Status |
|---|---|
| ArbeitnowDriver + testes | ✅ 3/3 passando |
| RemotiveDriver + testes | ✅ 2/2 passando |
| WeWorkRemotelyDriver (RSS) + testes | ✅ 4/4 passando |
| GupyDriver (scraping leve) + testes | ✅ 3/3 passando |
| Registro no `AppServiceProvider` + `config/aggregator.php` | ✅ Drivers resolvidos via `config/aggregator.php#drivers`, tagged no container |
| Matching com perfil (interseção de stacks + bônus modality/seniority) | ✅ `ListMatchingJobs` query + endpoint `/api/jobs/matching` (4/4 testes) |
| Feed de vagas no frontend com filtros | ✅ `JobsListView` + `useJobFilters` composable + 4 testes Vitest |
| Busca full-text via Scout/Meilisearch | ✅ `Job` Searchable, `GET /api/jobs?q=...` (2/2 testes) |

**Critério de pronto:** drivers plugáveis, matching por perfil, busca full-text e feed filtrável — **suíte verde (95 Pest + 23 Vitest + vue-tsc clean)**.

### Sprint 1 — Auth e perfil (✅ concluída)

| Item | Status |
|---|---|
| Endpoints Auth (Register/Login/Logout/Me) | ✅ Implementados |
| Fluxo OAuth (Google/LinkedIn/GitHub) | ✅ Implementado — bugs de config corrigidos |
| RegisterView + OAuth callback no frontend | ✅ Implementado |
| Formulário de perfil (cargo, seniority, modalidade, stack) | ✅ Implementado |
| Middleware Accept-Language + traduções PT/EN/ES | ✅ Implementado |
| Pest + Vitest + type-check | ✅ 77/77 + 19/19 + vue-tsc clean |

---

## O que foi entregue até aqui

### Documentação
- [`ARQUITETURA.md`](./ARQUITETURA.md) — arquitetura completa, stack, segurança, escala, patterns, roadmap
- [`docs/adr/`](./docs/adr/) — 5 ADRs (stack, hospedagem, agregação, PDF, patterns)
- [`README.md`](./README.md), [`infra/README.md`](./infra/README.md)

### Backend (Laravel 12, PHP 8.3+)
**Estrutura Domain-Driven:**
- `app/Domain/Job/Aggregator/` — Contract (driver), DTO, drivers (RemoteOk, Arbeitnow, Remotive, WeWorkRemotely, Gupy), pipeline (Normalize/Deduplicate/Persist), Action
- `app/Domain/Job/Queries/` — `ListMatchingJobs` (score = interseção de stacks + bônus modality/seniority)
- `app/Domain/Application/` — Actions (Create, ChangeStatus), DTO, Events, **Exceptions (`DuplicateApplicationException`)**
- `app/Domain/Resume/` — Actions (`CreateResume`, `UpdateResume`, `UploadResumePdf`), DTOs (`ResumeData`, `ResumeSectionData`), Queries (`ListUserResumes`)
- **`app/Domain/Metrics/`** — Actions (`RollupDailyMetrics` c/ idempotência via `updateOrCreate` + backfill), Queries (`GetUserMetrics`, `GenerateInsights`), DTO (`MetricsSummaryData`)
- **`app/Domain/Account/`** — Actions `ExportUserData` (JSON bundle LGPD) e `DeleteAccount` (remove blobs S3 + MediaLibrary + usuário; logout antes de delete)
- `app/Enums/` — ApplicationStatus (com state machine), Modality, Seniority, SupportedLocale, **ResumeSectionType**
- `app/Policies/ApplicationPolicy.php`, **`ResumePolicy.php`**
- `app/Http/Controllers/Api/` — Auth, OAuth, Jobs, Applications, ApplicationAttachment, Profile, Health, Resume, ResumePdf, Metrics, **Account**
- **RateLimiters** (`AppServiceProvider@boot`): `auth` (10/min IP + 5/min e-mail), `uploads` (20/min user), `account-sensitive` (5/min user), `search` (120/min user)
- **Sentry:** `config/sentry.php` + `bootstrap/app.php#withExceptions` com `Integration::handles()`; no-op sem DSN
- `app/Http/Requests/` — Form Requests tipadas (Store/Update Application+Resume, UploadResumePdf — ownership via `Rule::exists where user_id`)
- `app/Notifications/` — `ApplicationFollowUpNotification` (canal mail)
- `app/Console/Commands/` — `AggregateJobsCommand`, **`SendApplicationFollowUpsCommand`**
- 12 Models com relações e casts (Application usa `HasMedia` + `InteractsWithMedia` para anexos; **`MetricsDaily`** sem cast de `date` para estabilidade em SQLite)
- 10 migrations (users, oauth_accounts, profiles, skills, companies, jobs, resumes, applications, metrics_daily, media)
- Scheduler: `jobs:aggregate` (6h), **`metrics:rollup-daily` (03:00 UTC — agora com comando real)**, `jobs:deactivate-expired` (sem/dom), `applications:send-followups` (09:00)
- Pacotes instalados: **Sanctum, Socialite, Horizon, Scout, Meilisearch, spatie/data, spatie/medialibrary, spatie/permission, google2fa, sentry**

### Frontend (Vue 3 + TS + Vite)
- Módulos: `auth/`, `jobs/`, `applications/`, `resumes/`, `profile/`, **`metrics/` (dashboard refeito: KPIs, insights, funil, canais, heatmap)**
- `applications/`: `ApplicationsKanbanView` (drag-drop nativo), `ApplicationDetailView` (timeline, notes, anexos, **currículo vinculado**), composables `useApplyToJob`, `useChangeApplicationStatus` (optimistic + rollback), `useApplicationDetail` (aceita `resumeId`), `useAttachments`
- `jobs/`: `JobsListView` com botão "Aplicar" → abre link externo do `JobSource`; **seletor "Usar currículo" passa `resumeId`**
- `resumes/`: `ResumesListView` (cards + upload PDF + exportar/baixar), `ResumeBuilderView` (editor dinâmico de seções), `ResumeExportView` (preview + seletor Clássico/Moderno); templates `ClassicTemplate.vue` e `ModernTemplate.vue`; composables `useResumes`, `useResumeDetail`, `useSaveResume`, `useDeleteResume`, `useUploadResumePdf`, `useResumePdfExport` (jsPDF + html2canvas com imports dinâmicos e multi-página)
- **`metrics/`: `DashboardView` completo (KPIs, insights, funil, canais, heatmap pintado por intensidade indigo) + composable `useMetrics` com parse Zod**
- Shared: `api/client.ts` (com CSRF Sanctum), `schemas.ts` (Zod — inclui `JobSource`, `ApplicationEvent`, `Attachment`, `Resume`, `ResumeSection`, `ResumesPage`; `Application` com `resume_id`/`resume`; **`MetricsSummary` com KPIs, canais, funil, heatmap, insights**)
- Pinia store de auth, layout app, Tailwind, i18n (pt-BR/en/es)
- State machine XState para fluxo de candidatura
- Packages instalados (411 — adicionado `html2canvas`)

### Testes

**Backend — 156 testes Pest verdes (502 asserções):**
```
tests/Feature/Auth/          (Register, Login, Logout, Me, OAuth)
tests/Feature/Profile/       (Show, Update)
tests/Feature/I18n/          (SetLocale)
tests/Feature/Jobs/          (List c/ sources, Filters, Show, Matching, Search — Scout)
tests/Feature/Aggregator/    (RemoteOk, Arbeitnow, Remotive, WeWorkRemotely, Gupy,
                              Deduplicate, Persist, SyncJobs, Command)
tests/Feature/Applications/  (List, Create c/ 409 duplicata, Show, Update, ChangeStatus,
                              Delete, Policy, Attachments, SendFollowUps, ResumeLinking)
tests/Feature/Resumes/       (Model, List, Show, Create, Update, Delete, Policy,
                              UploadResumePdf, DownloadResumePdf)
tests/Feature/Metrics/       (Model, RollupDaily, GetUserMetrics endpoint)
tests/Feature/Account/       (ExportUserData, DeleteAccount)
tests/Feature/RateLimitingTest.php  (throttle auth/register)
tests/Feature/SmokeTest.php  (health + critical path end-to-end)
tests/Unit/Domain/           (JobDTO hash, ApplicationStatus transitions, Actions)
```

**Frontend — 14 arquivos, 52 testes verdes:**
```
tests/schemas.test.ts                         ✅ 8 passing
tests/resumeSchemas.test.ts                   ✅ 7 passing
tests/metricsSchemas.test.ts                  ✅ 4 passing
tests/authStore.test.ts                       ✅ 5 passing
tests/applicationStatusMachine.test.ts        ✅ 5 passing
tests/apiClient.test.ts                       ✅ 1 passing
tests/useJobFilters.test.ts                   ✅ 4 passing
tests/useApplyToJob.test.ts                   ✅ 3 passing
tests/useChangeApplicationStatus.test.ts      ✅ 2 passing
tests/useSaveResume.test.ts                   ✅ 3 passing
tests/useUploadResumePdf.test.ts              ✅ 2 passing
tests/useApplicationDetailResumeLink.test.ts  ✅ 3 passing
tests/useMetrics.test.ts                      ✅ 3 passing
tests/useAccount.test.ts                      ✅ 2 passing
```

### CI/CD (GitHub Actions)
- [`ci.yml`](./.github/workflows/ci.yml) — Pint + PHPStan + Pest (parallel) + ESLint + type-check + Vitest + build
- [`deploy-staging.yml`](./.github/workflows/deploy-staging.yml) — **Gate: `needs: ci`** (testes precisam passar)
- [`deploy-prod.yml`](./.github/workflows/deploy-prod.yml) — **Gate: `needs: ci`** + aprovação manual via GitHub Environments

### Infra
- [`infra/docker-compose.yml`](./infra/docker-compose.yml) — Postgres, Redis, Meilisearch, MinIO, MailHog
- [`infra/docker-compose.prod.yml`](./infra/docker-compose.prod.yml) — stack de produção no Hetzner
- [`infra/nginx/`](./infra/nginx/) — reverse proxy + TLS
- [`infra/scripts/`](./infra/scripts/) — backup + deploy + **restore**

---

## Ambiente de desenvolvimento

**Tudo roda em Docker.** Nenhuma dependência precisa estar instalada na máquina do dev além de Docker + git.

```bash
# Sobe toda a stack de dev (Postgres, Redis, Meilisearch, MinIO, MailHog, backend Laravel)
docker compose -f infra/docker-compose.yml up -d

# Rodar testes backend
docker compose -f infra/docker-compose.yml run --rm backend vendor/bin/pest

# Rodar artisan
docker compose -f infra/docker-compose.yml run --rm backend php artisan migrate

# Frontend — Node local ou num container Node
cd frontend && npm install && npm run dev
```

O `Dockerfile` do backend já inclui todas as extensões necessárias (`pdo_sqlite`, `pdo_pgsql`, `redis`, `pcntl`, `exif`, `bcmath`, etc). Se precisar de mais alguma, adiciona no `Dockerfile` — nunca na máquina local.

### Avisos menores
- `vue-i18n@10` deprecou; migrar para v11 quando houver tempo
- `npm audit fix` resolve a maioria das vulnerabilidades transitivas

---

## Próxima etapa — Desenvolvimento das funcionalidades

A fundação (arquitetura, estrutura, testes) está pronta. Agora desenvolvemos as features em **TDD**: cada feature começa com o teste falhando e termina com todos os testes verdes + CI passando.

### Ordem sugerida (foco em "valor primeiro")

#### Sprint 1 — Auth e perfil (fundação do usuário) — 🟡 implementada, aguardando build
1. [x] Endpoints Auth (Register/Login/Logout/Me) — controllers + Form Requests prontos
2. [x] Fluxo OAuth (Google/LinkedIn/GitHub) — config `services.linkedin` corrigido, `app.frontend_url` adicionado
3. [x] Frontend: RegisterView, OAuthCallbackView, rotas `/register` e `/auth/callback`, link entre Login/Register
4. [x] Perfil: `ProfileController` com `Rule::enum`, rota singleton `/api/profile`, `SkillController` de autocomplete, ProfileView completo com tags de skills
5. [x] i18n: middleware `SetLocale` (user.locale → Accept-Language → fallback por prefixo), `lang/{en,pt_BR,es}/auth.php`, traduções frontend expandidas
6. [ ] **Rodar `pest` + `vitest` + `type-check` — ver bloco no topo deste arquivo**

**Critério de pronto:** usuário cria conta, vincula OAuth, define perfil com stack, faz logout/login — **validado via suíte de testes verde**.

#### Sprint 2 — Agregação de vagas (core do produto) — ✅ concluída
1. [x] Drivers: `ArbeitnowDriver`, `RemotiveDriver`, `WeWorkRemotelyDriver`, `GupyDriver`
2. [x] Registro centralizado via `config/aggregator.php` → tagged bindings no container
3. [ ] Rodar `php artisan jobs:aggregate` contra APIs reais (validar volume/dedup end-to-end) — **smoke test em staging**
4. [x] `Job` Searchable + `GET /api/jobs?q=...` integrado ao Scout (collection em testes, Meilisearch em staging/prod)
5. [x] `JobsListView` + `useJobFilters` com filtros de busca/stack/seniority/modalidade + toggle "só matches"
6. [x] `ListMatchingJobs` query + `GET /api/jobs/matching` (score = |skills ∩ stack| + bônus modality/seniority)

**Critério de pronto:** drivers plugáveis, matching por perfil e feed filtrável — **suíte verde (95 Pest + 23 Vitest + vue-tsc clean)**. Smoke test com APIs reais fica como primeira tarefa do deploy em staging.

#### Sprint 3 — Candidaturas (ciclo completo) — ✅ concluída
1. [x] Endpoint: criar candidatura direto do feed (clicou "aplicar" → registra + abre link externo)
2. [x] Kanban frontend: drag-and-drop entre colunas disparando `ChangeApplicationStatus`
3. [x] Tela de detalhe: timeline de eventos, notas, anexos
4. [x] Notificação por e-mail de follow-up (7 dias sem resposta → lembrete)

**Critério de pronto:** usuário registra aplicação, move entre status, recebe lembrete.

#### Sprint 4 — Currículos — ✅ concluída
1. [x] Builder estruturado (summary, experience, education, skill, language, project) — editor dinâmico com add/remove/reorder
2. [x] Export PDF no cliente (`jsPDF` + `html2canvas`) com 2 templates (Clássico serif + Moderno sidebar indigo); multi-página automática
3. [x] Upload de PDF externo em disk `s3` (MinIO/R2), visibility private, UUID; download via `temporaryUrl` TTL 5min
4. [x] Vinculação currículo ↔ candidatura com ownership (`Rule::exists where user_id`); seletor no feed e no detalhe da candidatura

**Critério de pronto:** usuário cria currículo no builder, baixa PDF, usa em candidatura — **suíte verde (136 Pest + 43 Vitest + vue-tsc clean + PHPStan nível 8)**.

#### Sprint 5 — Métricas — ✅ concluída
1. [x] Job noturno de rollup: `metrics:rollup-daily` (default ontem, `--date=YYYY-MM-DD`, backfill `--from=...--to=...`)
2. [x] Endpoint `/api/metrics` com KPIs, canais, funil, heatmap, tempo médio entre etapas
3. [x] Dashboard com: taxa de resposta, tempo médio entre etapas, canais mais efetivos, heatmap dia-da-semana × hora
4. [x] Insights (taxa baixa, maior queda no funil, melhor canal)

**Critério de pronto:** dashboard consome `/api/metrics` com insights reais — **suíte verde (146 Pest + 50 Vitest + vue-tsc clean)**.

#### Sprint 6 — Polimento pré-launch — 🟡 código pronto, infra pendente
1. [x] Rate limiting nas rotas sensíveis (auth, uploads, account-sensitive, search)
2. [x] LGPD: export/delete de dados do usuário (`GET /api/account/export`, `DELETE /api/account`)
3. [x] Monitoring: Sentry integrado (config + `Integration::handles`). Uptime Kuma depende de VPS.
4. [x] Script de restore (`infra/scripts/restore.sh`). Teste mensal real depende de staging.
5. [ ] Provisionamento do VPS Hetzner — depende de infra externa
6. [ ] Primeiro deploy de staging — depende de VPS
7. [x] Smoke tests end-to-end (`SmokeTest`: health + critical path)

**Critério de pronto:** staging rodando há 1 semana sem intervenção manual; métricas de SLO coletadas.

---

## Métricas de progresso

| Métrica | Atual | Meta MVP |
|---|---|---|
| Cobertura de testes (backend) | 156 casos Pest verdes (502 asserções) | >80% das linhas de `Domain/` |
| Cobertura de testes (frontend) | 52 casos Vitest verdes | suíte por módulo |
| Endpoints API implementados | 27 (incl. `/account/export`, `DELETE /account`) | ~27 |
| Drivers de agregação | 5 (RemoteOk, Arbeitnow, Remotive, WeWorkRemotely, Gupy) | 5 (MVP) |
| Fontes de vagas ativas | 0 (ingestão real no deploy) | 5 |
| Telas do frontend | Jobs c/ filtros+apply+seletor de currículo · Kanban drag-drop · Detalhe c/ timeline/notes/anexos/currículo · Resumes list+builder+export · Perfil · Auth/Register/OAuth · Dashboard c/ KPIs+funil+canais+heatmap+insights · **Conta & privacidade (LGPD)** | 12+ funcionais |
| Custo de infra | €0 (ainda local) | €8–15/mês |

---

## Como trabalhar daqui pra frente

**TDD por feature:**
1. Escrever o teste da feature (mesmo que fique vermelho)
2. Implementar o mínimo para passar
3. Refatorar mantendo testes verdes
4. Commit seguindo Conventional Commits (`feat:`, `fix:`, `refactor:`, ...)
5. Abrir PR → CI roda → merge em `main` → deploy automático para staging
6. Tag `v*` → deploy para produção (com aprovação manual)

**Fluxo recomendado por dia:**
- Um módulo/feature por vez, do backend até o frontend
- Não pular testes — eles são o contrato
- ADR novo sempre que tomar decisão arquitetural não trivial
