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
- `email_apply_enabled: boolean`
- `email_apply_message_mode: nullable|in:fixed,variable` (required_if `email_apply_enabled`)
- `email_apply_message_template: nullable|string|max:5000` (required_if `message_mode=fixed`)
- `email_apply_resume_mode: nullable|in:fixed,variable` (required_if `email_apply_enabled`)
- `email_apply_resume_id: nullable|exists:resumes,id` (required_if `resume_mode=fixed`, ownership check)

### Fluxo
- **show**: `Profile::firstOrCreate(['user_id' => $user->id])` — garante perfil em branco para o front renderizar formulário sem 404.
- **update**: `UpdateProfileRequest` valida → `fill($validated)->save()` → `skills()->sync($payload['skills'])`. Os 5 campos `email_apply_*` e os 5 campos geográficos são persistidos no mesmo `update`.
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

### Email-apply settings

O perfil armazena 5 campos de configuração para candidatura por e-mail:

| Campo | Tipo | Propósito |
|---|---|---|
| `email_apply_enabled` | `boolean` | Liga/desliga envio automático de e-mail ao candidatar |
| `email_apply_message_mode` | `enum: fixed\|variable` | `fixed` = usa template salvo; `variable` = pede ao user cada vez |
| `email_apply_message_template` | `text\|null` | Template da mensagem (usado quando mode=fixed) |
| `email_apply_resume_mode` | `enum: fixed\|variable` | `fixed` = usa currículo salvo; `variable` = pede ao user cada vez |
| `email_apply_resume_id` | `FK resumes\|null` | Currículo padrão (usado quando resume_mode=fixed) |

Relação: `Profile::emailApplyResume()` → `belongsTo(Resume::class, 'email_apply_resume_id')`.

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
  - **Languages**: chips toggle
  - **Skills**: autocomplete com busca debounced (200ms) na API, dropdown posicionado com `z-50`, navegação por teclado (ArrowUp/Down/Enter/Escape), máximo 8 skills, tags com botão `×` para remover. Filtra sugestões já selecionadas.
  - **Email-apply settings**: seção condicional (toggle habilita). Radio buttons para `message_mode` (fixed/variable), textarea para template, radio para `resume_mode` (fixed/variable), select de currículo.
  - Submit captura 422 em `fieldErrors`, flash de sucesso por 3s
- **`LocationFields`** (`components/LocationFields.vue`):
  - Props: `modelValue`, `countries: SupportedCountry[]`, `fieldErrors`
  - Country select localizado (lê `name_pt|en|es` conforme `useI18n().locale.value`)
  - Input de CEP/ZIP com placeholder `country.postal_example`; quando o valor bate o `postal_pattern` E `country.supports_lookup`, faz auto-lookup com **debounce 500ms**. Botão "Buscar" para retry manual (mesmo handler).
  - Estado/cidade pré-preenchidos pelo lookup mas sempre **editáveis** (sem readonly).
  - Trocar de país reseta `postal_code/state_*/city` e limpa erros.

## Efeitos colaterais

- Escritas: `profiles`, `profile_skills`
- Migrations:
  - `2026_04_21_000100_add_email_apply_to_profiles` — 5 colunas de email-apply
  - `2026_04_27_120000_replace_location_with_geo_fields_on_profiles` — drop `location`, adiciona `country_code`/`postal_code`/`state_code`/`state_name`/`city` + 3 indexes

## Testes

- `backend/tests/Feature/Profile/ShowProfileTest.php`
- `backend/tests/Feature/Profile/UpdateProfileTest.php`

## Pontos de atenção

- **`firstOrCreate` no show** evita 404 em users novos — mas se você adicionar um campo NOT NULL sem default, ele quebra. Sempre forneça default no migration.
- **`skills()->sync`** apaga vínculos não enviados. Se o front por engano enviar `skills: []`, o user perde todas. Considerar exigir `skills` como `present` (não `nullable`).
- **Throttle `search`** é 120/min user, 30/min IP — autocomplete agressivo pode bater. O front já debounce 200ms.
- **Skills duplicadas no autocomplete**: o backend não impede `aliases` colidindo entre skills. O front filtra por `skill.id` na hora de adicionar, então duplicatas visuais não causam bug — mas a UX confunde. Considerar dedup no `SkillController` por `name LIKE`.
- **Locale do user vs locale do profile**: `users.locale` controla idioma da UI; `profile.languages` é lista de idiomas que o candidato fala. Não confundir.
- **Campo `salary_currency` foi removido do frontend** — o input de moeda não existe mais na ProfileView. O campo permanece no backend/banco mas não é mais editável pela UI.
- **Email-apply cross-field validation** é complexa (`required_if` encadeado). Se adicionar modos, atualizar as regras no FormRequest E os condicionais `v-if` no ProfileView.
- **`email_apply_resume_id`** valida ownership via `Rule::exists('resumes', 'id')->where('user_id', ...)`. Não remover o `where`.
- **Localização é obrigatória**: `country_code`, `state_name` e `city` são `required` no `UpdateProfileRequest`. O frontend sempre envia o objeto completo no PUT — alterações parciais não são suportadas. Se for adicionar PATCH parcial, repensar as regras.
- **`postal_code` é condicional**: required apenas para países com `supports_lookup=true` (BR/US/ES hoje). Para outros países o usuário preenche estado/cidade manualmente sem postal code.
- **Adicionar país com lookup** requer mudanças coordenadas: 1) `SupportedCountry::meta()` com `supports_lookup=true`, 2) novo Client implementando `PostalCodeLookupClient` (ou estender `ZippopotamClient::SUPPORTED`), 3) tag em `AppServiceProvider` se for cliente novo.
- **Coluna `location` foi removida.** Dados livres anteriores foram perdidos (não houve parse heurístico). Qualquer código legado que lia `$profile->location` quebra — buscar com grep antes de mexer.
