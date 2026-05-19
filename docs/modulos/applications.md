# Módulo Applications

**Propósito:** ciclo completo de candidatura — registrar do feed, mover entre status (Kanban), anexar documentos, receber follow-up automático após 7 dias sem resposta.

## Endpoints

| Método | Rota | Handler | Throttle / Auth |
|---|---|---|---|
| `GET` | `/api/applications?status=<enum>&archived=<0\|1>` | `ApplicationController@index` | `auth:sanctum` |
| `POST` | `/api/applications` | `ApplicationController@store` | `auth:sanctum` |
| `GET` | `/api/applications/{id}` | `ApplicationController@show` | `auth:sanctum` |
| `PATCH` | `/api/applications/{id}` | `ApplicationController@update` | `auth:sanctum` |
| `DELETE` | `/api/applications/{id}` | `ApplicationController@destroy` | `auth:sanctum` |
| `PATCH` | `/api/applications/{id}/status` | `ApplicationController@changeStatus` | `auth:sanctum` |
| `POST` | `/api/applications/{id}/archive` | `ApplicationController@archive` | `auth:sanctum` |
| `POST` | `/api/applications/{id}/unarchive` | `ApplicationController@unarchive` | `auth:sanctum` |
| `GET` | `/api/applications/{id}/attachments` | `ApplicationAttachmentController@index` | `auth:sanctum` |
| `POST` | `/api/applications/{id}/attachments` | `ApplicationAttachmentController@store` | `throttle:uploads` |
| `DELETE` | `/api/applications/{id}/attachments/{media}` | `ApplicationAttachmentController@destroy` | `auth:sanctum` |
| CLI | `php artisan applications:send-followups` | scheduler 09:00 UTC |

## Backend

**Controllers:**
- `app/Http/Controllers/Api/ApplicationController.php`
- `app/Http/Controllers/Api/ApplicationAttachmentController.php`

**Domínio:** `app/Domain/Application/`
- **Actions:** `CreateApplication`, `ChangeApplicationStatus`, `SendApplicationEmail` (legado — descontinuado na UI)
- **DTO:** `ApplicationData` (inclui `emailMessageOverride`, `emailResumeIdOverride` opcionais — legado, frontend não envia mais)
- **Enum:** `app/Enums/EmailApplyMode.php` — `Fixed | Variable` (legado)
- **Mailable:** `app/Mail/ApplicationEmail.php` — legado
- **Blade:** `resources/views/mail/application.blade.php`
- **Exception:** `DuplicateApplicationException` (controller mapeia para `409`)

**Enums:** `app/Enums/ApplicationStatus.php` — apenas labels e `isFinal()`. Sem state machine: qualquer status pode ir pra qualquer status (cada empresa tem seu próprio processo).

**Policy:** `app/Policies/ApplicationPolicy.php` — `view/update/delete` apenas se `user_id` bate

**Notification:** `app/Notifications/ApplicationFollowUpNotification.php` (canal `mail`)

**Command:** `app/Console/Commands/SendApplicationFollowUpsCommand.php`

**Models:**
- `app/Models/Application.php` — usa `HasMedia` + `InteractsWithMedia` (Spatie MediaLibrary)
- `app/Models/ApplicationEvent.php`

### Mudança de status (sem state machine)

Qualquer status pode ir pra qualquer status — incluindo pular fases ou voltar de um terminal (`accepted/rejected/withdrawn → applied`). `ChangeApplicationStatus` faz no-op se `current === target` (não cria evento). Sucesso → dispara `ApplicationStatusChanged`, registra `ApplicationEvent` com `event_type='status_changed'` e `payload={from, to, note?}`.

**Funil de métricas:** conta uma candidatura em uma etapa **só se houve evento explícito** chegando lá. Pular `applied → offer` direto não conta `screening/assessment/interview_*` no funil — cada empresa tem seu próprio processo seletivo (ver `metrics.md`).

### Duplicidade

`CreateApplication` checa `unique(user_id, job_id)`. Se existir, lança `DuplicateApplicationException` → controller responde `409`.

### Auto-fill ao aplicar via feed

Quando `jobId` é passado e `notes`/`jobUrl` vêm `null` no DTO (cenário típico de "Aplicar" na lista de vagas), o `CreateApplication` carrega o Job (com `sources`) e preenche:
- `notes` ← `extractTextFromHtml($job->description_html)` (strip de tags, preservando `<br>` e `</p>` como quebras, decode de entidades, cap em 5000 chars com `…`)
- `job_url` ← `$job->sources->first()?->external_url`

Valores explícitos no DTO **sempre vencem** (operador `??`). Manual application (sem `jobId`) não tenta carregar nada.

### Candidatura por e-mail (legado)

A feature de apply-por-email foi **descontinuada na UI**. O fluxo backend (`SendApplicationEmail` action + `ApplicationEmail` mailable + colunas `profiles.email_apply_*` + `jobs.contact_email` + `applications.sent_via_email_at`) ainda existe e o `CreateApplication` continua chamando `SendApplicationEmail` quando o perfil tem `email_apply_enabled=true`. Como o frontend não permite mais ligar essa flag (a `ProfileView` removeu a seção e o `ProfileSchema` Zod não tem os campos), na prática **nenhum email é enviado em produção**. Limpeza completa (drop do action + mailable + colunas + validações + chamada no `CreateApplication`) está pendente.

### Anexos

- Coleção MediaLibrary `'attachments'` em `Application`
- Validação: `max:5MB`, mimes `pdf|png|jpeg|webp|doc|docx`
- Storage: disk **`s3`** (MinIO em dev; R2 em prod), `visibility=private`
- `store` registra `ApplicationEvent` com `event_type='attachment_added'`

### Arquivamento

**Ortogonal à state machine** — `archived_at` é coluna nullable (timestamp). Não bloqueia nem dispara transição de status. Use case: candidaturas de teste que poluem o Kanban.

- `POST /archive` seta `archived_at = now()` e registra `ApplicationEvent` com `event_type='archived'` (idempotente — chamada com já-arquivada não duplica evento)
- `POST /unarchive` seta `archived_at = null` e registra `event_type='unarchived'`
- `GET /api/applications` por padrão **exclui** arquivadas (`whereNull('archived_at')`); com `?archived=1` retorna **só** arquivadas. Filtro `status` aplica em ambos os modos.

### Follow-up automático

`SendApplicationFollowUpsCommand` (scheduler **diário 09:00 UTC**):
- Para cada candidatura **não terminal** com `applied_at` há **>7 dias**:
  - **Pula** se houve `status_changed` ou `followup_sent` nos últimos 7 dias (anti spam)
  - Envia `ApplicationFollowUpNotification`
  - Registra `ApplicationEvent` com `event_type='followup_sent'`, `payload={ days_since_applied }`

## Frontend

**Arquivos:** `frontend/src/modules/applications/`

- **`ApplicationsKanbanView`** (`views/`) — Kanban com colunas por status. Drag-and-drop **HTML5 nativo, só desktop** (≥ lg = 1024px via `window.matchMedia('(min-width: 1024px)')`). Mobile: cards stack em coluna única (responsive `flex-col lg:flex-row`), drag desabilitado, troca de status só pela `ApplicationDetailView`. **Botão "Arquivadas" no header** (mesmo estilo do "Arquivar" no detail) alterna entre `?archived=0` e `?archived=1` — em modo arquivada o label muda para "Ativas". Em modo arquivada: drag-drop também desabilitado e botão "Nova candidatura" desabilitado. Query key inclui o modo: `['applications', 'active' | 'archived']`. `dragEnabled = isDesktop && !viewArchived` é a condição única — testada nos handlers e no atributo `:draggable`. Inclui:
  - **Modal "Nova candidatura"** — título, empresa, link da vaga, descrição da vaga, canal (select: LinkedIn/Indeed/Catho/Glassdoor/Gupy/InfoJobs/etc.), currículo enviado (select dos resumes do user). Envia `manualTitle`, `manualCompany`, `jobUrl`, `notes`, `source`, `resumeId`. Campo `jobUrl` aceita `null` ou URL válida (validação `url` no backend, max 500 chars).
  - **Modal "Configurar etapas"** — modal centralizado com etapas em row horizontal. Cada etapa mostra bolinha de cor (click abre `<input type="color">` nativo), nome (click para editar inline), setas esquerda/direita para reordenar, lixeira para excluir. Etapas `applied`, `accepted` e `rejected` são travadas (sem editar nome/cor/posição/excluir). Botão "Adicionar etapa" cria etapas customizadas (limite de 15). "Restaurar padrão" reseta tudo.
  - **Scroll fade** — `mask-image` CSS nas bordas do kanban quando há colunas fora da viewport.
  - **Altura da view fixada em desktop** (`lg:h-[calc(100vh-7rem)] lg:overflow-hidden`) — `7rem` = header (4rem) + paddings do AppLayout (3rem). Colunas ocupam altura cheia disponível e a área de cards usa `lg:overflow-y-auto` interno, então a página não cresce mais quando há muitos cards (scroll é dentro da coluna).
  - **Empty state por coluna** — quando `grouped[col.status].length === 0`, mostra ícone + texto `applications.kanban_no_apps_in_stage` (i18n nos 3 locales). Empty state tem `flex-1` e o cards-area é `flex flex-col`, então o placeholder estica vertical e centraliza no meio da coluna inteira (não fica só no topo).
- **`ApplicationDetailView`** (`views/`) — rota `/app/applications/:id`. Integra `useKanbanConfig` para usar labels, cores e visibilidade das etapas configuradas pelo user no Kanban. Layout com `border-l-4 border-brand-500` em todos os cards. **Botão Arquivar/Desarquivar** no header (lado direito do voltar) — vira "Desarquivar" se `archived_at != null`. Mostra badge "Arquivada" no header da candidatura quando arquivada. Inclui:
  - **Header** — título, empresa, data, badge de status com cor da etapa do Kanban + **progress stepper** horizontal mostrando as etapas visíveis do Kanban com dots coloridos
  - **Avançar status** — botões filtrados pelas etapas visíveis do Kanban, com dot de cor da etapa. Labels respeitam renomeações feitas no Kanban
  - **Descrição da vaga, link, salário & currículo** — seção unificada (título `applications.job_description`, antes era "Anotações"). Contém input "Título da vaga" (`manual_title`, max 255), input "Link da vaga" (`job_url`) com botão de abrir em nova aba quando preenchido, textarea da descrição (persistida em `notes`, max **5000 chars**), salário esperado e dropdown de currículo lado a lado (`sm:grid-cols-2`). **Um único botão Salvar** envia `notes`, `job_url`, `manual_title`, `expected_salary` e `resume_id` juntos via `updateNotes.mutateAsync()`. A coluna `notes` continua sendo o campo de descrição (não foi renomeada no DB — só o label da seção). **Display do título no header e nos cards do Kanban prioriza `manual_title` sobre `job.title`** (override do usuário vence — antes era o inverso) — isso permite editar o título mesmo em candidaturas vindas do feed
  - **Attachments** — upload/list/delete via `useAttachments`
  - **Timeline** — `application_events` ordenados desc, labels de status via `statusLabel()` (consistente com Kanban)
- **Composables:**
  - `useApplicationDetail` — query + `updateNotes({ notes?, expectedSalary?, resumeId?, jobUrl?, manualTitle? })` (otimista, envia ao endpoint só os campos definidos — `body.job_url` / `body.manual_title` em snake_case)
  - `useChangeApplicationStatus` — mutation otimista com rollback em 422. **Optimistic write em `['applications', 'active']`** (não em `['applications']` cru, porque a query do Kanban inclui modo no key)
  - `useApplyToJob` — POST `/api/applications`, classifica erro em `'duplicate' | 'validation' | 'unknown'`
  - `useArchiveApplication` — POST `/{id}/archive` ou `/{id}/unarchive` baseado em flag. Invalida `['applications']` (prefix match) + `['application', id]`
  - `useAttachments` — GET/POST (FormData) /DELETE
  - `useKanbanConfig` — configuração do quadro Kanban (colunas, cores hex, ordem, etapas customizadas). Armazena em `localStorage('kanban-config')`. Funções: `visibleColumns`, `styleFor` (gera estilos inline a partir de hex), `rename`, `setColor`, `moveColumn`, `addColumn` (max 15), `removeColumn`, `isLocked`, `isCustom`, `resetDefaults`. Cores são hex arbitrárias (color picker nativo). Etapas locked: `applied`, `accepted`, `rejected`.
- **`machines/applicationStatusMachine.ts`** (XState) — espelho client-side da state machine, usado para mostrar apenas próximas etapas válidas no UI antes do request

## Efeitos colaterais

- Escritas: `applications` (inclui `sent_via_email_at`, `archived_at`), `application_events`, `media`
- Migration: `2026_04_21_000300_add_sent_via_email_at_to_applications`, `2026_04_30_000100_add_archived_at_to_applications`, `2026_05_19_000000_add_job_url_to_applications` (varchar 500 nullable)
- Eventos: `ApplicationCreated`, `ApplicationStatusChanged`
- E-mails via Resend (queue `mail`) — follow-ups + candidatura por e-mail (`ApplicationEmail`)
- Objetos em S3 (anexos)

## Testes

- `backend/tests/Feature/Applications/` — `ListApplicationsTest`, `CreateApplicationTest` (com 409), `ShowApplicationTest`, `UpdateApplicationTest`, `ChangeStatusTest`, `DeleteApplicationTest`, `PolicyTest`, `AttachmentsTest`, `SendFollowUpsTest`, `ResumeLinkingTest`, `ArchiveApplicationTest`
- `frontend/tests/useApplyToJob.test.ts`
- `frontend/tests/useChangeApplicationStatus.test.ts`
- `frontend/tests/useArchiveApplication.test.ts`
- `frontend/tests/applicationsKanbanView.test.ts` — botão de filtro arquivadas (label dinâmico, query param, drag-drop desabilitado em arquivada, botão "Nova" desabilitado)
- `frontend/tests/useApplicationDetailResumeLink.test.ts` — 4 testes: só resumeId, clear resumeId, só notes, e envio unificado (notes + salary + resumeId + jobUrl)
- `frontend/tests/applicationStatusMachine.test.ts`
- `frontend/tests/useKanbanConfig.test.ts` — 25 testes cobrindo: defaults, isLocked/isCustom, rename, setColor, moveColumn (incluindo bloqueio por locked), addColumn (com limite 15), removeColumn (locked/default/custom), resetDefaults, styleFor (hex→rgba), singleton state

## Pontos de atenção

- **Sem state machine — qualquer transição é aceita.** O frontend mostra todas as colunas visíveis do Kanban como destino possível em `ApplicationDetailView.nextStatuses` (filtra a atual). XState e `ALLOWED_TRANSITIONS` foram removidos. Se voltar a precisar de gating, restaurar nas 3 fontes (enum PHP + frontend view + cliente).
- **Detail view depende de `useKanbanConfig`.** Labels, cores e visibilidade das etapas no detalhe da candidatura vêm do Kanban config do user (localStorage). Se o user nunca configurou, usa os defaults. Etapas ocultas no Kanban ficam ocultas também nos botões de avançar status.
- **Drag-drop otimista:** se a API retornar 422, o `onError` precisa restaurar o snapshot do cache. Confira que o `onMutate` salvou o snapshot antes de mutar.
- **MediaLibrary requer disk `s3` configurado.** Em dev sem MinIO subido, upload falha com erro confuso de stream. Verifique `docker compose ps` antes.
- **Throttle `uploads`** (20/min user) bate em casos de drag-drop múltiplo. Considere agrupar em multipart se virar problema.
- **Follow-up anti-spam:** o gate "7 dias desde último evento" considera `status_changed` E `followup_sent`. Se mudar a regra, cuide para não remover o gate de re-follow-up (caso contrário, manda e-mail diariamente).
- **Resume linking:** o Form Request usa `Rule::exists('resumes', 'id')->where('user_id', $userId)` — isso é o que impede um user vincular currículo de outro. Não remova o `where('user_id', ...)`.
- **Kanban config é client-side only.** Colunas, cores e ordem ficam em `localStorage('kanban-config')`. Etapas customizadas (prefixo `custom_`) são visuais — o backend não conhece esses status, então drag-drop para elas é bloqueado no frontend. Limite de 15 colunas.
- **Vinculação ↔ vaga:** quando uma vaga é desativada (`active=false`), candidaturas existentes mantêm `job_id`. UI deve renderizar com fallback para vaga removida.
- **`ApplicationCreated` listener** pode disparar lógica adicional (ex.: contadores). Se virar gargalo no fluxo de aplicar, mover para queue.
- **`SendApplicationEmail` é dead code do ponto de vista do usuário** — a UI de configuração foi removida, então `email_apply_enabled` nunca é true em perfis novos. A action ainda é injetada em `CreateApplication` mas o `if (!$profile->email_apply_enabled) return` curto-circuita. Limpar quando fizermos o drop das colunas.
- **`CreateApplication` injeta `SendApplicationEmail` via container.** O teste unitário usa `app(CreateApplication::class)` para resolver a dependência — nunca instanciar diretamente com `new`.
- **Arquivamento é ortogonal ao status.** `archived_at` não interfere na state machine — uma candidatura `accepted` ou `applied` pode ser arquivada do mesmo jeito. O follow-up automático **não** considera `archived_at` (ainda manda follow-up pra arquivada se ela bater os critérios). Se isso for indesejado, adicionar `whereNull('archived_at')` no `SendApplicationFollowUpsCommand`.
- **Query key do Kanban mudou para `['applications', 'active' | 'archived']`.** Quem fizer optimistic update em mutation deve usar a key específica (`['applications', 'active']`), senão `setQueryData` vira no-op silencioso. `useChangeApplicationStatus` já está ajustado.
