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
- **Drivers:** `Aggregator/Drivers/` — `RemoteOkDriver`, `ArbeitnowDriver`, `RemotiveDriver`, `WeWorkRemotelyDriver`, `GitHubVagasDriver` (lê issues abertas em comunidades BR — `frontendbr/vagas`, `backend-br/vagas`, etc., configurável em `aggregator.github_repos`)
- **Filter:** `Aggregator/Filters/ProgrammingJobFilter.php` — whitelist de cargos/linguagens/frameworks; usado pelos 4 drivers internacionais (RemoteOk, Remotive, Arbeitnow, WeWorkRemotely) pra descartar vagas não-tech antes de chegar à pipeline. **`GitHubVagasDriver` não usa o filtro** porque lê comunidades de programação por definição.
- **Pipeline:** `Aggregator/Pipeline/` — `NormalizeJob`, `DeduplicateJob`, `PersistJob`
- **Action:** `Aggregator/Actions/SyncJobsFromSource.php`
- **Query:** `Queries/ListMatchingJobs.php`

**Config:** `config/aggregator.php`
- `enabled_sources` (env `AGGREGATOR_SOURCES`, default todos)
- `drivers` — mapa `key => Driver::class`
- `schedule_cron` (env `AGGREGATOR_SCHEDULE_CRON`, default `0 */6 * * *`)

**Container binding:** `AppServiceProvider::register` instancia cada driver e marca com `tag('job.drivers')`. `AggregateJobsCommand` recebe via `iterable<JobSourceDriver>` injetado por `tagged('job.drivers')`.

**Models:** `app/Models/Job.php` (inclui `contact_email` em fillable), `Company.php`, `JobSource.php`

### Filtro de programação (drivers internacionais)

`ProgrammingJobFilter::isProgrammingJob(string $title, iterable $tags = []): bool` — match case-insensitive no haystack `título + tags` contra whitelist de:
- **Cargos** — `developer`, `engineer`, `programmer`, `devops`, `sre`, `fullstack`, `backend`, `frontend`, `software`, `data engineer`, `ml engineer`, `qa engineer`, `tech lead`, `embedded`, `cloud engineer`, `platform engineer`, etc.
- **Linguagens** — `php`, `python`, `javascript`, `typescript`, `ruby`, `golang`, `rust`, `kotlin`, `swift`, `c#`, `.net`, `elixir`, etc.
- **Frameworks** — `laravel`, `rails`, `django`, `react`, `vue`, `angular`, `next.js`, `node.js`, `flutter`, `spring boot`, `phoenix`, etc.
- **Infra** — `kubernetes`, `docker`, `terraform`, `graphql`, `microservices`.

Aplicado em:
| Driver | Estratégia |
|---|---|
| `RemoteOkDriver` | filter sobre `position` + `tags` |
| `RemotiveDriver` | request `?category=software-dev` (filtro nativo da API) **+** filter como defesa |
| `ArbeitnowDriver` | filter sobre `title` + `tags` |
| `WeWorkRemotelyDriver` | feed já é `remote-programming-jobs.rss`; filter sobre `title` como defesa em profundidade |
| `GitHubVagasDriver` | **não filtra** — comunidades BR já são tech |

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
  - State: `q, modality, seniority, matchOnly` (stack[] e language[] foram removidos junto com os filtros UI deles)
  - `queryParams` computed (omite vazios)
  - `reset()`
- **`useApplyToJob`** (`composables/useApplyToJob.ts`):
  - Mutation `POST /api/applications`
  - Trata `409` (`'duplicate'`), `422` (`'validation'`)
  - Payload: `{ jobId, source?, resumeId?, notes?, expectedSalary? }`
- **`JobsListView`** (`views/JobsListView.vue`):
  - TanStack Query alterna entre `/api/jobs` e `/api/jobs/matching` via toggle "Só compatíveis com meu perfil"
  - **Filtros**: busca textual, modalidade, senioridade, e o toggle de matching. Filtros antigos (stack tags rápidas, idiomas) foram removidos. Filtros não vêm pré-populados do perfil — o usuário aplica manualmente
  - **Resume selector** (dropdown) → passa `resume_id` ao `useApplyToJob`. Compartilha a mesma `<section>` do toggle de matching (toggle alinhado à direita via `ml-auto`)
  - Botões por card: **Visualizar** (abre painel lateral) + **Aplicar** (mutation → abre `external_url` em nova aba)
  - Estados otimistas: `applyingJobId`, `appliedJobIds`, `alreadyAppliedJobIds`
  - **Painel lateral de detalhes** (`selectedJob` ref + `selectJob`/`closeDetail`): layout `lg:flex lg:items-stretch` que empurra a lista pra esquerda quando aberto (push, não overlay). Painel de **760px** fixo com card `lg:overflow-y-auto`. Conteúdo: logo + empresa + título + botão fechar (X), `<dl>` com localização/modalidade/senioridade/salário/data/idioma, chips completas de stack, descrição renderizada via `v-html` (backend já filtra com `strip_tags` whitelist em `NormalizeJob`), botão Aplicar no rodapé. No mobile, lista é escondida via `hidden lg:flex` quando o painel abre — o painel toma a tela inteira até fechar
  - **Paginação** (windowed): janela de **3 botões numerados** com a página atual centrada quando possível (clamp nas bordas) + botão "Próxima". Reseta pra página 1 quando q/modality/seniority/matchOnly mudam (`watch` nos quatro). Usa `placeholderData: keepPreviousData` pra não dar blink durante refetch. `goToPage(p)` faz scroll do `<ul>` (e do `window`) pro topo
  - **Layout (lg+)**: `lg:flex lg:h-[calc(100vh-7rem)] lg:flex-col` — tela travada na altura do viewport menos navbar (4rem) + padding do `<main>` (3rem). Currículo + filtros + contador no topo (`lg:shrink-0`), lista + painel ocupam o resto (`lg:flex-1 lg:min-h-0`). Lista (`<ul>`) usa `lg:flex-1 lg:min-h-0 lg:overflow-y-auto` e o card do painel usa `lg:h-full lg:overflow-y-auto` — ambos rolam internamente. Mobile mantém page-scroll natural (sem altura travada)

## Efeitos colaterais

- Escritas: `jobs` (inclui `contact_email`), `companies`, `job_sources`
- Migration: `2026_04_21_000200_add_contact_email_to_jobs`
- Sincronização via Scout `database` driver (escreve direto na tabela, sem queue/serviço externo)
- HTTP outbound para APIs/RSS dos drivers internacionais e GitHub REST API (api.github.com)

## Testes

- `backend/tests/Feature/Aggregator/` — `RemoteOkDriver`, `Arbeitnow`, `Remotive`, `WeWorkRemotely`, `GitHubVagas`, `Deduplicate`, `Persist`, `SyncJobs`, `Command`
- `backend/tests/Feature/Jobs/` — `ListJobsTest`, `MatchingJobsTest`, `Search`
- `frontend/tests/useJobFilters.test.ts` — cobre só `q`, `modality`, `seniority`, `matchOnly` (stack/language saíram junto com os filtros)
- `frontend/tests/useApplyToJob.test.ts`
- `frontend/tests/schemas.test.ts` — `JobSchema` cobre parse com/sem `description_html` (default `null`)

## Pontos de atenção

- **Driver quebra quando ATS muda HTML/JSON.** Cada driver tem teste isolado com fixture — atualize a fixture quando reproduzir o bug, depois ajuste o parser.
- **Filtro de programação** (`ProgrammingJobFilter`) é uma whitelist de cargos/linguagens/frameworks com blacklist de engenharias não-software (`mechanical engineer`, `civil engineer`, `sales engineer`, etc. são rejeitadas mesmo casando com `engineer`). Se aparecer cargo tech sendo descartado, adicionar em `PROGRAMMING_PATTERNS` em `app/Domain/Job/Aggregator/Filters/ProgrammingJobFilter.php` (ex.: linguagem nova, role como `staff engineer`). Se aparecer cargo não-tech passando, adicionar em `NON_PROGRAMMING_PATTERNS`. **Não filtra `GitHubVagasDriver`** por design — comunidades BR já são tech.
- **Mudança no filtro requer reprocessamento.** Vagas já persistidas não somem do feed automaticamente — só não entram novas. Pra purgar vagas antigas que agora seriam descartadas, fazer cleanup manual ou rodar `jobs:aggregate` após `Job::truncate()` (perigoso em prod).
- **`GitHubVagasDriver` rate-limit**: sem `GITHUB_TOKEN` no .env são 60 req/h (suficiente pra ~6 repos paginados). Em prod usar Personal Access Token (5000 req/h). 404 num repo é tratado com `Log::warning` e segue.
- **Parser de título do GitHub Vagas** depende de padrão `[tags] Empresa - Cargo`. Issues que fogem do padrão caem em `companyName='Comunidade GitHub'` (mantém a vaga, perde a empresa). Se observar muitas vagas com esse fallback, ajustar `splitTitle`/`splitCompanyAndRole`.
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
- **`contact_email` extraído por regex** em `NormalizeJob::extractContactEmail()` ainda roda no backend, mas **o frontend não usa mais** (UI de apply-por-email foi removida). O campo continua no `JobSchema` Zod como `optional/nullable`. Limpeza completa do backend (action `SendApplicationEmail`, colunas `profiles.email_apply_*` e `jobs.contact_email`, validações) está pendente.
- **`jobs.language` deve estar no enum canônico (`pt_BR | en | es`).** Drivers devem emitir essas strings (não `pt` cru) — o `LocaleEnum` Zod do front faz throw em valores fora do enum, e a tela de vagas trava com erro mesmo que o backend retorne 200. O `JobFactory` e o `GitHubVagasDriver` usam `pt_BR`. Se adicionar driver novo, conferir que ele emite valores válidos. Migration `2026_04_30_000200_normalize_jobs_language` já normalizou dados antigos.
- **Layout `lg:h-[calc(100vh-7rem)]` é frágil**: o `7rem` é calculado a partir de `AppLayout` (`navbar h-16` + `main wrapper py-6`). Se mudar a altura do navbar ou o padding do `<main>`, a tela de vagas vai sobrar/faltar pixels. Conferir os dois antes de mexer no `JobsListView`.
- **Painel de detalhes em 760px hardcoded** (`lg:w-[760px]` no `<aside>`). Em viewports < ~1280px o painel ocupa quase metade — considerar reduzir ou usar `clamp()` se o design pedir.
- **Descrição renderiza com `v-html`**: depende do `NormalizeJob::handle()` no backend que faz `strip_tags($html, '<p><br><ul><ol><li><strong><em><a>')`. Se mudar a whitelist no backend pra incluir tags com atributos perigosos (`onclick`, `style`), o front precisa sanitizar (DOMPurify ou similar) antes de `v-html`.
- **Paginação reseta automaticamente** em mudança de `q`/`modality`/`seniority`/`matchOnly` via `watch`. Se adicionar novo filtro, incluí-lo no array do `watch` — senão o usuário fica numa página que pode não existir mais.
