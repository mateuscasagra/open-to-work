# Módulo Profile

**Propósito:** dados do candidato usados pelo matching e pelo currículo (cargo desejado, seniority, modalidade, faixa salarial, localização estruturada, idiomas, stack).

## Endpoints

| Método | Rota | Handler | Throttle |
|---|---|---|---|
| `GET` | `/api/profile` | `ProfileController@show` | `auth:sanctum` |
| `PUT` | `/api/profile` | `ProfileController@update` | `auth:sanctum` |
| `GET` | `/api/skills?q=<query>` | `SkillController@index` | `throttle:search` |

`/api/profile` é `Route::singleton('profile', ...)` — o ID implícito é `auth()->user()->id`.

## Backend

**Controllers:**
- `app/Http/Controllers/Api/ProfileController.php`
- `app/Http/Controllers/Api/SkillController.php`

**Models:**
- `app/Models/Profile.php` — `belongsTo` em `User`, `belongsToMany` em `Skill` via pivot `profile_skills` com `proficiency`
- `app/Models/Skill.php` — `name`, `category`, `aliases[]`

**Seeder:** `database/seeders/SkillSeeder.php` — ~404 tecnologias em categorias (Languages, Frontend, Backend, CSS/UI, Mobile, Databases, DevOps, Cloud, Messaging, Testing, Tooling, AI/ML, CMS, APIs, Security, etc.). Usa `upsert` em lotes de 50 para evitar erro de cardinalidade do PostgreSQL.

**Form Requests:** `app/Http/Requests/Profile/UpdateProfileRequest.php` — valida:
- `desired_role: string|nullable`
- `seniority`/`modality` via `Rule::enum`
- `salary_min/max: integer|nullable`
- `country_code: required|string|Rule::enum(SupportedCountry)` — ISO-2 do enum `App\Domain\Location\Support\SupportedCountry`
- `postal_code: nullable|string|max:20` — required quando o país tem `supports_lookup=true` (BR, US, ES)
- `state_code: nullable|string|max:10`
- `state_name: required|string|max:120`
- `city: required|string|max:120`
- `languages: array of SupportedLocale`
- `bio: string|max:2000`
- `skills: array of integer (skill IDs)`

> **Nota:** as regras `email_apply_*` ainda existem no `UpdateProfileRequest` mas o frontend **não envia mais esses campos** (a UI de configuração de candidatura por e-mail foi removida). Limpeza completa (drop das regras + colunas) está pendente.

### Fluxo
- **show**: `Profile::firstOrCreate(['user_id' => $user->id])` — garante perfil em branco para o front renderizar formulário sem 404.
- **update**: `UpdateProfileRequest` valida → `fill($validated)->save()` → `skills()->sync($payload['skills'])`. Os 5 campos geográficos são persistidos no mesmo `update`.
- **search**: `WHERE LOWER(name) LIKE LOWER(?)`, limite 50.

### Localização estruturada

A tabela `profiles` guarda localização em 5 colunas (substituiu o campo de texto livre `location`):

| Campo | Tipo | Propósito |
|---|---|---|
| `country_code` | `char(2)` | ISO 3166-1 alpha-2 do país (BR, US, ES, AR, …) |
| `postal_code` | `string(20)` | CEP/ZIP normalizado (sem hífen/espaço) |
| `state_code` | `string(10)` | Sigla do estado/província (UF para BR, state abbreviation pra US/ES/CA) |
| `state_name` | `string(120)` | Nome humano do estado |
| `city` | `string(120)` | Nome da cidade |

Indexes: `country_code`, `(country_code, state_code)`, `(country_code, city)` — usados pelo agregado de admin.

O preenchimento usa o módulo **[Location](./location.md)** (lookup automático via ViaCEP/zippopotam.us). Países sem suporte de lookup (todos exceto BR/US/ES) caem em fallback manual.

### Email-apply settings (legado)

As 5 colunas `email_apply_*` ainda existem no banco e na model `Profile`, mas o **frontend não as usa mais** (`ProfileSchema` Zod não declara mais esses campos). A `<ProfileView>` removeu a seção "Candidatura por e-mail" e o save não envia mais os campos. Drop das colunas (`profiles.email_apply_*` + `applications.sent_via_email_at` + `jobs.contact_email`) e do código backend (`SendApplicationEmail`, `EmailApplyMode` enum, `ApplicationEmail` mailable, validações no `UpdateProfileRequest` e `StoreApplicationRequest`) está pendente.

## Frontend

**Arquivos:** `frontend/src/modules/profile/`

- **`useProfile`** (`composables/useProfile.ts`):
  - `fetch()` → `GET /api/profile`, parse `ProfileSchema`
  - `save(payload)` → `PUT /api/profile`
  - Export `searchSkills(q)` para autocomplete
- **`useLocationLookup`** (`composables/useLocationLookup.ts`):
  - `useSupportedCountries()` — TanStack Query (`['location', 'countries']`, `staleTime: Infinity`) → `GET /api/location/countries`
  - `useLocationLookup()` — `lookup(country_code, postal_code)` chama `POST /api/location/lookup`; expõe `loading`, `errorKey` (chave i18n) e `clearError()`
- **`ProfileView`** (`views/ProfileView.vue`):
  - Form com todos os campos do perfil; usa um `computed` `locationModel` (get/set) para passar os 5 campos geográficos ao `LocationFields.vue`
  - **Banner de gating**: quando `auth.locationComplete === false`, renderiza no topo um aviso amarelo (`profile.locked_banner_title` / `_body`) explicando que as outras seções estão bloqueadas até preencher país/estado/cidade.
  - **Languages**: chips toggle
  - **Skills**: autocomplete com busca debounced (200ms) na API, dropdown posicionado com `z-50`, navegação por teclado (ArrowUp/Down/Enter/Escape), máximo 8 skills, tags com botão `×` para remover. Filtra sugestões já selecionadas.
  - Submit captura 422 em `fieldErrors`, **chama `auth.refreshLocationStatus()` após `save()` bem-sucedido** (destrava o gating imediatamente), flash de sucesso por 3s.
- **`LocationFields`** (`components/LocationFields.vue`):
  - Props: `modelValue`, `countries: SupportedCountry[]`, `fieldErrors`
  - Country select localizado (lê `name_pt|en|es` conforme `useI18n().locale.value`)
  - Input de CEP/ZIP com placeholder `country.postal_example`; quando o valor bate o `postal_pattern` E `country.supports_lookup`, faz auto-lookup com **debounce 500ms**. Botão "Buscar" para retry manual (mesmo handler).
  - Estado/cidade pré-preenchidos pelo lookup mas sempre **editáveis** (sem readonly).
  - Trocar de país reseta `postal_code/state_*/city` e limpa erros.

### Gating de localização (onboarding)

Usuário recém-cadastrado é mandado direto pra `/profile` (vide `auth.md`) e **não consegue navegar para nenhuma outra rota autenticada** até preencher `country_code`, `state_name` e `city`. A flag fica em `useAuthStore().locationComplete: boolean` e é calculada no frontend a partir de `GET /api/profile` — não depende de campo no `UserSchema` (assim sobrevive a cache do FrankenPHP no backend).

**Quando `locationComplete` é atualizado:**
- `auth.fetchMe()` (chamada inicial pelo router guard) → dispara `refreshLocationStatus()`
- `auth.login()`, `auth.register()` (quando volta user direto), `auth.verifyEmail()`, `auth.resetPassword()` → todos disparam `refreshLocationStatus()`
- `ProfileView.onSubmit()` após `save()` → dispara `refreshLocationStatus()`
- `auth.logout()` → zera para `false`

**Definição de "completo"**: `Boolean(country_code && state_name && city)`. `postal_code` **não** é exigido (é condicional por país no backend, e usar só os 3 campos sempre obrigatórios garante que o save tenha passado). Se quiser exigir mais (ex.: `desired_role`), trocar a lógica em `auth.refreshLocationStatus()`.

**O que destrava o gating:**
1. Router guard em `router/index.ts` checa `auth.locationComplete` e redireciona para `profile` se for `false`.
2. AppLayout renderiza os itens do nav como botões desabilitados (cadeado + tooltip + redirecionam para profile no click) enquanto bloqueado.

## Efeitos colaterais

- Escritas: `profiles`, `profile_skills`
- Migrations:
  - `2026_04_21_000100_add_email_apply_to_profiles` — 5 colunas de email-apply (feature descontinuada na UI; colunas ainda no banco aguardando drop)
  - `2026_04_27_120000_replace_location_with_geo_fields_on_profiles` — drop `location`, adiciona `country_code`/`postal_code`/`state_code`/`state_name`/`city` + 3 indexes

## Testes

- `backend/tests/Feature/Profile/ShowProfileTest.php`
- `backend/tests/Feature/Profile/UpdateProfileTest.php`

## Pontos de atenção

- **Gating de localização preso** mesmo após save bem-sucedido → `ProfileView.onSubmit` esqueceu de chamar `auth.refreshLocationStatus()` antes do `setTimeout` do flash. Sem isso o `locationComplete` no Pinia continua `false` e o user fica preso mesmo com o profile salvo no banco.
- **Adicionar campo obrigatório novo no gating** (ex.: tornar `desired_role` obrigatório pra liberar) → editar `useAuthStore.refreshLocationStatus()` em `frontend/src/modules/auth/stores/auth.ts`. Hoje a fórmula é só `country_code && state_name && city`.
- **`firstOrCreate` no show** evita 404 em users novos — mas se você adicionar um campo NOT NULL sem default, ele quebra. Sempre forneça default no migration.
- **`skills()->sync`** apaga vínculos não enviados. Se o front por engano enviar `skills: []`, o user perde todas. Considerar exigir `skills` como `present` (não `nullable`).
- **Throttle `search`** é 120/min user, 30/min IP — autocomplete agressivo pode bater. O front já debounce 200ms.
- **Skills duplicadas no autocomplete**: o backend não impede `aliases` colidindo entre skills. O front filtra por `skill.id` na hora de adicionar, então duplicatas visuais não causam bug — mas a UX confunde. Considerar dedup no `SkillController` por `name LIKE`.
- **Locale do user vs locale do profile**: `users.locale` controla idioma da UI; `profile.languages` é lista de idiomas que o candidato fala. Não confundir.
- **Campo `salary_currency` foi removido do frontend** — o input de moeda não existe mais na ProfileView. O campo permanece no backend/banco mas não é mais editável pela UI.
- **Email-apply foi descontinuado na UI** — `ProfileSchema` Zod não tem mais os campos `email_apply_*`, `ProfileView` não tem mais a seção, e o save não envia. As regras no `UpdateProfileRequest` toleram ausência (são todas `nullable` ou cross-field) e as colunas continuam no banco. Limpeza completa (drop das colunas + remover regras + remover relação `emailApplyResume()`) está pendente.
- **Localização é obrigatória**: `country_code`, `state_name` e `city` são `required` no `UpdateProfileRequest`. O frontend sempre envia o objeto completo no PUT — alterações parciais não são suportadas. Se for adicionar PATCH parcial, repensar as regras.
- **`postal_code` é condicional**: required apenas para países com `supports_lookup=true` (BR/US/ES hoje). Para outros países o usuário preenche estado/cidade manualmente sem postal code.
- **Adicionar país com lookup** requer mudanças coordenadas: 1) `SupportedCountry::meta()` com `supports_lookup=true`, 2) novo Client implementando `PostalCodeLookupClient` (ou estender `ZippopotamClient::SUPPORTED`), 3) tag em `AppServiceProvider` se for cliente novo.
- **Coluna `location` foi removida.** Dados livres anteriores foram perdidos (não houve parse heurístico). Qualquer código legado que lia `$profile->location` quebra — buscar com grep antes de mexer.
