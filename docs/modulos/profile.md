# Módulo Profile

**Propósito:** dados do candidato usados pelo matching e pelo currículo (cargo desejado, seniority, modalidade, faixa salarial, localização, idiomas, stack).

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

**Form Requests:** `app/Http/Requests/Profile/` — valida:
- `desired_role: string|nullable`
- `seniority`/`modality` via `Rule::enum`
- `salary_min/max: integer|nullable`
- `salary_currency: string|size:3`
- `location: string|nullable`
- `languages: array of SupportedLocale`
- `bio: string|max:2000`
- `skills: array of integer (skill IDs)`

### Fluxo
- **show**: `Profile::firstOrCreate(['user_id' => $user->id])` — garante perfil em branco para o front renderizar formulário sem 404.
- **update**: valida → `update(...)` → `skills()->sync($payload['skills'])`.
- **search**: `WHERE LOWER(name) LIKE LOWER(?)`, limite 50.

## Frontend

**Arquivos:** `frontend/src/modules/profile/`

- **`useProfile`** (`composables/useProfile.ts`):
  - `fetch()` → `GET /api/profile`, parse `ProfileSchema`
  - `save(payload)` → `PUT /api/profile`
  - Export `searchSkills(q)` para autocomplete
- **`ProfileView`** (`views/ProfileView.vue`):
  - Form com todos os campos do perfil
  - **Languages**: chips toggle
  - **Skills**: input com debounce de 200ms, dropdown de sugestões, click adiciona (sem duplicar), botão `×` remove
  - Submit captura 422 em `fieldErrors`, flash de sucesso por 3s

## Efeitos colaterais

- Escritas: `profiles`, `profile_skills`

## Testes

- `backend/tests/Feature/Profile/ShowProfileTest.php`
- `backend/tests/Feature/Profile/UpdateProfileTest.php`

## Pontos de atenção

- **`firstOrCreate` no show** evita 404 em users novos — mas se você adicionar um campo NOT NULL sem default, ele quebra. Sempre forneça default no migration.
- **`skills()->sync`** apaga vínculos não enviados. Se o front por engano enviar `skills: []`, o user perde todas. Considerar exigir `skills` como `present` (não `nullable`).
- **Throttle `search`** é 120/min user, 30/min IP — autocomplete agressivo pode bater. O front já debounce 200ms.
- **Skills duplicadas no autocomplete**: o backend não impede `aliases` colidindo entre skills. O front filtra por `skill.id` na hora de adicionar, então duplicatas visuais não causam bug — mas a UX confunde. Considerar dedup no `SkillController` por `name LIKE`.
- **Locale do user vs locale do profile**: `users.locale` controla idioma da UI; `profile.languages` é lista de idiomas que o candidato fala. Não confundir.
- **Currency** não tem validação contra ISO 4217 — qualquer string de 3 chars passa. Se virar fonte de bug em métricas, validar com `Rule::in([...])`.
