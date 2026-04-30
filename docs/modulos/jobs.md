# Módulo Jobs (Aggregator)

**Propósito:** agregar vagas de múltiplas fontes (APIs públicas, RSS, scraping leve), normalizar para schema único, deduplicar entre fontes, oferecer feed filtrável + matching por perfil + busca full-text.

## Endpoints / Comandos

| Método | Rota / Comando | Handler |
|---|---|---|
| `GET` | `/api/jobs?q=&modality=&seniority=&stack[]=` | `JobController@index` |
| `GET` | `/api/jobs/{job}` | `JobController@show` |
| `GET` | `/api/jobs/matching` | `JobController@matching` |
| CLI | `php artisan jobs:aggregate {source?*}` | `AggregateJobsCommand` |
| CLI | `php artisan jobs:deactivate-expired` | (scheduler sáb/dom) |

## Backend

**Controller:** `app/Http/Controllers/Api/JobController.php`

**Domínio:** `app/Domain/Job/`
- **Contract:** `Aggregator/Contracts/JobSourceDriver.php` — `name(): string`, `fetch(): iterable<JobDTO>`
- **DTO:** `Aggregator/DTOs/JobDTO.php` (spatie/laravel-data) — schema canônico
- **Drivers:** `Aggregator/Drivers/` — `RemoteOkDriver`, `ArbeitnowDriver`, `RemotiveDriver`, `WeWorkRemotelyDriver`, `GupyDriver`
- **Pipeline:** `Aggregator/Pipeline/` — `NormalizeJob`, `DeduplicateJob`, `PersistJob`
- **Action:** `Aggregator/Actions/SyncJobsFromSource.php`
- **Query:** `Queries/ListMatchingJobs.php`

**Config:** `config/aggregator.php`
- `enabled_sources` (env `AGGREGATOR_SOURCES`, default todos)
- `drivers` — mapa `key => Driver::class`
- `schedule_cron` (env `AGGREGATOR_SCHEDULE_CRON`, default `0 */6 * * *`)

**Container binding:** `AppServiceProvider::register` instancia cada driver e marca com `tag('job.drivers')`. `AggregateJobsCommand` recebe via `iterable<JobSourceDriver>` injetado por `tagged('job.drivers')`.

**Models:** `app/Models/Job.php` (inclui `contact_email` em fillable), `Company.php`, `JobSource.php`

### Pipeline de agregação

```
Driver.fetch() → NormalizeJob → DeduplicateJob → PersistJob
```

1. **NormalizeJob:** `trim`, strip de HTML em `description`, normaliza `stack[]` (lowercase + únicos), remove campos vazios. **Extrai `contact_email`** da descrição via regex pipeline (padrões: `mailto:`, `@company.com`, etc.) e seta `JobDTO::contactEmail`.
2. **DeduplicateJob:** calcula `canonical_hash = SHA256(normalize(title) + normalize(company) + normalize(location))`. Confronta com `jobs.canonical_hash` (índice único).
3. **PersistJob:**
   - Se `Job` não existe → cria `Company` (firstOrCreate por `name`) + `Job` (inclui `contact_email`)
   - Se existe → upsert apenas em `JobSource` (`job_id` ↔ `external_id`/`external_url`/`fetched_at`)
   - Vagas duplicadas entre fontes → mesma linha em `jobs`, múltiplos `job_sources`

### Matching

`ListMatchingJobs` calcula:
```
score = |user.skills ∩ job.stack|
      + (job.modality == profile.modality ? 1 : 0)
      + (job.seniority == profile.seniority ? 2 : 0)
```
Filtra `score > 0`, ordena por `score DESC, posted_at DESC`.

### Busca full-text

`Job` usa `Laravel\Scout\Searchable`. `GET /api/jobs?q=...` chama `Job::search($q)`.
- **Driver:** `collection` em testes; **`database`** (Postgres LIKE/ILIKE) em staging/prod. Meilisearch foi removido em 2026-04-24 pra economizar RAM em VPS pequena — se precisar de typo tolerance/ranking real, considerar `pg_trgm` + GIN ou pacote scout-postgres.
- **Indexável:** `title`, `description`, `company.name`, `stack[]`.

## Frontend

**Arquivos:** `frontend/src/modules/jobs/`

- **`useJobFilters`** (`composables/useJobFilters.ts`):
  - State: `q, modality, seniority, stack[], matchOnly`
  - `queryParams` computed (omite vazios)
  - `toggleStack(tag)`, `reset()`
- **`useApplyToJob`** (`composables/useApplyToJob.ts`):
  - Mutation `POST /api/applications`
  - Trata `409` (`'duplicate'`), `422` (`'validation'`)
  - Suporta campos opcionais `email_message_override` e `email_resume_id_override` para candidatura por e-mail
- **`JobsListView`** (`views/JobsListView.vue`):
  - TanStack Query alterna entre `/api/jobs` e `/api/jobs/matching` (toggle `matchOnly`)
  - Tags rápidas: `php, laravel, vue, typescript, python, node, react, go`
  - **Resume selector** (dropdown) → passa `resume_id` ao `useApplyToJob`
  - Botão **Aplicar** → mutation, depois abre `external_url` em nova aba
  - Estados otimistas: `applyingJobId`, `appliedJobIds`, `alreadyAppliedJobIds`
  - **Email tag**: vagas com `contact_email` exibem chip `chip-brand` com ícone de envelope e label `t('jobs.email_tag')`
  - **ApplyByEmailModal** (`components/ApplyByEmailModal.vue`): modal exibido quando `email_apply_enabled` está ativo e a vaga tem `contact_email` com mode `variable`. Permite editar mensagem e selecionar currículo antes de enviar. Se modes forem `fixed`, o envio é direto sem modal.

## Efeitos colaterais

- Escritas: `jobs` (inclui `contact_email`), `companies`, `job_sources`
- Migration: `2026_04_21_000200_add_contact_email_to_jobs`
- Sincronização via Scout `database` driver (escreve direto na tabela, sem queue/serviço externo)
- HTTP outbound para APIs/RSS/Gupy

## Testes

- `backend/tests/Feature/Aggregator/` — `RemoteOkDriver`, `Arbeitnow`, `Remotive`, `WeWorkRemotely`, `Gupy`, `Deduplicate`, `Persist`, `SyncJobs`, `Command`
- `backend/tests/Feature/Jobs/` — `ListJobsTest`, `MatchingJobsTest`, `Search`
- `frontend/tests/useJobFilters.test.ts`
- `frontend/tests/useApplyToJob.test.ts`

## Pontos de atenção

- **Driver quebra quando ATS muda HTML** (Gupy especialmente). Cada driver tem teste isolado com fixture HTML/JSON — atualize a fixture quando reproduzir o bug, depois ajuste o parser.
- **Normalização de hash é case-sensitive na lógica.** Se mudar o `NormalizeJob`, todas as duplicatas históricas precisam ser re-hashadas. Nunca mexa sem migration de re-hash.
- **`config/aggregator.php` é cacheado** em prod (`config:cache`). Mudanças em env de `AGGREGATOR_SOURCES` exigem `php artisan config:clear` + reinício.
- **Matching retorna vazio?** Verificar:
  1. `profile.skills` está populado? (`/api/profile` retorna `skills: []`?)
  2. `jobs.stack` foi normalizado em lowercase no `NormalizeJob`?
  3. Comparação é case-sensitive em `ListMatchingJobs` — se não for, ajustar.
- **Scout em testes:** driver `collection` não suporta filtros complexos. Em CI, busca textual roda em memória; em prod, via Postgres LIKE — divergências de comportamento são possíveis.
- **`jobs.posted_at` vem do driver** — alguns devolvem timestamp local, outros UTC. Conferir no driver antes de comparar com `now()`.
- **Throttle:** `/api/jobs` não tem throttle nominal (só o global do Sanctum). Se virar problema, criar limiter `feed`.
- **`active=false` em vagas expiradas:** rotina sáb/dom. Vaga pode ainda aparecer no feed entre a expiração e a varredura — filtrar `WHERE active=true` na query do `index`.
- **`contact_email` extraído por regex** em `NormalizeJob::extractContactEmail()`. Pode gerar falsos positivos (e-mails de suporte, não de RH). Se reportarem envio para e-mail errado, revisar os padrões de regex.
- **Email-apply depende do perfil configurado** (`email_apply_enabled=true`). Se o botão de aplicar não mostra opção de e-mail, verificar perfil do user.
- **`jobs.language` deve estar no enum canônico (`pt_BR | en | es`).** Drivers devem emitir essas strings (não `pt` cru) — o `LocaleEnum` Zod do front faz throw em valores fora do enum, e a tela de vagas trava com erro mesmo que o backend retorne 200. O `JobFactory` e o `GupyDriver` agora usam `pt_BR`. Se adicionar driver novo, conferir que ele emite valores válidos. Migration `2026_04_30_000200_normalize_jobs_language` já normalizou dados antigos.
