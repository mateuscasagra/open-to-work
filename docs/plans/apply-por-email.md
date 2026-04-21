# Apply por e-mail

## Context

Hoje o botão "Apply" em `JobsListView.vue` só registra a candidatura local e redireciona pro ATS externo (WeWorkRemotely, RemoteOK, etc.). Vagas agregadas não têm API de submit, então não dá pra "aplicar de verdade" via plataforma — mas **quando o job traz um e-mail no description**, é possível enviar currículo + mensagem por email pelo próprio OpenToWork.

Decisões confirmadas:
- E-mail de contato vem de regex no `description_html` durante a normalização (não tem hoje; adicionar coluna + extração).
- Após envio do email, **ainda redireciona** pro `external_url` (email-apply é aditivo, não substitui).
- Se a vaga não tem email, fallback silencioso pro comportamento atual.

## Data model (migrations novas)

| Tabela | Coluna | Tipo |
|---|---|---|
| `profiles` | `email_apply_enabled` | `boolean default false` |
| `profiles` | `email_apply_message_mode` | `string(10) nullable` (`fixed`/`variable`) |
| `profiles` | `email_apply_message_template` | `text nullable` |
| `profiles` | `email_apply_resume_mode` | `string(10) nullable` |
| `profiles` | `email_apply_resume_id` | `foreignId nullable` → `resumes.id` on delete set null |
| `jobs` | `contact_email` | `string nullable` (indexed? não precisa) |
| `applications` | `sent_via_email_at` | `timestamp nullable` |

Enum `app/Enums/EmailApplyMode.php` com `Fixed`/`Variable` (casts do Laravel).

## Backend

### Profile
- `app/Models/Profile.php:19–42` — adicionar 5 fields em `$fillable`, cast `email_apply_enabled` → `bool`, `email_apply_message_mode`/`email_apply_resume_mode` → `EmailApplyMode::class`.
- `app/Http/Controllers/Api/ProfileController.php:24–49` — estender validação inline:
  - `email_apply_enabled: boolean`
  - `email_apply_message_mode: nullable|Rule::enum(EmailApplyMode::class)`
  - `email_apply_message_template: nullable|string|max:5000`
  - `email_apply_resume_mode: nullable|Rule::enum(EmailApplyMode::class)`
  - `email_apply_resume_id: nullable|integer|exists:resumes,id,user_id,{user.id}`
- Regra cross-field: se `email_apply_enabled=true` e `message_mode=fixed` → `template` obrigatório; se `resume_mode=fixed` → `resume_id` obrigatório. Validar com closure `after` no `validate()`.

### Job aggregation — extrair contact_email
- `app/Domain/Job/Aggregator/DTOs/JobDTO.php:17–36` — novo campo `public readonly ?string $contactEmail`.
- `app/Domain/Job/Aggregator/Pipeline/NormalizeJob.php` — após strip de HTML em `description`, rodar regex:
  - Preferir `mailto:([^"'\s>]+)` no HTML original (antes do strip).
  - Fallback: regex de e-mail genérico (`[\w.+-]+@[\w.-]+\.[a-z]{2,}`) no texto limpo.
  - Excluir domínios óbvios de ATS (`noreply@`, `@gupy.io`, `@greenhouse.io`, etc.) — allowlist via config `aggregator.contact_email_blocklist`.
  - Normalizar lowercase.
- `app/Domain/Job/Aggregator/Pipeline/PersistJob.php` — passar `contact_email` pro `Job::create/update`.
- `app/Models/Job.php:20–36` — adicionar `contact_email` em `$fillable`.
- **Sem alteração nos drivers** — extração centralizada no pipeline.

### Apply flow
- `app/Domain/Application/DTOs/ApplicationData.php:11–17` — novos campos opcionais: `emailMessageOverride: ?string`, `emailResumeIdOverride: ?int`.
- `app/Http/Requests/Application/StoreApplicationRequest.php:20–33` — aceitar `email_message_override: nullable|string|max:5000` e `email_resume_id_override: nullable|integer|exists:resumes,id,user_id,{user.id}`.
- `app/Domain/Application/Actions/CreateApplication.php:16–41` — após `Application::create`, chamar nova action `SendApplicationEmail::execute($application, $data)` dentro de `try/catch` (nunca falha o apply).

### Mailer (novo)
- `app/Domain/Application/Actions/SendApplicationEmail.php` — resolve template/resume (override > profile fixed), renderiza placeholders `{empresa}`/`{cargo}` com `str_replace`, valida precondições, dispara `Mail::to($job->contact_email)->replyTo($user->email)->queue(new ApplicationEmail(...))`, stampa `applications.sent_via_email_at=now()` on success. Loga falha via `Log::warning` (não throwa).
- `app/Mail/ApplicationEmail.php` (nova pasta `app/Mail/`) — Mailable com:
  - `envelope()` → subject `"Candidatura: {cargo} — {nome do candidato}"`, replyTo user.
  - `content()` → `Content(view: 'mail.application', with: ['body' => $renderedBody])`.
  - `attachments()` → `[Attachment::fromStorageDisk('s3', $resume->file_path)->as($resume->title.'.pdf')]`.
- `resources/views/mail/application.blade.php` — layout simples: `{!! nl2br(e($body)) !!}` dentro de `<x-mail::message>` do Laravel.
- Queue via `database` (default já configurado em `config/queue.php`).

### Rate limiter
- `bootstrap/app.php` ou `RouteServiceProvider` — novo limiter `email-apply: 20/day` por user. Aplicado dentro de `SendApplicationEmail` (não na request, porque a request ainda cria Application mesmo sem email).

## Frontend

### Schemas — `frontend/src/shared/api/schemas.ts:40–54`
Adicionar em `ProfileSchema`:
```ts
email_apply_enabled: z.boolean().default(false),
email_apply_message_mode: z.enum(['fixed', 'variable']).nullable(),
email_apply_message_template: z.string().nullable(),
email_apply_resume_mode: z.enum(['fixed', 'variable']).nullable(),
email_apply_resume_id: z.number().nullable(),
```
E em `JobSchema` (onde estiver): `contact_email: z.string().nullable()`.

### Profile view — `frontend/src/modules/profile/views/ProfileView.vue`
- Nova seção ao fim do form "Candidatura por email":
  - Toggle master `email_apply_enabled`.
  - `v-if="email_apply_enabled"`:
    - Radio `email_apply_message_mode` (`fixed`/`variable`).
    - Se `fixed`: `<textarea v-model="email_apply_message_template">` + helper inline mostrando `{empresa}` / `{cargo}` clicáveis (inserem no cursor).
    - Radio `email_apply_resume_mode` (`fixed`/`variable`).
    - Se `fixed`: `<select>` populado por `useResumes()` — já existe em `jobs/views/JobsListView.vue:34`; **extrair pra composable compartilhado** `frontend/src/modules/resumes/composables/useResumes.ts` se ainda não estiver.
- Ajustar `onSubmit` (linhas ~87–98) pra mandar os 5 campos.

### Apply flow — `frontend/src/modules/jobs/views/JobsListView.vue`
- Adicionar branch no `onApply()` (linha ~37):
  ```
  const profile = useProfile().profile.value;
  const hasEmail = !!job.contact_email;
  const emailApply = profile?.email_apply_enabled && hasEmail;
  const needsModal = emailApply && (profile.email_apply_message_mode === 'variable' || profile.email_apply_resume_mode === 'variable');

  if (needsModal) openModal(job);
  else submitApply(job); // sem overrides
  ```
- Após mutation success, **sempre** abrir `external_url` (como hoje).

### Novo componente — `frontend/src/modules/jobs/components/ApplyByEmailModal.vue`
- Props: `job`, `profile`.
- Se `message_mode=variable`: textarea com placeholders `{empresa}`/`{cargo}`.
- Se `resume_mode=variable`: select de currículos.
- Botão "Enviar candidatura" → chama `useApplyToJob().apply({ jobId, emailMessageOverride, emailResumeIdOverride })`.
- Após sucesso: fecha modal + abre `external_url`.

## Tests

### Backend (`backend/tests/Feature/`)
- `Profile/UpdateProfileTest.php` — 3 casos novos: enable, validação cross-field (fixed exige template/resume), FK scoping do `email_apply_resume_id`.
- `Application/CreateApplicationTest.php` (ou `SendEmailApplicationTest.php` novo) — 5 casos:
  - email-apply desligado → `Mail::assertNothingQueued()`.
  - email-apply on + job com email + fixed → `Mail::assertQueued(ApplicationEmail)` + attachment + `sent_via_email_at` setado.
  - email-apply on + job SEM email → sem mail, Application criada normalmente.
  - variable mode + override no request → payload do Mailable usa override.
  - template com `{empresa}`/`{cargo}` → placeholders substituídos.
- `Aggregator/NormalizeJobTest.php` (ou `ExtractContactEmailTest.php`) — 3 casos: `mailto:`, email em texto, blocklist (`noreply@`, `@gupy.io`).

### Frontend (`frontend/tests/`)
- `useApplyToJob.test.ts` — extender com override args.
- `ApplyByEmailModal.test.ts` novo — cobrir variantes (só texto variável, só resume variável, ambos).

## Verification

1. `docker compose -f infra/docker-compose.yml exec backend php artisan migrate`
2. `docker compose -f infra/docker-compose.yml exec backend php artisan jobs:aggregate` — confirmar que alguma vaga pega `contact_email` via `Schema::...; Job::whereNotNull('contact_email')->count()`.
3. No front: habilitar email-apply no perfil, modo fixo, template `Olá {empresa}, candidato-me a {cargo}...`, selecionar currículo.
4. Apply numa vaga com contact_email.
5. Abrir MailHog em `http://localhost:8025` → verificar email recebido, placeholders renderizados, PDF anexado.
6. Verificar no DB: `applications.sent_via_email_at IS NOT NULL` pra essa candidatura.
7. Aplicar numa vaga sem `contact_email` → sem email enviado, Application criada, redirect acontece.
8. Trocar pra modo variable → confirmar que o modal abre antes do redirect.
9. `docker compose -f infra/docker-compose.yml exec backend php artisan test --filter="Profile|Application|Aggregator"`.
10. `cd frontend && npm test`.

## Arquivos críticos (resumo)

**Migrations novas:** 3 arquivos em `backend/database/migrations/`.
**Backend alterados:** `Profile.php`, `ProfileController.php`, `JobDTO.php`, `NormalizeJob.php`, `PersistJob.php`, `Job.php`, `ApplicationData.php`, `StoreApplicationRequest.php`, `CreateApplication.php`.
**Backend novos:** `EmailApplyMode.php` (enum), `SendApplicationEmail.php` (action), `ApplicationEmail.php` (Mailable), `mail/application.blade.php` (view).
**Frontend alterados:** `schemas.ts`, `ProfileView.vue`, `JobsListView.vue`, `useApplyToJob.ts`.
**Frontend novos:** `ApplyByEmailModal.vue`, (possivelmente) `useResumes.ts` extraído.
