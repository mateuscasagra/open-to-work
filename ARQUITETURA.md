# Open to Work — Arquitetura do Sistema

**Versão:** 1.2
**Data:** 2026-05-04
**Stack principal:** Laravel 12 (API) + Vue 3 (SPA) + PostgreSQL + Redis
**Hospedagem:** Hostinger VPS (self-hosted) + Cloudflare (CDN/WAF)
**Modelo de negócio:** 100% gratuito no MVP

---

## 1. Visão Geral

**Open to Work** é uma plataforma para organizar e acelerar a jornada de busca por emprego. O sistema:
- Agrega vagas de múltiplas fontes públicas (APIs + ATS BR)
- Permite ao usuário aplicar, acompanhar status e medir performance
- Oferece builder de currículos (PDF gerado no cliente)
- Entrega recomendações personalizadas baseadas em perfil

### 1.1 Princípios arquiteturais

| Princípio | Decisão |
|---|---|
| Custo baixo | Hostinger VPS self-hosted + Cloudflare free tier (CDN/WAF/DNS) |
| Escalabilidade | API stateless + fila assíncrona Redis + cache agressivo |
| Segurança | Laravel Sanctum, TLS, criptografia de PDFs, LGPD/GDPR |
| i18n | `laravel-lang` (backend) + `vue-i18n` (frontend) — PT-BR, EN, ES |
| DX | Monorepo + CI/CD automatizado (GitHub Actions) |

### 1.2 Decisões consolidadas

| Área | Decisão |
|---|---|
| **Billing** | 100% gratuito no MVP; monetização discutida na v1.1+ |
| **Geração de PDF** | No cliente via `jsPDF` / `pdf-lib` (custo zero de servidor) |
| **Captura de vagas** | Agregação automática de APIs públicas e ATS BR |
| **OAuth** | Google + LinkedIn + GitHub (via Laravel Socialite) |
| **Repositório** | Monorepo único |
| **Hospedagem** | Hostinger VPS (self-hosted com Docker) |
| **Sync de vagas** | Laravel Scheduler rodando a cada 6h |

---

## 2. Arquitetura de Alto Nível

```
┌─────────────────────────────────────────────────────────────┐
│                        CLOUDFLARE                            │
│          (CDN + WAF + DNS + Cache estático + TLS)            │
└──────────────────────────┬──────────────────────────────────┘
                           │
                    ┌──────────────┐
                    │ Hostinger VPS│   (Docker Compose)
                    └──────┬───────┘
                           │
      ┌────────────────────┼────────────────────────┐
      │                    │                        │
┌─────▼──────┐      ┌──────▼──────┐         ┌───────▼──────┐
│ FrankenPHP │      │ Laravel API │         │  Vue SPA     │
│ (Caddy+PHP)│◄────►│  (Sanctum)  │         │ (build est.) │
└─────┬──────┘      └──────┬──────┘         └──────────────┘
      │                    │
      │              ┌─────┴─────┐
      │              │           │
┌─────▼─────┐  ┌─────▼─────┐  ┌──▼────────┐  ┌─────────────┐
│PostgreSQL │  │   Redis   │  │  Workers  │  │ Cloudflare  │
│ 16 + FTS  │  │ (cache+q) │  │ (queue)   │  │     R2      │
└───────────┘  └───────────┘  └─────┬─────┘  └─────────────┘
                                    │
                ┌───────────────────┼────────────────────┐
                │                   │                    │
        ┌───────▼──────┐    ┌───────▼───────┐   ┌────────▼───────┐
        │ Aggregators  │    │ Notifications │   │ Metrics ETL    │
        │ (6h cron)    │    │ (email)       │   │ (daily rollup) │
        └──────────────┘    └───────────────┘   └────────────────┘
```

---

## 3. Stack Tecnológico

### 3.1 Backend
- **Laravel 12** — framework principal
- **PHP 8.3** rodando em **FrankenPHP** (Caddy + PHP em um único binário, mode worker)
- **Laravel Sanctum** — autenticação SPA (cookie HttpOnly) + tokens pessoais
- **Laravel Socialite** — OAuth Google/LinkedIn/GitHub
- **Laravel Horizon** — gerenciamento de filas Redis
- **Laravel Scout + driver `database`** — busca usa Postgres `ILIKE` (Meilisearch foi removido pra economizar RAM; ver `docs/modulos/jobs.md`)
- **Spatie MediaLibrary** — gestão de uploads (PDFs/anexos)
- **spatie/laravel-backup** — backup automatizado para Cloudflare R2
- **Pest** — framework de testes

### 3.2 Frontend
- **Vue 3** (Composition API) + **TypeScript**
- **Vite** — build tool (gera assets estáticos servidos pelo Nginx)
- **Pinia** — state management
- **Vue Router**
- **vue-i18n** — lazy-load de locales
- **TailwindCSS** + **shadcn-vue** — UI
- **TanStack Query** — cache de requests
- **Zod** — validação
- **jsPDF / pdf-lib** — geração de PDF dos currículos 100% no cliente

### 3.3 Infraestrutura (Hostinger self-hosted)

| Componente | Solução | Observação |
|---|---|---|
| Compute | Hostinger VPS (KVM) | Suficiente para MVP (centenas de usuários) |
| Orquestração | Docker Compose | Zero complexidade inicial |
| DB | PostgreSQL 16 em container | Backup diário para Cloudflare R2 |
| Cache/Queue | Redis 7 em container | |
| Busca | Postgres full-text (`ILIKE` via Scout `database`) | Sem dependência externa; Meilisearch foi removido |
| Storage objeto (prod) | **Cloudflare R2** | Currículos e anexos; zero egress |
| Storage objeto (dev) | MinIO em container | API S3-compatível para parity local |
| Reverse proxy/TLS | FrankenPHP (Caddy embutido) — TLS automático | |
| CDN/DNS/WAF | Cloudflare (free) | Protege contra DDoS/bots |
| E-mail transacional | Resend (3k/mês grátis) | MailHog em dev (`http://localhost:8025`) |
| Monitoring | Sentry (free) | Backend + frontend |

**Custo mensal estimado MVP:** ~$5–8 (VPS) + $0 (Cloudflare R2 sob free tier + Sentry/Resend free tiers).

---

## 4. Módulos e Domínios

### 4.1 Autenticação & Perfil
- Cadastro/login via e-mail/senha + OAuth (Google, LinkedIn, GitHub)
- 2FA opcional (TOTP via `pragmarx/google2fa`)
- Perfil: cargo desejado, seniority, stack, localização, modalidade, faixa salarial, idiomas
- **LinkedIn OAuth bonus:** importa dados básicos do perfil no primeiro login

### 4.2 Currículos
- **Builder estruturado:** experiências, formação, skills, idiomas, projetos
- **Múltiplas versões:** "Backend Sênior", "Fullstack Pleno", etc.
- **Export PDF no cliente:** templates renderizados em HTML → `html2canvas` + `jsPDF` (ou `pdf-lib` puro)
- **Upload de PDF externo:** armazenado em **Cloudflare R2** (em dev: MinIO via mesma API S3) com URL temporária assinada (TTL 5min) e visibilidade `private`
- **Versionamento:** histórico de alterações para recuperar

### 4.3 Candidaturas (applications)
- Fluxo: usuário vê vaga no feed → clica "aplicar" → sistema registra candidatura + abre link externo em nova aba
- **Status board (Kanban):** `Aplicada → Triagem → Teste → Entrevista RH → Entrevista Técnica → Proposta → Aceita/Recusada`
- Timeline de eventos, anotações, anexos (e-mails de feedback, etc.)
- Vinculação: candidatura ↔ vaga ↔ currículo usado

### 4.4 Agregação de Vagas (core do produto)

Pipeline de agregação roda via **Laravel Scheduler a cada 6h**:

```
┌──────────────┐   ┌──────────────┐   ┌──────────────┐   ┌────────────┐
│  Aggregator  │──►│  Normalizer  │──►│  Deduplicator│──►│  Postgres  │
│  (por fonte) │   │(schema único)│   │ (hash título+│   │ (indexado) │
└──────────────┘   └──────────────┘   │  empresa+loc)│   └────────────┘
                                      └──────────────┘
```

> Busca usa Postgres `ILIKE` via Scout `database` driver — Meilisearch foi removido em 2026-04-24 pra economizar RAM no VPS. Detalhes em `docs/modulos/jobs.md`.

#### Fontes (drivers plugáveis)

| Fonte | Tipo | Status |
|---|---|---|
| **RemoteOK** | API JSON pública | MVP |
| **Arbeitnow** | API JSON pública | MVP |
| **Remotive** | API JSON pública | MVP |
| **WeWorkRemotely** | RSS | MVP |
| **Gupy (páginas públicas)** | Scraping leve de listagens públicas | MVP |
| **Kenoby** | Scraping páginas públicas | v1.1 |
| **Solides** | Scraping páginas públicas | v1.1 |
| **Adzuna (opcional)** | API (plano gratuito limitado) | v1.2 |

> **Contrato de driver:** cada fonte implementa `JobSourceDriver` com método `fetch(): iterable<JobDTO>`. Novas fontes = nova classe + registro no container. Zero mudança no resto do código.

#### Schema normalizado (JobDTO)

```
id_external, source, title, company_name, company_logo_url,
description_html, location, modality (remote/hybrid/onsite),
contract_type, salary_min, salary_max, salary_currency,
stack[], seniority, external_url, posted_at, expires_at
```

#### Deduplicação
Hash SHA-256 de `(normalize(title) + normalize(company) + normalize(location))`. Vagas duplicadas entre fontes apontam para a mesma linha canônica com múltiplos `external_urls`.

#### Matching com perfil do usuário
Query combinada:
- Stack com interseção ≥ 1 skill (`jobs.stack && user.skills`)
- Seniority compatível (`job.seniority IN user.seniority_range`)
- Modalidade compatível
- Ordenação: score de match (interseção de stacks * peso) + recência

### 4.5 Métricas
- Taxa de resposta por currículo
- Taxa de aprovação por etapa (onde você mais cai)
- Tempo médio entre etapas
- Fontes/canais mais efetivos
- Heatmap de dias/horas com maior retorno
- **Implementação:** tabela `metrics_daily` materializada via job noturno + cache Redis

### 4.6 Notificações
- E-mail (Resend): lembretes de follow-up, novas vagas que batem com o perfil
- Web push (v1.2)

---

## 5. Modelo de Dados (resumido)

```sql
users (id, name, email, password, locale, 2fa_secret, created_at, ...)
oauth_accounts (user_id, provider, provider_id, access_token_enc)
profiles (user_id, desired_role, seniority, modality, salary_min/max, location, languages[])
skills (id, name, category, aliases[])
profile_skills (profile_id, skill_id, proficiency)

resumes (id, user_id, title, language, is_pdf_upload, file_path, metadata_json)
resume_sections (resume_id, type, order, content_json)

companies (id, name, domain, logo_url, linkedin_url)
jobs (
  id, canonical_hash, title, company_id,
  description_html, location, modality,
  stack[], seniority, salary_min, salary_max, currency,
  posted_at, expires_at, active
)
job_sources (job_id, source, external_id, external_url, fetched_at)

applications (
  id, user_id, job_id, resume_id,
  manual_title, manual_company, job_url,
  status, applied_at, source, notes,
  expected_salary, sent_via_email_at, archived_at
)
application_events (application_id, event_type, payload_json, occurred_at)

metrics_daily (user_id, date, applications_count, responses_count, ...)  -- materialized

-- Índices críticos
CREATE INDEX jobs_stack_gin ON jobs USING GIN (stack);
CREATE INDEX applications_user_status ON applications (user_id, status);
CREATE INDEX jobs_posted_at ON jobs (posted_at DESC) WHERE active = true;
CREATE UNIQUE INDEX jobs_canonical ON jobs (canonical_hash);
```

---

## 6. Segurança

| Camada | Controle |
|---|---|
| Rede | Cloudflare WAF + rate limiting no edge |
| Firewall VPS | `ufw` — apenas 80/443 abertos; SSH por chave em porta alta |
| Transporte | TLS 1.3 via Let's Encrypt (Certbot) + HSTS |
| Auth | Sanctum + cookies `HttpOnly/Secure/SameSite=Lax` |
| Senhas | bcrypt (cost 12) |
| 2FA | TOTP (`pragmarx/google2fa`) |
| OAuth | Socialite; tokens guardados encriptados (Laravel `encrypted` cast) |
| Autorização | Policies Laravel por recurso |
| CSRF | Token padrão do Laravel |
| XSS | Escape automático Blade/Vue + CSP restritivo |
| SQLi | Eloquent/Query Builder apenas |
| Upload | Validação MIME real + tamanho + ClamAV (opcional) |
| PDFs | Cloudflare R2 com `visibility=private` e URLs temporárias (TTL 5min) |
| Logs | Sem PII; formato JSON estruturado |
| Secrets | `.env` nunca commitado |
| LGPD/GDPR | Consentimento explícito, export/delete de dados, política de retenção |
| Backup | pg_dump diário → Cloudflare R2; teste de restore mensal |

---

## 7. Internacionalização (i18n)

- **Backend:** `lang/{pt_BR,en,es}/*.php`, middleware lê Accept-Language e/ou `user.locale`
- **Frontend:** `vue-i18n` com lazy-load por rota
- **Dados do usuário:** `profile.locale` + `resume.language`
- **Vagas agregadas:** detecção de idioma via `franc` (heurística) — campo `jobs.language`
- **Interface padrão:** PT-BR; fallback EN

---

## 8. Escalabilidade

**Estratégia de crescimento (do MVP ao produto):**

| Estágio | Usuários | Infra |
|---|---|---|
| MVP | 0–1k | 1 Hostinger VPS (tudo junto: app + Postgres + Redis) |
| Growth | 1k–10k | Upgrade do plano da Hostinger + Cloudflare cache agressivo (R2 já externo) |
| Scale | 10k–50k | Separar DB em VPS dedicada; workers em VPS separada |
| Enterprise | 50k+ | Migrar para K8s managed, replicas de DB |

**Princípios:**
- API stateless desde o dia 1 (sessions em Redis, arquivos em storage objeto)
- Workers de fila separáveis (escala independente do web)
- Cache agressivo em Redis (vagas, métricas, traduções)
- Rate limiting por usuário/IP (Cloudflare + app)
- Jobs assíncronos para: parsing de PDFs, agregação de vagas, emails, métricas

---

## 9. CI/CD (GitHub Actions)

```
push/PR ──► lint + static + test ──► build Vue ──► build Docker ──► deploy
```

**Pipelines:**
1. **PR check:** PHPStan (nível 8), Laravel Pint, ESLint, Prettier, Pest, Vitest, build Vue
2. **Merge `main` → staging:** build das imagens, `docker stack deploy` em staging VPS, migrations automáticas, smoke tests
3. **Tag `v*` → produção:** deploy com aprovação manual (GitHub Environments)

**Deploy:** imagens Docker enviadas ao GitHub Container Registry (grátis para repos públicos/privados até 500MB) + `docker compose pull && up -d` na VPS via SSH.

**Zero-downtime:** rolling update do Swarm OU blue-green via Nginx upstream toggle.

**Migrations:** sempre backward-compatible (expand/contract).

---

## 10. Estrutura do Monorepo

```
open-to-work/
├── backend/                 # Laravel 12 API
│   ├── app/
│   │   ├── Domain/          # DDD-lite por bounded context
│   │   │   ├── Application/ # candidaturas
│   │   │   ├── Resume/
│   │   │   ├── Job/
│   │   │   │   └── Aggregator/   # drivers por fonte
│   │   │   ├── Metrics/
│   │   │   └── User/
│   │   ├── Http/Controllers/Api/
│   │   └── Jobs/            # jobs de fila
│   ├── database/migrations/
│   ├── tests/
│   ├── routes/api.php
│   └── Dockerfile
├── frontend/                # Vue 3 SPA
│   ├── src/
│   │   ├── modules/         # feature-based
│   │   │   ├── auth/
│   │   │   ├── applications/
│   │   │   ├── resumes/
│   │   │   ├── jobs/        # feed + busca
│   │   │   └── metrics/
│   │   ├── shared/
│   │   └── locales/
│   ├── tests/
│   └── Dockerfile
├── infra/
│   ├── docker-compose.yml   # dev local
│   ├── docker-stack.yml     # produção (Swarm)
│   ├── nginx/
│   └── scripts/             # backup, deploy, restore
├── .github/workflows/
│   ├── ci.yml
│   ├── deploy-staging.yml
│   └── deploy-prod.yml
├── docs/
│   └── adr/                 # Architecture Decision Records
├── ARQUITETURA.md
└── README.md
```

---

## 11. Roadmap de Entrega

### MVP (3 meses) — foco: funciona e agrega vagas
- [ ] Auth (e-mail + Google/LinkedIn/GitHub) + perfil + i18n (PT/EN/ES)
- [ ] CRUD de candidaturas + kanban de status
- [ ] Agregadores: RemoteOK, Arbeitnow, Remotive, WeWorkRemotely, Gupy
- [ ] Feed de vagas com filtros (stack, seniority, modalidade, localização)
- [ ] Matching básico por perfil do usuário
- [ ] Upload de PDFs + listagem
- [ ] Dashboard básico de métricas
- [ ] Deploy Hostinger + CI/CD GitHub Actions

### v1.1 (+2 meses) — builder + mais fontes
- [ ] Resume builder estruturado + export PDF no cliente
- [ ] Agregadores: Kenoby, Solides
- [ ] Follow-up automático por e-mail
- [ ] Exportar candidaturas (CSV/JSON)

### v1.2 (+2 meses) — inteligência
- [ ] Métricas avançadas + insights
- [ ] Web push notifications
- [ ] Integração Adzuna (opcional)
- [ ] Detecção de idioma nas vagas

### v2 (6 meses+) — diferenciação
- [ ] Matching por embeddings semânticos
- [ ] Sugestões de melhoria de currículo (LLM)
- [ ] Mobile (Capacitor) — opcional

---

## 12. Riscos & Mitigações

| Risco | Mitigação |
|---|---|
| Scraping de ATS quebra quando mudam HTML | Drivers isolados + alertas Sentry + fallback para fontes redundantes |
| Duplicação de vagas entre fontes | Hash canônico + revisão manual dos edge cases nos primeiros meses |
| Custo de storage escalando com PDFs | Lifecycle policy (PDFs antigos → cold storage); limite de PDFs no tier free |
| VPS única = SPOF | Backup diário off-site + script de provisionamento reproduzível (Ansible/Terraform) |
| LGPD em caso de vazamento | Criptografia em repouso + pentest anual + plano de resposta a incidente |
| Falta de vagas BR no MVP | Priorizar Gupy na v1 (cobertura ampla de empresas BR) |

---

## 13. Design Patterns & Convenções de Código

### 13.1 Arquitetura geral
- **DDD-lite (bounded contexts):** pastas por domínio em `app/Domain/{User,Resume,Job,Application,Metrics}`. Sem agregar complexidade de DDD completo (sem Repositories custom em cima do Eloquent, sem ValueObjects onde um scalar basta).
- **CQRS-lite (leitura vs escrita):** separar comandos (`Actions/`) de queries (`Queries/`). Comandos mudam estado; queries leem e retornam DTOs.
- **Ports & Adapters (hexagonal) apenas onde faz sentido:** integrações externas (agregadores de vagas, provedores de OAuth, storage) são expostas por interfaces (`ports`) e implementadas por drivers concretos (`adapters`).

### 13.2 Padrões backend (Laravel)

| Padrão | Uso no projeto | Biblioteca |
|---|---|---|
| **Action classes** | 1 classe = 1 caso de uso (`CreateApplication`, `SyncJobsFromSource`). Elimina controllers gordos e services genéricos. | `lorisleiva/laravel-actions` (opcional) |
| **DTOs (Data Transfer Objects)** | Entrada/saída de Actions e boundaries com APIs externas. `JobDTO`, `ResumeDTO`, `ApplicationDTO`. | `spatie/laravel-data` |
| **Strategy pattern** | Drivers de agregação: `JobSourceDriver` com `RemoteOkDriver`, `GupyDriver`, etc. Registro via container. | Nativo |
| **Pipeline pattern** | Fluxo de agregação: `Fetch → Normalize → Deduplicate → Persist`. Cada estágio é uma classe. | `Illuminate\Pipeline` |
| **Events & Listeners** | `ApplicationStatusChanged`, `JobMatched`, `UserRegistered`. Listeners assíncronos via fila. | Nativo |
| **Policies** | Autorização por recurso (`ApplicationPolicy@update`). Zero lógica de auth em controller. | Nativo |
| **Form Requests** | Toda validação de input vive em classes dedicadas. Controllers não validam. | Nativo |
| **Model Observers** | Side-effects de ciclo de vida (ex: gerar hash canônico de job ao salvar). | Nativo |
| **Repository pattern** | **NÃO usar.** Eloquent já é um repositório; abstrair em cima dele é cerimônia sem ganho. |  |
| **Service classes genéricas** | **Evitar.** Preferir Actions (uma responsabilidade) a `JobService` com 20 métodos. | |

### 13.3 Padrões frontend (Vue 3)

| Padrão | Uso no projeto |
|---|---|
| **Composables** (`use*`) | Lógica reutilizável: `useAuth()`, `useApplications()`, `useJobFilters()`. Substitui mixins. |
| **Feature modules** | `src/modules/{auth,jobs,applications,resumes,metrics}` com `views/`, `composables/`, `stores/`, `components/` internos. |
| **Pinia stores por domínio** | Um store por bounded context, não um global. |
| **Adapter layer para HTTP** | `modules/shared/api/` expõe clients tipados (Zod-validated) — componentes nunca chamam `axios` direto. |
| **State machine (XState-lite)** | Fluxo de status de candidatura modelado explicitamente: evita transições inválidas (`Recusada → Proposta`). |
| **Container/Presentational** | Views fazem orchestration (dados + ações); components são dumb/puros. |

### 13.4 Padrões de domínio específicos

- **Specification pattern** (opcional, v1.1+) — encapsular regras de matching vaga↔usuário em `MatchesUserProfile` reutilizável.
- **Factory pattern** — fábricas de modelos para testes e seeds (`UserFactory`, `JobFactory`).
- **Builder pattern** — para montar PDFs de currículo no cliente (`ResumePdfBuilder`).
- **Adapter pattern** — normalizar payloads heterogêneos das fontes de vagas no `JobDTO` canônico.

### 13.5 Convenções de código

- **PHP:** PSR-12 + Laravel Pint (config default).
- **TypeScript:** strict mode ligado; `any` proibido em código novo (warning no ESLint).
- **Nomenclatura:**
  - Actions: verbo + substantivo (`CreateApplication`, `SyncJobs`).
  - Queries: `Get*` ou `List*` (`GetUserMetrics`, `ListMatchingJobs`).
  - Events: passado (`ApplicationCreated`, `JobMatched`).
  - Jobs de fila: imperativo (`SendFollowUpEmail`, `RefreshJobSources`).
- **Testes:** Pest no backend, Vitest no frontend. Cobertura mínima: 80% em `Domain/`.
- **Architecture Decision Records (ADRs):** decisões não óbvias documentadas em `docs/adr/NNNN-titulo.md`.
- **Commits:** Conventional Commits (`feat:`, `fix:`, `refactor:`, ...).

### 13.6 O que NÃO fazer (anti-patterns)

- Repository pattern sobre Eloquent (reinventa o que já existe).
- Service classes gigantes com 20 métodos públicos (preferir Actions).
- Lógica de negócio em controllers ou em Blade/Vue templates.
- `Facades` custom (preferir DI explícita).
- Magic strings para status (usar Enums PHP 8.1).
- Estado global no frontend (preferir Pinia stores por módulo).
- Abstração prematura — só introduza interface quando existirem 2+ implementações reais.

---

## 14. Próximos Passos Imediatos

1. Criar estrutura do monorepo com `backend/`, `frontend/`, `infra/`
2. Scaffolding do Laravel 11 + Sanctum + Socialite
3. Scaffolding do Vue 3 + Vite + Pinia + vue-i18n
4. `docker-compose.yml` de dev (app + Postgres + Redis + MinIO + MailHog)
5. Migrations iniciais (users, profiles, jobs, applications)
6. Primeiro driver de agregação (RemoteOK) como prova de conceito
7. GitHub Actions: pipeline de CI (lint + test)
8. Documentar primeiros ADRs em `docs/adr/`
