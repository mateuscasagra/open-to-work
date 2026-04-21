# Módulo Auth

**Propósito:** autenticação SPA via Sanctum (cookie de sessão HttpOnly) com fallback OAuth (Google, LinkedIn, GitHub).

## Endpoints

| Método | Rota | Handler | Throttle / Auth |
|---|---|---|---|
| `POST` | `/api/auth/register` | `AuthController@register` | `throttle:auth` |
| `POST` | `/api/auth/login` | `AuthController@login` | `throttle:auth` |
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

**Models:** `app/Models/User.php`, `app/Models/OauthAccount.php`

**Enums:** `app/Enums/SupportedLocale.php` (`pt_BR | en | es`)

### Fluxo — Register
1. Validação via `RegisterRequest`.
2. `User::create([name, email, password=Hash::make(...)])`.
3. `Auth::login($user)` + `session()->regenerate()`.
4. `$user->refresh()` antes de serializar — necessário para incluir defaults do DB (ex.: `locale='pt_BR'`).
5. Resposta `201 { user }`.

### Fluxo — Login
1. Validação via `LoginRequest`.
2. **Importante:** monta `$credentials = ['email', 'password']` excluindo `remember`. Se `remember` entrar no array, o `EloquentUserProvider` interpreta como cláusula `WHERE remember = ?` (coluna inexistente → `SQLSTATE[42703]`).
3. `Auth::attempt($credentials, remember: $data['remember'] ?? false)`.
4. Sucesso → `session()->regenerate()` + `200 { user }`. Falha → `ValidationException` em `email` com `__('auth.failed')`.

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

- **Store** (`stores/auth.ts`) — Pinia: state `{ user, initialized }`; actions `fetchMe`, `login`, `register`, `logout`; helper `oauthUrl(provider)`. Toda resposta passa por `UserSchema.parse(data.user)`.
- **LoginView** — form e-mail + senha + checkbox **remember**. OAuth como `<a :href>` (full page navigation, obrigatório para redirect server-side). Sucesso → `router.push({ name: 'dashboard' })`.
- **RegisterView** — form name + e-mail + senha + confirmação. Captura `422` em `fieldErrors`. Sucesso → `router.push({ name: 'profile' })`.
- **OAuthCallbackView** — lê `?success=1`, chama `auth.fetchMe()`, redireciona para dashboard ou login.

**Router guard** (`router/index.ts` → ver `shared-frontend.md`): `beforeEach` chama `fetchMe()` antes do primeiro render se `!initialized`.

## Efeitos colaterais

- Escritas: `users`, `oauth_accounts`
- Sessão Sanctum em Redis (cookies `XSRF-TOKEN` + sessão)

## Testes

- `backend/tests/Feature/Auth/RegisterTest.php`
- `backend/tests/Feature/Auth/LoginTest.php`
- `backend/tests/Feature/Auth/LogoutTest.php`
- `backend/tests/Feature/Auth/MeTest.php`
- `backend/tests/Feature/Auth/OAuthTest.php`
- `frontend/tests/authStore.test.ts`

## Pontos de atenção

- **`remember` no array de credenciais → SQL error.** Mantenha o pattern `$credentials = ['email', 'password']` separado do flag `remember`.
- **`User::create()` não recarrega defaults do DB.** Sem `->refresh()`, a serialização perde campos como `locale` e o `UserSchema.parse` no front quebra (mostra "registerFailed" embora o status seja 201).
- **CSRF em SPA.** Toda mutação precisa do cookie `XSRF-TOKEN` — o `client.ts` faz isso via `ensureCsrf()` antes de POST/PUT/PATCH/DELETE. 419 ao logar geralmente é cookie ausente (ver `cross-cutting.md`).
- **Sanctum stateful.** Front deve estar listado em `SANCTUM_STATEFUL_DOMAINS` no `.env` do backend, senão login retorna 200 mas requests subsequentes voltam como guest.
- **OAuth precisa de `app.frontend_url`** definido. Se a env faltar, o redirect final quebra.
- **Throttle `auth`:** 10/min IP + 5/min e-mail. Em testes manuais repetitivos, dá `429` rápido.
- **Para fluxo de senha esquecida** (não implementado no MVP), seguir o mesmo padrão de Form Request + Action.
