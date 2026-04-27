# Módulo Location

**Propósito:** lookup de CEP/ZIP em APIs externas (ViaCEP para Brasil, zippopotam.us para US/ES) para preencher estado e cidade no perfil. Também expõe a lista de países suportados pelo sistema.

Esse módulo é **infraestrutura de suporte** ao [Profile](./profile.md) e ao [Admin](./admin.md) — não tem UI própria.

## Endpoints

| Método | Rota | Handler | Throttle |
|---|---|---|---|
| `GET` | `/api/location/countries` | `Api\Location\SupportedCountriesController` | `auth:sanctum` |
| `POST` | `/api/location/lookup` | `Api\Location\LookupPostalCodeController` | `auth:sanctum` + `throttle:location-lookup` |

## Backend

**Domínio:** `app/Domain/Location/`

```
Domain/Location/
  Contracts/PostalCodeLookupClient.php
  Clients/ViaCepClient.php
  Clients/ZippopotamClient.php
  DTOs/PostalCodeLookupResult.php
  DTOs/SupportedCountryData.php
  Exceptions/{PostalCodeNotFound,PostalCodeLookup,UnsupportedCountry,InvalidPostalCode}Exception.php
  Support/SupportedCountry.php          (PHP backed enum)
  Support/PostalCodeFormatter.php
  Actions/LookupPostalCode.php
  Queries/ListSupportedCountries.php
```

### Enum `SupportedCountry`

Lista curada de países (~23). Cada caso retorna `meta()` com `name_pt`, `name_en`, `name_es`, `postal_pattern` (regex), `postal_example`, `supports_lookup` (bool).

| Code | Lookup |
|---|---|
| `BR` | ViaCEP |
| `US`, `ES` | zippopotam.us |
| `CA, MX, AR, CL, CO, PE, PT, GB, IE, FR, DE, NL, IT, AU, NZ, IN, JP, KR, IL, ZA` | manual (sem lookup) |

Helper: `SupportedCountry::supportsLookup(): bool`.

**Adicionar país com lookup**:
1. Adicionar caso no enum com `supports_lookup => true` e `postal_pattern` adequada
2. Estender `ZippopotamClient::SUPPORTED` (se zippopotam suporta) **OU** criar novo Client implementando `PostalCodeLookupClient` e adicionar tag `'location.clients'` em `AppServiceProvider::register`

### `LookupPostalCode` (Action)

`execute(string $countryCode, string $postalCode): PostalCodeLookupResult`

Fluxo:
1. Resolve enum (`tryFrom` + uppercase). Se não bate ou `supports_lookup=false`, lança `UnsupportedCountryException`.
2. `PostalCodeFormatter::normalize()` valida formato e retorna canonicalização (sem hífen/espaço, uppercase). Se inválido, lança `InvalidPostalCodeException`.
3. Consulta cache positivo `loc:{country}:{normalized}` (TTL 30 dias). Hit → devolve direto.
4. Consulta cache negativo `loc:nf:{country}:{normalized}` (TTL 10 min). Hit → lança `PostalCodeNotFoundException` sem chamar API.
5. Itera clients (`tagged('location.clients')`); o que `supports($country)` chama `lookup()`.
6. **Sucesso** → cacheado em `loc:*` por 30 dias. **`PostalCodeNotFoundException`** → cacheado em `loc:nf:*` por 10 min. **`PostalCodeLookupException`** (timeout/5xx) → **NÃO** é cacheado, próxima tentativa retenta.

### Mapping de respostas

**ViaCEP** `https://viacep.com.br/ws/{cep}/json/`:
- `localidade` → `city`
- `uf` → `state_code`
- Tabela estática `UF → state_name` no client (27 entradas)
- Resposta `{"erro": true}` → `PostalCodeNotFoundException`

**Zippopotam** `https://api.zippopotam.us/{cc}/{postal}` (lowercase no `cc`):
- `places[0]['place name']` → `city`
- `places[0]['state']` → `state_name`
- `places[0]['state abbreviation']` → `state_code` (pode ser null em alguns países)
- 404 → `PostalCodeNotFoundException`. `places: []` raro → mesma exception.

### Service binding

Em `AppServiceProvider::register`:

```php
$this->app->tag([ViaCepClient::class, ZippopotamClient::class], 'location.clients');
$this->app->bind(LookupPostalCode::class, fn ($app) => new LookupPostalCode(
    clients: $app->tagged('location.clients'),
    cache: $app->make('cache.store'),
));
```

### Rate limiter

Definido em `AppServiceProvider::configureRateLimiters()`:
- `location-lookup`: 30/min por user, 10/min por IP

### Mensagens i18n

Backend `lang/{pt_BR,en,es}/location.php`:
- `not_found` (404)
- `unsupported_country` (422)
- `invalid_postal_code` (422)
- `lookup_failed` (502)

## Frontend

**Schemas (Zod):** `frontend/src/shared/api/schemas.ts`
- `SupportedCountrySchema`
- `PostalCodeLookupResultSchema`

**Composables:** `frontend/src/modules/profile/composables/useLocationLookup.ts`
- `useSupportedCountries()` — TanStack Query, key `['location', 'countries']`, `staleTime: Infinity` (lista é imutável dentro da sessão)
- `useLocationLookup()` — `lookup(country, postal)` chama `POST /api/location/lookup`; mapeia status HTTP em chaves i18n via `errorKey` (`profile.location_not_found`, `profile.location_invalid`, `profile.location_api_failed`)

**Componente:** `frontend/src/modules/profile/components/LocationFields.vue` — descrito em [profile.md](./profile.md#frontend).

## Efeitos colaterais

- Cache de 30 dias em `loc:{country}:{postal}` (driver default `cache.store`)
- Chamadas externas:
  - `https://viacep.com.br` — sem auth, sem rate limit oficial
  - `https://api.zippopotam.us` — sem auth, sem rate limit oficial

## Testes

`backend/tests/Feature/Location/LookupPostalCodeTest.php` — 17 casos cobrindo todas as camadas defensivas:

| Camada / Cenário | Teste |
|---|---|
| Auth | `rejeita usuário não autenticado` |
| Happy path BR (ViaCEP) | `busca endereço brasileiro via ViaCEP` |
| Happy path US (zippopotam) | `busca ZIP americano via zippopotam` |
| Happy path ES (zippopotam) | `busca código postal espanhol via zippopotam` |
| Normalização de hífen | `normaliza CEP com hífen antes de consultar` |
| 404 upstream BR | `retorna 404 quando ViaCEP devolve erro` |
| 404 upstream zippopotam | `retorna 404 quando zippopotam devolve 404` |
| País fora do enum | `retorna 422 para país fora do enum` |
| País sem lookup | `retorna 422 para país sem suporte de lookup` |
| Formato inválido | `retorna 422 para CEP com formato inválido` |
| Regex curto-circuita externa | `regex inválido NÃO chama API externa` |
| 5xx upstream | `retorna 502 quando upstream falha` |
| Cache positivo (30 dias) | `cache evita chamada duplicada para mesmo CEP` |
| Cache negativo (10 min) | `cache negativo evita re-tentar CEP inexistente` |
| 5xx não é cacheado | `falha de transporte não é cacheada (permite retry)` |
| Rate limit 30/min/user | `rate limiter corta floods em 30/min/user` |
| Rate limit isolado por user | `rate limiter conta por usuário (outro user não é afetado)` |

`backend/tests/Feature/Location/SupportedCountriesTest.php` — 4 casos (auth, lista completa do enum, flags `supports_lookup` corretas em BR/US/ES, nomes localizados pt/en/es).

```bash
docker exec otw-backend php artisan test --filter="LookupPostalCode|SupportedCountries"
```

## Pontos de atenção

- **`PostalCodeFormatter::normalize` falha cedo**: se o `postal_code` não bate o regex do país, lança `InvalidPostalCodeException` antes de qualquer chamada externa. Útil pra evitar floods.
- **CEP brasileiro com hífen** (`01310-100`) é normalizado para `01310100` antes de bater no ViaCEP. Cache leva o normalizado, não o original.
- **Cache key não inclui usuário**: lookup do mesmo CEP por dois users diferentes só consulta a API uma vez. CEPs são públicos, sem leak.
- **ViaCEP fica no Brasil, zippopotam.us fica nos EUA** — latência de US/ES pode ser maior. Timeout do client é 10s + 2 retries de 500ms.
- **Adicionar BR como zippopotam fallback é tentador, mas não vale**: zippopotam tem cobertura inferior pra CEPs brasileiros e devolve sem nome de UF completo.
- **Se ViaCEP cair em produção**: o cache absorve a maioria dos lookups recentes. CEPs novos retornam 502 — usuário pode preencher manualmente. Não há failover automático para outra API.
- **Rate limiter `location-lookup` 30/user/min** é generoso pra UX (debounce 500ms no front limita pra ~120/min em digitação contínua, mas raramente acontece). Se for fechar mais, lembrar que cada `country` change reseta o input e dispara um novo lookup.
- **Trocar `country_code` no frontend reseta `postal_code`, `state_*` e `city`** — comportamento intencional pra evitar misturar dados de países diferentes.

### Defesa contra abuso

Camadas em ordem de execução, da mais barata pra mais cara:

1. **Frontend — debounce 500ms**: não dispara a cada tecla (`LocationFields.vue`).
2. **Frontend — `lastLookupKey`**: mesma combinação `country+postal` não é re-tentada em sequência.
3. **Frontend — regex do país**: só dispara lookup quando o input bate `postal_pattern`.
4. **Backend — `PostalCodeFormatter::normalize`**: rejeita formato inválido com 422, **sem** chamar API externa.
5. **Backend — rate limiter**: 30/min/user, 10/min/IP em `POST /api/location/lookup`.
6. **Backend — cache positivo**: 30 dias. Lookups repetidos = zero chamadas externas.
7. **Backend — cache negativo**: 10 min. CEPs inexistentes (404) não são re-consultados nesse intervalo.
8. **Backend — sem cache de 5xx**: erros transitórios permitem retry imediato — não punimos o user por falha de transporte.

Cenário pior caso (atacante autenticado): 30 CEPs **novos e diferentes** por minuto, todos batendo o regex mas inexistentes → 30 calls externas/min/user. A partir do 2º lookup do mesmo CEP, não há mais chamada por 10 min.
