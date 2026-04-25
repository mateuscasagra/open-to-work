# Módulo Auth

**Propósito:** autenticação SPA via Sanctum (cookie de sessão HttpOnly) com fallback OAuth (Google, LinkedIn, GitHub).

## Endpoints

| Método | Rota | Handler | Throttle / Auth |
|---|---|---|---|
| `POST` | `/api/auth/register` | `AuthController@register` | `throttle:auth` |
| `POST` | `/api/auth/login` | `AuthController@login` | `throttle:auth` |
| `POST` | `/api/auth/verify-email` | `AuthController@verifyEmail` | `throttle:auth` |
| `POST` | `/api/auth/resend-code` | `AuthController@resendVerificationCode` | `throttle:auth` |
| `POST` | `/api/auth/logout` | `AuthController@logout` | `auth:sanctum` |
| `GET` | `/api/auth/{provider}/redirect` | `OauthController@redirect` | `throttle:auth` |
| `GET` | `/api/auth/{provider}/callback` | `OauthController@callback` | `throttle:auth` |
| `GET` | `/api/me` | `AuthController@me` | `auth:sanctum` |

`{provider}` é restrito a `google|linkedin|github` via `whereIn` em `routes/api.php`.

## Backend

**Controllers / Requests:**
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/OauthController.php`
- `app/Http/Requests/Auth/RegisterRequest.php` — `name`, `email` (unique), `password` (confirmed + min 8 + maiúscula + minúscula + número)
- `app/Http/Requests/Auth/LoginRequest.php` — `email`, `password`, `remember` (nullable boolean)
- `app/Http/Requests/Auth/VerifyEmailRequest.php` — `email`, `code` (digits:6)
- `app/Http/Requests/Auth/ResendVerificationCodeRequest.php` — `email`

**Models:** `app/Models/User.php`, `app/Models/OauthAccount.php`

**Enums:** `app/Enums/SupportedLocale.php` (`pt_BR | en | es`)

### Fluxo — Register
1. Validação via `RegisterRequest`.
2. `User::create([name, email, password=Hash::make(...)])` — `email_verified_at` permanece `null`.
3. `issueVerificationCode($user)`: gera 6 dígitos aleatórios, persiste hash + expiração de 15min em `email_verification_code`/`email_verification_code_expires_at`, zera `email_verification_attempts`, envia `VerifyEmailCode` mailable.
4. **Não loga o usuário.** Resposta `202 { status: 'verification_required', email }`.

### Fluxo — Verify Email
1. Validação via `VerifyEmailRequest` (`email`, `code` com `digits:6`).
2. Busca user por email. Se já verificado → `422 already_verified`.
3. Se code expirou ou não existe → `422 code_expired`.
4. Se `email_verification_attempts >= 5` → `422 too_many_attempts` (forçar resend).
5. `Hash::check(code, stored)` → falha incrementa attempts e retorna `422 invalid_code`.
6. Sucesso: zera campos de verificação, define `email_verified_at = now()`, `Auth::login($user)`, `session()->regenerate()`, retorna `200 { user }`.

### Fluxo — Resend Code
1. Validação via `ResendVerificationCodeRequest`.
2. Busca user. Se existe e `email_verified_at === null`, chama `issueVerificationCode($user)`.
3. **Sempre retorna 200** (não vaza enumeração de e-mails registrados).

### Fluxo — Login
1. Validação via `LoginRequest`.
2. **Importante:** monta `$credentials = ['email', 'password']` excluindo `remember`. Se `remember` entrar no array, o `EloquentUserProvider` interpreta como cláusula `WHERE remember = ?` (coluna inexistente → `SQLSTATE[42703]`).
3. `Auth::attempt($credentials, remember: $data['remember'] ?? false)`.
4. **Se autenticou mas `email_verified_at === null`:** desloga, regenera token, dispara novo código e retorna `403` com `email = __('auth.verify.must_verify')`. Front redireciona para `/verify-email`.
5. Sucesso e verificado → `session()->regenerate()` + `200 { user }`. Falha de credencial → `ValidationException` em `email` com `__('auth.failed')`.

### Fluxo — OAuth
1. `redirect`: `Socialite::driver($provider)->stateless()->redirect()`.
2. `callback`:
   - `Socialite::driver($provider)->stateless()->user()`
   - Lookup `OauthAccount` por `(provider, provider_id)`
   - Se não existir: `User::firstOrCreate(['email'])` (com `email_verified_at=now`) + cria `OauthAccount`
   - Persiste `access_token` + `refresh_token` (cast `encrypted`) + `expires_at`
   - `Auth::login($user)` → redirect para `${app.frontend_url}/auth/callback?success=1` (ou `?error=1`)

## Frontend

**Arquivos:** `frontend/src/modules/auth/`

- **Store** (`stores/auth.ts`) — Pinia: state `{ user, initialized, pendingVerificationEmail }`; actions `fetchMe`, `login`, `register`, `verifyEmail`, `resendVerificationCode`, `setPendingVerificationEmail`, `logout`; helper `oauthUrl(provider)`. `pendingVerificationEmail` persiste em `localStorage` (chave `auth.pending_verification_email`) para sobreviver a reload entre register e verify. Toda resposta de login/verify passa por `UserSchema.parse(data.user)`.
- **LoginView** — form e-mail + senha + checkbox **remember**. OAuth como `<a :href>` (full page navigation, obrigatório para redirect server-side). Sucesso → `router.push({ name: 'dashboard' })`. Em `403`, salva email pendente e redireciona para `verify-email`.
- **RegisterView** — form name + e-mail + senha + confirmação. Captura `422` em `fieldErrors`. Sucesso → `router.push({ name: 'verify-email', query: { email } })` (não há mais auto-login).
- **VerifyEmailView** — 6 inputs de 1 dígito com paste handler, autocomplete `one-time-code`, contador de reenvio (30s). Sucesso → `router.push({ name: 'profile' })`.
- **OAuthCallbackView** — lê `?success=1`, chama `auth.fetchMe()`, redireciona para dashboard ou login.

**Router guard** (`router/index.ts` → ver `shared-frontend.md`): `beforeEach` chama `fetchMe()` antes do primeiro render se `!initialized`.

## Efeitos colaterais

- Escritas: `users` (incluindo `email_verification_code`, `email_verification_code_expires_at`, `email_verification_attempts`, `email_verified_at`), `oauth_accounts`
- E-mails: `VerifyEmailCode` (sync) — view `resources/views/mail/verify-email.blade.php`
- Sessão Sanctum em Redis (cookies `XSRF-TOKEN` + sessão)

## Testes

- `backend/tests/Feature/Auth/RegisterTest.php`
- `backend/tests/Feature/Auth/LoginTest.php`
- `backend/tests/Feature/Auth/LogoutTest.php`
- `backend/tests/Feature/Auth/MeTest.php`
- `backend/tests/Feature/Auth/OAuthTest.php`
- `backend/tests/Feature/Auth/VerifyEmailTest.php`
- `frontend/tests/authStore.test.ts`

## Pontos de atenção

- **`remember` no array de credenciais → SQL error.** Mantenha o pattern `$credentials = ['email', 'password']` separado do flag `remember`.
- **`User::create()` não recarrega defaults do DB.** Sem `->refresh()`, a serialização perde campos como `locale` e o `UserSchema.parse` no front quebra (mostra "registerFailed" embora o status seja 201).
- **CSRF em SPA.** Toda mutação precisa do cookie `XSRF-TOKEN` — o `client.ts` faz isso via `ensureCsrf()` antes de POST/PUT/PATCH/DELETE. 419 ao logar geralmente é cookie ausente (ver `cross-cutting.md`).
- **Sanctum stateful.** Front deve estar listado em `SANCTUM_STATEFUL_DOMAINS` no `.env` do backend, senão login retorna 200 mas requests subsequentes voltam como guest.
- **OAuth precisa de `app.frontend_url`** definido. Se a env faltar, o redirect final quebra.
- **Throttle `auth`:** 10/min IP + 5/min e-mail. Em testes manuais repetitivos, dá `429` rápido.
- **Verificação de e-mail é toggle.** Controlado por `config('auth.email_verification_enabled')` (env `AUTH_EMAIL_VERIFICATION`, default `true`). Quando `false`: register auto-loga (carimba `email_verified_at=now()`) e login não bloqueia contas sem verificação — útil em dev ou antes do provedor de e-mail estar configurado em prod. Frontend store detecta `data.user` na resposta de `/register` e pula a tela de verify. A migração `2026_04_24_000200_add_email_verification_code_to_users_table` carimba `email_verified_at = now()` para usuários antigos com valor `null`, evitando lockout.
- **OAuth não exige verificação:** `OauthController` cria/recupera user com `email_verified_at = now()`. Provider já validou o e-mail.
- **Email síncrono:** `VerifyEmailCode` é enviado com `Mail::send()` (não `queue`) — em dev (MailHog em `http://localhost:8025`) abre instantâneo; em prod com Resend, latência da rota `register` aumenta ~200-500ms. Considerar mover para queue quando houver throughput real.
- **Provedor de e-mail em prod:** `resend/resend-laravel` — setar `MAIL_MAILER=resend` + `RESEND_KEY=<api_key>`. Domínio precisa estar verificado no painel da Resend (3 registros DNS: SPF, DKIM, DMARC). `MAIL_FROM_ADDRESS` deve usar o domínio verificado, senão Resend rejeita com 403. Em dev, `MAIL_MAILER=smtp` aponta pra MailHog (config no docker-compose).
- **Não loga em `verifyEmail`:** o controller intencionalmente não vaza se o e-mail está cadastrado (resposta 200 sempre). Mantenha esse contrato.
- **Para fluxo de senha esquecida** (não implementado no MVP), seguir o mesmo padrão de Form Request + Action.
