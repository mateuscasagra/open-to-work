# Módulo Suggestions

**Propósito:** canal interno de feedback colaborativo — qualquer usuário autenticado pode publicar sugestões para o time, e a comunidade ranqueia (upvote/downvote) o que considera mais importante.

## Endpoints

| Método | Rota | Handler | Throttle / Auth |
|---|---|---|---|
| `GET` | `/api/suggestions?page=N` | `SuggestionController@index` | `auth:sanctum` |
| `GET` | `/api/suggestions/quota` | `SuggestionController@quota` | `auth:sanctum` |
| `POST` | `/api/suggestions` | `SuggestionController@store` | `throttle:suggestions-write` (10/min user) |
| `DELETE` | `/api/suggestions/{id}` | `SuggestionController@destroy` | `auth:sanctum` + `SuggestionPolicy` |
| `POST` | `/api/suggestions/{id}/vote` | `SuggestionController@vote` | `throttle:suggestions-vote` (60/min user) |

**Forma de cada Suggestion no payload:**

```json
{
  "id": 1,
  "user": { "id": 7, "name": "Diego" },
  "title": "Add dark mode",
  "body": "...",
  "upvotes_count": 12,
  "downvotes_count": 3,
  "score": 9,
  "my_vote": 1 | -1 | null,
  "rank": 1 | 2 | 3 | null,
  "created_at": "2026-04-29T..."
}
```

`POST /vote` recebe `{ "value": "up" | "down" }`. Convertido para `1` / `-1` na Action.

**Erros mapeados:**
- 5ª sugestão na janela de 7d → **429** com `{ message, next_slot_at }` (`WeeklyQuotaExceededException`)
- Voto na própria sugestão → **403** (`CannotVoteOwnSuggestionException`)
- Validation → **422** (Laravel padrão)
- Delete sem ser dono → **403** (Policy)

## Backend

**Controller:** `backend/app/Http/Controllers/Api/SuggestionController.php` — orquestra Actions, normaliza resposta com `serialize()` privado (injeta `my_vote` e `rank`).

**Domínio:** `backend/app/Domain/Suggestion/`
- **Actions:** `CreateSuggestion` (valida quota rolling 7d), `CastVote` (lógica de toggle/replace dentro de `DB::transaction` + `lockForUpdate` no select do voto existente).
- **DTO:** `SuggestionData` (string $title, string $body) — extends `Spatie\LaravelData\Data`.
- **Exceptions:** `WeeklyQuotaExceededException` carrega `Carbon $nextSlotAt`; `CannotVoteOwnSuggestionException` sem campos extras. Ambas usam `__('suggestions.errors.*')`.

**Policy:** `backend/app/Policies/SuggestionPolicy.php` — só método `delete($user, $suggestion)` (ownership). Auto-discovery por convenção (sem registro manual).

**Models:**
- `backend/app/Models/Suggestion.php` — `belongsTo(User)`, `hasMany(SuggestionVote)`, casts integer em counts/score.
- `backend/app/Models/SuggestionVote.php` — `belongsTo(User)`, `belongsTo(Suggestion)`, cast integer em value.

**i18n:** `backend/lang/{pt_BR,en,es}/suggestions.php` — mensagens das exceptions traduzidas.

**Rate limiters** (em `AppServiceProvider::configureRateLimiters()`):
- `suggestions-write`: 10/min user, 3/min IP (burst protection no POST).
- `suggestions-vote`: 60/min user, 10/min IP.

### Quota rolling 7 dias

`CreateSuggestion::execute()` conta `where('user_id', $user->id)->where('created_at', '>=', now()->subDays(7))`. Se ≥ 5, lança `WeeklyQuotaExceededException(nextSlotAt: oldest->created_at + 7d)`.

`SuggestionController::quota()` retorna `{ used, limit: 5, next_slot_at }` — `next_slot_at` é null enquanto used < limit.

**Trade-off**: deletar uma sugestão NÃO libera vaga na quota imediatamente — a row some, mas a contagem do período usa apenas registros ainda existentes. Isso permite que o usuário regenere após delete (aceito como pragmático para evitar soft-delete + log auxiliar).

### Voto: state machine

`CastVote::execute(User, Suggestion, int value ∈ {1, -1})`:
1. Se `value` inválido → `InvalidArgumentException`.
2. Se `user->id === suggestion->user_id` → `CannotVoteOwnSuggestionException`.
3. `DB::transaction`:
   - `SELECT ... FOR UPDATE` no voto existente (`lockForUpdate`) — evita race condition em duplo-clique.
   - **Mesmo value** → delete (toggle off, retorna null).
   - **Value oposto** → update value.
   - **Sem voto anterior** → create.
   - Recalcula `upvotes_count`, `downvotes_count`, `score` via `COUNT(*)` direto na tabela `suggestion_votes` e `update()` na sugestão (não usar `loadCount` aqui — não marca atributos como dirty para `save()`).

### Ranking top-3 global

`SuggestionController::topIds()` — query separada `Suggestion::orderByDesc('score')->orderBy('created_at')->limit(3)->pluck('id')`. Tiebreaker `created_at ASC` premia consistência (a mais antiga vence empate). O mesmo `ORDER BY` é usado na listagem paginada (`index`) para evitar inconsistência visual entre rank e ordem.

## Frontend

**Arquivos:** `frontend/src/modules/suggestions/`

- **`SuggestionsView`** (`views/`) — página única com header (título, contador de quota, botão "Nova sugestão"), modal de criação (Teleport, igual KanbanView), lista de cards verticais, paginação simples (prev/next). Empty state quando lista vazia. Banner de erro temporário (4s) para `cannot_vote_own`.
- **Componentes:**
  - `RankBadge.vue` — props `rank: 1|2|3|null`, `placement: 'above' | 'inline'`. Renderiza coroa custom (rank=1, `text-amber-500`) ou troféu Heroicons (2/3, `text-slate-400` / `text-amber-700`). `null` → `null`.
  - `VoteButtons.vue` — props `myVote, score, disabled, disabledReason`. Layout vertical chevron-up + score + chevron-down. Estado ativo: `text-brand-600 bg-brand-50` (up) / `text-red-600 bg-red-50` (down). Disabled: `opacity-30 cursor-not-allowed`.
- **Composables:**
  - `useSuggestions(page: Ref<number>)` — `useQuery({ queryKey: ['suggestions', page] })`.
  - `useSuggestionsQuota()` — `useQuery({ queryKey: ['suggestions', 'quota'] })`.
  - `useCreateSuggestion` — `useMutation`. Erros classificados: `quota_exceeded` (com `nextSlotAt`), `validation` (com `errors`), `unknown`. Invalida `['suggestions']`.
  - `useCastVote` — `useMutation` com **optimistic update** que aplica delta correto (tabela 3×3: estado anterior null/1/-1 × ação up/down). Atualiza TODAS as queries `['suggestions']` via `setQueriesData` (cobre páginas múltiplas). `onError` restaura snapshot. Erros: `forbidden`, `unknown`. `rank` não é recalculado localmente — refresca após `onSettled` invalidar.
  - `useDeleteSuggestion` — `useMutation`. Invalida `['suggestions']`.

- **Schemas Zod** (em `frontend/src/shared/api/schemas.ts`): `SuggestionAuthorSchema`, `SuggestionVoteValueSchema` (literal 1 | -1), `SuggestionRankSchema` (literal 1 | 2 | 3), `SuggestionSchema`, `SuggestionsPageSchema`, `SuggestionQuotaSchema`. Tipos exportados: `Suggestion`, `SuggestionsPage`, `SuggestionQuota`, etc.

- **Router:** `frontend/src/router/index.ts` — rota `/app/suggestions` lazy-loaded.
- **AppLayout:** item `suggestions` no `navItems` (entre `resumes` e `profile`), ícone Heroicons `light-bulb`.
- **i18n:** chave `nav.suggestions` + bloco `suggestions.*` em `frontend/src/locales/{pt-BR,en,es}.json`.

## Efeitos colaterais

- Escritas: tabelas `suggestions` e `suggestion_votes` (com índices `score`, `(score, created_at)`, `unique(user_id, suggestion_id)`).
- Migrations: `2026_04_29_000100_create_suggestions_table`, `2026_04_29_000200_create_suggestion_votes_table`.
- **Sem** soft deletes, eventos, queue, S3 ou e-mails.
- Cascade delete: deletar sugestão remove votos via `cascadeOnDelete` no FK.

## Testes

- `backend/tests/Feature/Suggestions/CreateSuggestionTest.php` — 8 casos (criar, validar, quota 429, janela rolling).
- `backend/tests/Feature/Suggestions/ListSuggestionsTest.php` — 5 casos (paginar, ordenar, my_vote, rank global em múltiplas páginas).
- `backend/tests/Feature/Suggestions/CastVoteTest.php` — 10 casos (up/down, toggle off, replace, can't own, unique constraint, agregação multi-user).
- `backend/tests/Feature/Suggestions/DeleteSuggestionTest.php` — 5 casos (próprio, alheio 403, cascade, quota não libera, auth).
- `backend/tests/Feature/Suggestions/QuotaTest.php` — 6 casos (zero, com itens, fora da janela, next_slot_at).
- `frontend/tests/suggestionsSchemas.test.ts` — 9 casos (parse Suggestion / Page / Quota com vários my_vote/rank).
- `frontend/tests/useCreateSuggestion.test.ts` — 4 casos (success, 429, 422, 500).
- `frontend/tests/useCastVote.test.ts` — 6 casos (POST, optimistic increment, toggle off, replace, rollback em 403, multi-page cache).

## Pontos de atenção

- **`rank` pode estar desatualizado por ~300ms após votar.** A optimistic update altera `score` localmente, mas `rank` depende do top-3 GLOBAL — só atualiza quando `onSettled` invalida e o `useQuery` refetch trazer o novo `rank`. Se isso virar problema visual, considere recomputar rank localmente ordenando o cache (mais código, ganho marginal).
- **Quota não libera ao deletar.** Trade-off pragmático para evitar soft-deletes. Se virar feedback recorrente, mude para soft-deletes E inclua `withTrashed()` na contagem da quota.
- **Race condition em duplo-clique mitigada via `lockForUpdate`** dentro de `DB::transaction`. Sem isso, o `unique(user_id, suggestion_id)` ainda protege via DB constraint, mas a UX seria pior (erro 500 em vez de toggle limpo).
- **Não use `loadCount` em `CastVote`** para recomputar counts — ele seta os atributos sem marcá-los dirty, então `save()` ignora. Use `COUNT(*)` direto + `update([...])`. Já há teste cobrindo isso (`aggregates votes from multiple users correctly`).
- **`my_vote` no `index` vem de subquery `addSelect`** (sem N+1). Se você adicionar mais campos derivados, mantenha esse padrão — não use `with('votes')` que carrega todos os votos por linha.
- **Tiebreaker `created_at ASC`** está repetido em 2 lugares: `SuggestionController::index()` e `SuggestionController::topIds()`. Se mudar, atualize ambos para evitar inconsistência entre ordem da lista e ranks.
- **i18n nas exceptions** (`__('suggestions.errors.*')`) — qualquer string nova vai em `backend/lang/{pt_BR,en,es}/suggestions.php`. Não hardcode mensagens em PHP.
- **`POST /vote` retorna a Suggestion completa** (com `my_vote` e `rank` recalculados) — front sincroniza cache sem precisar invalidar lista. Mantenha esse contrato; senão composable precisará invalidar.
- **Coroa é SVG path custom** (Heroicons não tem coroa nativamente). Se trocar o desenho, mantenha 24×24 viewBox e `stroke-width="1.75"` para coerência com outros ícones do app.
- **Throttle `suggestions-write`** (10/min) é burst protection, NÃO substitui a quota semanal (que vive na Action). Quota = regra de domínio; throttle = proteção operacional.
