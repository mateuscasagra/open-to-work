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

- **`ApplicationsKanbanView`** (`views/`) — Kanban de 8 colunas (uma por status). Drag-and-drop **HTML5 nativo**.
- **`ApplicationDetailView`** (`views/`) — rota `/app/applications/:id`. Tabs:
  - **Timeline** — `application_events` ordenados desc
  - **Notes** — PUT em `/api/applications/:id` (`useApplicationDetail.updateNotes`)
  - **Attachments** — upload/list/delete via `useAttachments`
  - **Resume vinculado** — dropdown salva `resume_id`
- **Composables:**
  - `useApplicationDetail` — query + `updateNotes(notes, expectedSalary, resumeId)` (otimista)
  - `useChangeApplicationStatus` — mutation otimista com rollback em 422
  - `useApplyToJob` — POST `/api/applications`, classifica erro em `'duplicate' | 'validation' | 'unknown'`
  - `useAttachments` — GET/POST (FormData) /DELETE
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
- `frontend/tests/useApplicationDetailResumeLink.test.ts`
- `frontend/tests/applicationStatusMachine.test.ts`

## Pontos de atenção

- **State machine duplicada (back + front XState).** Se mudar transições no `ApplicationStatus` PHP, **obrigatoriamente** atualize `applicationStatusMachine.ts` e `ALLOWED_TRANSITIONS` no `ApplicationDetailView`. Existem 3 fontes da verdade — risco real de drift.
- **Drag-drop otimista:** se a API retornar 422, o `onError` precisa restaurar o snapshot do cache. Confira que o `onMutate` salvou o snapshot antes de mutar.
- **MediaLibrary requer disk `s3` configurado.** Em dev sem MinIO subido, upload falha com erro confuso de stream. Verifique `docker compose ps` antes.
- **Throttle `uploads`** (20/min user) bate em casos de drag-drop múltiplo. Considere agrupar em multipart se virar problema.
- **Follow-up anti-spam:** o gate "7 dias desde último evento" considera `status_changed` E `followup_sent`. Se mudar a regra, cuide para não remover o gate de re-follow-up (caso contrário, manda e-mail diariamente).
- **Resume linking:** o Form Request usa `Rule::exists('resumes', 'id')->where('user_id', $userId)` — isso é o que impede um user vincular currículo de outro. Não remova o `where('user_id', ...)`.
- **Vinculação ↔ vaga:** quando uma vaga é desativada (`active=false`), candidaturas existentes mantêm `job_id`. UI deve renderizar com fallback para vaga removida.
- **`ApplicationCreated` listener** pode disparar lógica adicional (ex.: contadores). Se virar gargalo no fluxo de aplicar, mover para queue.
- **`SendApplicationEmail` depende do perfil** (`email_apply_enabled`, modes, template, resume). Se o perfil estiver incompleto (ex.: `message_mode=fixed` sem template), a action não envia. Verificar configuração do perfil antes de debugar "e-mail não enviado".
- **`CreateApplication` injeta `SendApplicationEmail` via container.** O teste unitário usa `app(CreateApplication::class)` para resolver a dependência — nunca instanciar diretamente com `new`.
