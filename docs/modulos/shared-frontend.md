# Frontend Compartilhado

**Propósito:** infra do front que atravessa todos os módulos — Axios client, Zod schemas, layout autenticado, router com guard, i18n, e a landing pública.

## API Client (`src/shared/api/client.ts`)

```ts
const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';
export const api = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});
```

**CSRF lazy init:**
```ts
let csrfInitialized = false;
export async function ensureCsrf() {
  if (csrfInitialized) return;
  await api.get('/sanctum/csrf-cookie');
  csrfInitialized = true;
}
api.interceptors.request.use(async (config) => {
  if (['POST','PUT','PATCH','DELETE'].includes(config.method?.toUpperCase() ?? 'GET')) {
    await ensureCsrf();
  }
  return config;
});
```

- **`X-Requested-With: XMLHttpRequest`** — sinaliza ao Laravel para retornar JSON em validation errors (caso contrário pode retornar HTML).
- `withXSRFToken: true` — Axios lê o cookie `XSRF-TOKEN` e devolve no header `X-XSRF-TOKEN`.
- Reset do `csrfInitialized` em logout não é necessário (cookie continua válido até a sessão expirar).

## Schemas Zod (`src/shared/api/schemas.ts`)

Validadores tipados usados em **todas** as respostas:

| Schema | Usado em |
|---|---|
| `UserSchema`, `LocaleEnum` | auth, profile, account |
| `SeniorityEnum`, `ModalityEnum` | profile, jobs |
| `SkillSchema`, `ProfileSchema` | profile |
| `JobSchema` (inclui `description_html`, `contact_email`), `JobSourceSchema`, `JobsPage` | jobs |
| `ApplicationStatusSchema`, `ApplicationSchema`, `ApplicationEventSchema`, `AttachmentSchema` | applications |
| `ResumeSchema`, `ResumeSectionSchema`, `ResumesPageSchema` | resumes |
| `MetricsSummarySchema` | metrics |

**Importante:** todo composable que recebe dados da API **deve** passar por `Schema.parse(...)`. Se a API mudar formato sem o schema acompanhar, o erro estoura cedo (e em local óbvio) em vez de só corromper o estado.

### Exemplo de bug clássico

`UserSchema.locale: z.enum([...]).nullable()` (obrigatório, mas pode ser null). Se o backend retornar User sem o campo `locale` (ex.: `User::create(...)` sem `->refresh()`), o `parse` lança e o componente mostra mensagem de erro genérica embora o status HTTP seja 2xx. Sempre que ver "sucesso mas o front mostra erro", suspeitar de schema/parse.

## Layout (`src/shared/layouts/AppLayout.vue`)

Layout autenticado: top navbar + user dropdown + logout. 5 itens na navegação principal: `dashboard`, `jobs`, `applications`, `resumes`, `suggestions` (+ `admin` quando `auth.user.is_admin`). **`profile` não fica no nav principal** — é acessado pelo **dropdown do user** (entre o e-mail e o "Sair") e na seção do user no menu mobile. Usa `exact-active-class` (não `active-class`) para highlight — garante que apenas a rota exata é destacada (corrige bug onde Dashboard ficava destacado em todas as páginas `/app/*`). Slot `<router-view />`. Responsivo (mobile com hamburger menu).

### Gating de localização

Quando `auth.locationComplete === false`, os itens do nav principal (desktop **e** mobile) viram `<button>` desabilitado em vez de `<RouterLink>`:
- `cursor-not-allowed`, `opacity-70`, ícone de cadeado dourado ao lado do label
- `title=t('nav.locked_tooltip')` para tooltip nativo
- Click chama `goToProfile()` que faz `router.push({ name: 'profile' })`

No menu mobile aparece também um banner amarelo no topo da lista repetindo o mesmo aviso (tooltip nativo não funciona bem em mobile).

O dropdown do user (desktop) e a seção do user (mobile) **continuam acessíveis** mesmo com gating ligado — o user precisa conseguir abrir o profile e fazer logout.

## Router (`src/router/index.ts`)

**Estrutura:**
- Rotas públicas: `landing`, `login`, `register`, `verify-email`, `forgot-password`, `reset-password`, `auth.callback`
- Rotas protegidas sob `/app` (layout `AppLayout`): `dashboard`, `jobs`, `applications`, `applications/:id`, `resumes`, `resumes/new`, `resumes/:id/edit`, `resumes/:id/export`, `profile`, `suggestions`, `admin` (com `meta.adminOnly`), `account` (account existe como rota mas não aparece na nav)

**`beforeEach` guard:**
```ts
router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.initialized) await auth.fetchMe();

  if (to.meta.guestOnly && auth.user) {
    return auth.locationComplete
      ? { name: 'dashboard' }
      : { name: 'profile' };
  }

  if (!to.meta.public && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  if (auth.user && !auth.locationComplete && !to.meta.public && to.name !== 'profile') {
    return { name: 'profile' };
  }

  if (to.meta.adminOnly && !auth.user?.is_admin) {
    return { name: 'dashboard' };
  }

  return true;
});
```

- **`auth.initialized`** garante que `fetchMe()` roda **uma vez** antes do primeiro render — evita flash de "deslogado" para users autenticados. O próprio `fetchMe()` já chama `refreshLocationStatus()` (ver `auth.md`), então `locationComplete` está populado quando o guard roda.
- **`meta.guestOnly: true`** redireciona usuários autenticados (login, register, callback) para `dashboard` se completos, `profile` se incompletos.
- **`meta.public: true`** permite acesso sem auth (landing, login, register, callback). Rotas sem `meta.public` exigem auth.
- **Gating de localização**: usuário autenticado com `locationComplete: false` é redirecionado para `profile` em qualquer rota não-pública não-`profile`. Sai do gating quando preenche `country_code`, `state_name` e `city` no profile e o save dispara `auth.refreshLocationStatus()`.
- **`meta.adminOnly`** roda **depois** do gating de localização — admin que não preencheu localização ainda fica preso em profile.

**`scrollBehavior`:** restaura saved → âncora suave em hash → topo.

## i18n (`src/locales/`)

**Detecção de locale:**
- `navigator.language` lido no boot
- `pt-*` → `pt-BR`, `es-*` → `es`, default `en`
- Fallback: `en`

**Arquivos:** `pt-BR.json`, `en.json`, `es.json` — imports estáticos. Se passar a 4+ locales, vale lazy-load por rota.

**Modo:** Composition API (`legacy: false`).

**Backend tem traduções próprias** em `lang/{pt_BR,en,es}/auth.php` (etc.) — usadas em mensagens de validação e notificações via middleware `SetLocale`.

## Bootstrap (`src/main.ts`)

Plugins inicializados em ordem:
1. **Pinia** (state)
2. **Vue Router**
3. **Vue I18n**
4. **Vue Query** (TanStack)

Mount em `#app`.

## Landing (`src/modules/landing/views/LandingView.vue`)

Página estática pública. Hero + 6 features + 3 passos + CTA + footer. Lê `auth.user` para mostrar **"Open app"** ou **"Sign up"** no header.

## Pontos de atenção

- **Mudou um schema Zod sem mudar o backend (ou vice-versa) → parse falha em produção.** Sempre rodar testes de schema (`tests/*Schemas.test.ts`) E testes de feature do backend juntos.
- **`ensureCsrf()` cacheia em variável de módulo.** Em testes que mockam o axios, talvez precise resetar — exportar uma função `_resetCsrf()` para testes (não existe ainda).
- **Router guard chama `fetchMe` no primeiro acesso.** Se `/api/me` retornar erro de rede, o guard ainda assim seta `initialized=true` (catch). Resultado: user vai para login. Em conexão instável, considerar retry.
- **Gating de localização preso mesmo após preencher** — significa que `auth.refreshLocationStatus()` não rodou após o save. Conferir se `ProfileView.onSubmit` chama o método (via `useAuthStore().refreshLocationStatus()`). Sem isso, o state do Pinia continua com `locationComplete: false` até refresh manual da página.
- **Adicionar uma rota nova abaixo de `/app`** ela já é coberta pelo gating de localização automaticamente. Se for uma rota que **deve** ser acessível mesmo sem localização (ex.: tela de "complete seu perfil" alternativa), use `meta.public: true` ou ajuste o guard.
- **`meta.public` ausente = rota protegida.** Se esquecer de marcar uma rota nova como pública, ela exige auth. Cuidado em rotas tipo `/reset-password`.
- **Composition API mode no i18n** — `useI18n()` retorna `{ t, locale, ... }`. Não usar `$t` em template (modo legacy).
- **`VITE_API_URL`** precisa estar setada no build de prod, senão o front bate em `localhost:8000`. Conferir `.env.production` antes de buildar.
- **Cookies entre subdomínios:** se front (`app.opentowork.app`) e API (`api.opentowork.app`) estiverem em subdomínios diferentes, `SESSION_DOMAIN=.opentowork.app` no backend.
- **Vue Query cache** persiste entre rotas. Após logout, **invalidar tudo** (`queryClient.clear()`) para evitar vazar dados do user anterior — confirmar que está implementado no logout do auth store.
