# Módulo Account (LGPD)

**Propósito:** cumprir LGPD/GDPR com **export** completo dos dados do usuário e **delete** com cascade real (incluindo blobs em S3).

## Endpoints

Ambos protegidos por `throttle:account-sensitive` (5/min user, 2/min IP).

| Método | Rota | Handler |
|---|---|---|
| `GET` | `/api/account/export` | `AccountController@export` |
| `DELETE` | `/api/account` | `AccountController@destroy` |

## Backend

**Controller:** `app/Http/Controllers/Api/AccountController.php`

**Domínio:** `app/Domain/Account/Actions/`
- `ExportUserData`
- `DeleteAccount`

### Export

`ExportUserData` monta um bundle JSON:
- `user` — `id`, `name`, `email`, `locale`
- `profile` — com skills + proficiency
- `resumes` — com sections; **sem** binário de PDF (apenas `file_path` quando upload)
- `applications` — com events + resumo da vaga (`title`, `company.name`, `external_url`)
- `oauth_accounts` — `provider`, `provider_id`; **tokens omitidos** (sensíveis)

Resposta: streamed download, filename `opentowork-export-{userId}-{timestamp}.json`.

### Delete

`DeleteAccount` em transação:
1. Remove blobs S3 dos currículos (`Resume::where('is_pdf_upload', true)->each(fn ($r) => Storage::disk('s3')->delete($r->file_path))`)
2. Limpa coleções MediaLibrary de candidaturas (`Application::all()->each->clearMediaCollection('attachments')`)
3. Apaga `personal_access_tokens` se a tabela existir (Sanctum tokens)
4. **Logout** (`Auth::logout` + `session()->invalidate()`) — **necessário antes** do `$user->delete()`, caso contrário `cycleRememberToken` durante o request reinsere o usuário no DB
5. `$user->delete()` — cascateia FKs para `applications`, `profile`, `resumes`, `oauth_accounts`

## Frontend

**Arquivos:** `frontend/src/modules/account/`

- **`useAccount`** (`composables/useAccount.ts`):
  - `exportData()` → `GET /api/account/export` como **blob**, cria `<a download>` programático, clica, revoga URL
  - `deleteAccount()` → `DELETE /api/account`
  - State: `exporting, deleting, error`
- **`AccountView`** (`views/`):
  - Seção **Exportar dados** — descrição + botão
  - Seção **Excluir conta** — caixa de aviso + input que exige digitar exatamente **`EXCLUIR`**. Botão habilita só com match exato. Em sucesso: limpa `auth.user`, redireciona para landing.

## Efeitos colaterais

- Apaga linhas em todas as tabelas vinculadas (cascade FK)
- Apaga objetos em S3/MinIO
- Invalida sessão Sanctum (logout server-side)
- Front limpa Pinia + localStorage relevantes

## Testes

- `backend/tests/Feature/Account/ExportUserDataTest.php`
- `backend/tests/Feature/Account/DeleteAccountTest.php`
- `frontend/tests/useAccount.test.ts`

## Pontos de atenção

- **Ordem do logout no delete é crítica.** `Auth::logout()` antes de `$user->delete()`. Se inverter, o middleware `AuthenticateSession` chama `cycleRememberToken` no user em memória **depois** do delete, o que reinsere a row (já vimos esse bug — não regredir).
- **Cascade de FKs depende das migrations terem `onDelete('cascade')`.** Se uma tabela nova for adicionada sem cascade, ela vira lixo após delete. Sempre adicionar cascade ou cleanup manual no `DeleteAccount`.
- **Blobs em S3 não cascateiam.** Toda nova feature que grava em disk `s3` vinculada ao user precisa de cleanup explícito em `DeleteAccount`. Se esquecer, viola LGPD.
- **Export omite tokens OAuth** — manter assim. Tokens são credenciais ativas, exportar é falha de segurança.
- **Streamed response:** se o user tiver muitos dados, evite carregar tudo em memória. O `ExportUserData` deveria streamar (chunked); se virar problema de memória, refatorar com `LazyCollection`.
- **Throttle restrito** (`account-sensitive`: 5/min) — testes manuais repetitivos batem rápido.
- **Sem confirmação por e-mail.** Delete é imediato — só confirma com o "EXCLUIR" no front. Se virar requisito legal, adicionar token de confirmação por e-mail antes de executar.
- **Sem soft delete.** `$user->delete()` é hard. Se quiser período de "recuperação" (ex.: 30 dias), trocar para soft delete + job de purge.
