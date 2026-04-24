# Módulo Applications

**Propósito:** ciclo completo de candidatura — registrar do feed, mover entre status (Kanban), anexar documentos, receber follow-up automático após 7 dias sem resposta.

## Endpoints

| Método | Rota | Handler | Throttle / Auth |
|---|---|---|---|
| `GET` | `/api/applications?status=<enum>` | `ApplicationController@index` | `auth:sanctum` |
| `POST` | `/api/applications` | `ApplicationController@store` | `auth:sanctum` |
| `GET` | `/api/applications/{id}` | `ApplicationController@show` | `auth:sanctum` |
| `PATCH` | `/api/applications/{id}` | `ApplicationController@update` | `auth:sanctum` |
| `DELETE` | `/api/applications/{id}` | `ApplicationController@destroy` | `auth:sanctum` |
| `PATCH` | `/api/applications/{id}/status` | `ApplicationController@changeStatus` | `auth:sanctum` |
| `GET` | `/api/applications/{id}/attachments` | `ApplicationAttachmentController@index` | `auth:sanctum` |
| `POST` | `/api/applications/{id}/attachments` | `ApplicationAttachmentController@store` | `throttle:uploads` |
| `DELETE` | `/api/applications/{id}/attachments/{media}` | `ApplicationAttachmentController@destroy` | `auth:sanctum` |
| CLI | `php artisan applications:send-followups` | scheduler 09:00 UTC |

## Backend

**Controllers:**
- `app/Http/Controllers/Api/ApplicationController.php`
- `app/Http/Controllers/Api/ApplicationAttachmentController.php`

**Domínio:** `app/Domain/Application/`
- **Actions:** `CreateApplication`, `ChangeApplicationStatus`, `SendApplicationEmail`
- **DTO:** `ApplicationData` (inclui `emailMessageOverride`, `emailResumeIdOverride` opcionais)
- **Enum:** `app/Enums/EmailApplyMode.php` — `Fixed | Variable` (backed string enum)
- **Mailable:** `app/Mail/ApplicationEmail.php` — mensagem com replyTo do user, anexa PDF do currículo via S3
- **Blade:** `resources/views/mail/application.blade.php`
- **Exception:** `DuplicateApplicationException` (controller mapeia para `409`)

**Enums:** `app/Enums/ApplicationStatus.php` — state machine via `canTransitionTo(ApplicationStatus $next): bool`

**Policy:** `app/Policies/ApplicationPolicy.php` — `view/update/delete` apenas se `user_id` bate

**Notification:** `app/Notifications/ApplicationFollowUpNotification.php` (canal `mail`)

**Command:** `app/Console/Commands/SendApplicationFollowUpsCommand.php`

**Models:**
- `app/Models/Application.php` — usa `HasMedia` + `InteractsWithMedia` (Spatie MediaLibrary)
- `app/Models/ApplicationEvent.php`

### State machine de status

```
Applied      → Screening | Assessment | Rejected | Withdrawn
Screening    → Assessment | InterviewHR | Rejected | Withdrawn
Assessment   → InterviewHR | InterviewTech | Rejected | Withdrawn
InterviewHR  → InterviewTech | Offer | Rejected | Withdrawn
InterviewTech→ Offer | Rejected | Withdrawn
Offer        → Accepted | Rejected | Withdrawn
Accepted | Rejected | Withdrawn  (terminais — sem transições)
```

`changeStatus` valida via `ApplicationStatus::canTransitionTo(...)`. Transição inválida → `422`. Sucesso → dispara `ApplicationStatusChanged`, registra `ApplicationEvent` com `event_type='status_changed'`.

### Duplicidade

`CreateApplication` checa `unique(user_id, job_id)`. Se existir, lança `DuplicateApplicationException` → controller responde `409`.

### Candidatura por e-mail

Quando o user aplica a uma vaga com `contact_email` e tem `email_apply_enabled=true` no perfil:

1. `CreateApplication` cria a candidatura normalmente
2. Injeta `SendApplicationEmail` e dispara o envio
3. **`SendApplicationEmail`** resolve mensagem e currículo:
   - `message_mode=fixed` → usa `profile.email_apply_message_template`
   - `message_mode=variable` → usa `emailMessageOverride` do DTO
   - `resume_mode=fixed` → usa `profile.email_apply_resume_id`
   - `resume_mode=variable` → usa `emailResumeIdOverride` do DTO
4. Envia `ApplicationEmail` Mailable via queue com `replyTo(user.email)`
5. Se currículo é PDF upload, anexa via S3 `temporaryUrl`
6. Seta `application.sent_via_email_at = now()`

**StoreApplicationRequest** valida os campos opcionais:
- `email_message_override: string|nullable|max:5000`
- `email_resume_id_override: integer|nullable|exists:resumes,id` (ownership check)

### Anexos

- Coleção MediaLibrary `'attachments'` em `Application`
- Validação: `max:5MB`, mimes `pdf|png|jpeg|webp|doc|docx`
- Storage: disk **`s3`** (MinIO em dev; R2 em prod), `visibility=private`
- `store` registra `ApplicationEvent` com `event_type='attachment_added'`

### Follow-up automático

`SendApplicationFollowUpsCommand` (scheduler **diário 09:00 UTC**):
- Para cada candidatura **não terminal** com `applied_at` há **>7 dias**:
  - **Pula** se houve `status_changed` ou `followup_sent` nos últimos 7 dias (anti spam)
  - Envia `ApplicationFollowUpNotification`
  - Registra `ApplicationEvent` com `event_type='followup_sent'`, `payload={ days_since_applied }`

## Frontend

**Arquivos:** `frontend/src/modules/applications/`

- **`ApplicationsKanbanView`** (`views/`) — Kanban com colunas por status. Drag-and-drop **HTML5 nativo**. Inclui:
  - **Modal "Nova candidatura"** — título, empresa, descrição da vaga, canal (select: LinkedIn/Indeed/Catho/Glassdoor/Gupy/InfoJobs/etc.), currículo enviado (select dos resumes do user). Envia `manualTitle`, `manualCompany`, `notes`, `source`, `resumeId`.
  - **Modal "Configurar etapas"** — modal centralizado com etapas em row horizontal. Cada etapa mostra bolinha de cor (click abre `<input type="color">` nativo), nome (click para editar inline), setas esquerda/direita para reordenar, lixeira para excluir. Etapas `applied`, `accepted` e `rejected` são travadas (sem editar nome/cor/posição/excluir). Botão "Adicionar etapa" cria etapas customizadas (limite de 15). "Restaurar padrão" reseta tudo.
  - **Scroll fade** — `mask-image` CSS nas bordas do kanban quando há colunas fora da viewport.
- **`ApplicationDetailView`** (`views/`) — rota `/app/applications/:id`. Integra `useKanbanConfig` para usar labels, cores e visibilidade das etapas configuradas pelo user no Kanban. Layout com `border-l-4 border-brand-500` em todos os cards. Inclui:
  - **Header** — título, empresa, data, badge de status com cor da etapa do Kanban + **progress stepper** horizontal mostrando as etapas visíveis do Kanban com dots coloridos
  - **Avançar status** — botões filtrados pelas etapas visíveis do Kanban, com dot de cor da etapa. Labels respeitam renomeações feitas no Kanban
  - **Notes, salário & currículo** — seção unificada com textarea, salário esperado e dropdown de currículo lado a lado (`sm:grid-cols-2`). **Um único botão Salvar** envia `notes`, `expected_salary` e `resume_id` juntos via `updateNotes.mutateAsync()`
  - **Attachments** — upload/list/delete via `useAttachments`
  - **Timeline** — `application_events` ordenados desc, labels de status via `statusLabel()` (consistente com Kanban)
- **Composables:**
  - `useApplicationDetail` — query + `updateNotes(notes, expectedSalary, resumeId)` (otimista)
  - `useChangeApplicationStatus` — mutation otimista com rollback em 422
  - `useApplyToJob` — POST `/api/applications`, classifica erro em `'duplicate' | 'validation' | 'unknown'`
  - `useAttachments` — GET/POST (FormData) /DELETE
  - `useKanbanConfig` — configuração do quadro Kanban (colunas, cores hex, ordem, etapas customizadas). Armazena em `localStorage('kanban-config')`. Funções: `visibleColumns`, `styleFor` (gera estilos inline a partir de hex), `rename`, `setColor`, `moveColumn`, `addColumn` (max 15), `removeColumn`, `isLocked`, `isCustom`, `resetDefaults`. Cores são hex arbitrárias (color picker nativo). Etapas locked: `applied`, `accepted`, `rejected`.
- **`machines/applicationStatusMachine.ts`** (XState) — espelho client-side da state machine, usado para mostrar apenas próximas etapas válidas no UI antes do request

## Efeitos colaterais

- Escritas: `applications` (inclui `sent_via_email_at`), `application_events`, `media`
- Migration: `2026_04_21_000300_add_sent_via_email_at_to_applications`
- Eventos: `ApplicationCreated`, `ApplicationStatusChanged`
- E-mails via Resend (queue `mail`) — follow-ups + candidatura por e-mail (`ApplicationEmail`)
- Objetos em S3 (anexos)

## Testes

- `backend/tests/Feature/Applications/` — `ListApplicationsTest`, `CreateApplicationTest` (com 409), `ShowApplicationTest`, `UpdateApplicationTest`, `ChangeStatusTest`, `DeleteApplicationTest`, `PolicyTest`, `AttachmentsTest`, `SendFollowUpsTest`, `ResumeLinkingTest`
- `frontend/tests/useApplyToJob.test.ts`
- `frontend/tests/useChangeApplicationStatus.test.ts`
- `frontend/tests/useApplicationDetailResumeLink.test.ts` — 4 testes: só resumeId, clear resumeId, só notes, e envio unificado (notes + salary + resumeId)
- `frontend/tests/applicationStatusMachine.test.ts`
- `frontend/tests/useKanbanConfig.test.ts` — 25 testes cobrindo: defaults, isLocked/isCustom, rename, setColor, moveColumn (incluindo bloqueio por locked), addColumn (com limite 15), removeColumn (locked/default/custom), resetDefaults, styleFor (hex→rgba), singleton state

## Pontos de atenção

- **State machine duplicada (back + front XState).** Se mudar transições no `ApplicationStatus` PHP, **obrigatoriamente** atualize `applicationStatusMachine.ts` e `ALLOWED_TRANSITIONS` no `ApplicationDetailView`. Existem 3 fontes da verdade — risco real de drift.
- **Detail view depende de `useKanbanConfig`.** Labels, cores e visibilidade das etapas no detalhe da candidatura vêm do Kanban config do user (localStorage). Se o user nunca configurou, usa os defaults. Etapas ocultas no Kanban ficam ocultas também nos botões de avançar status.
- **Drag-drop otimista:** se a API retornar 422, o `onError` precisa restaurar o snapshot do cache. Confira que o `onMutate` salvou o snapshot antes de mutar.
- **MediaLibrary requer disk `s3` configurado.** Em dev sem MinIO subido, upload falha com erro confuso de stream. Verifique `docker compose ps` antes.
- **Throttle `uploads`** (20/min user) bate em casos de drag-drop múltiplo. Considere agrupar em multipart se virar problema.
- **Follow-up anti-spam:** o gate "7 dias desde último evento" considera `status_changed` E `followup_sent`. Se mudar a regra, cuide para não remover o gate de re-follow-up (caso contrário, manda e-mail diariamente).
- **Resume linking:** o Form Request usa `Rule::exists('resumes', 'id')->where('user_id', $userId)` — isso é o que impede um user vincular currículo de outro. Não remova o `where('user_id', ...)`.
- **Kanban config é client-side only.** Colunas, cores e ordem ficam em `localStorage('kanban-config')`. Etapas customizadas (prefixo `custom_`) são visuais — o backend não conhece esses status, então drag-drop para elas é bloqueado no frontend. Limite de 15 colunas.
- **Vinculação ↔ vaga:** quando uma vaga é desativada (`active=false`), candidaturas existentes mantêm `job_id`. UI deve renderizar com fallback para vaga removida.
- **`ApplicationCreated` listener** pode disparar lógica adicional (ex.: contadores). Se virar gargalo no fluxo de aplicar, mover para queue.
- **`SendApplicationEmail` depende do perfil** (`email_apply_enabled`, modes, template, resume). Se o perfil estiver incompleto (ex.: `message_mode=fixed` sem template), a action não envia. Verificar configuração do perfil antes de debugar "e-mail não enviado".
- **`CreateApplication` injeta `SendApplicationEmail` via container.** O teste unitário usa `app(CreateApplication::class)` para resolver a dependência — nunca instanciar diretamente com `new`.
