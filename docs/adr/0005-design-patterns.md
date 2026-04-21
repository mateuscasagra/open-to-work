# 5. Design patterns: Actions + DTOs + Pipeline

Data: 2026-04-17
Status: Aceita

## Contexto

Laravel é flexível demais — sem convenções, código vira salada (controllers gordos, services com 30 métodos, repositories redundantes sobre Eloquent). Precisamos de convenções.

## Decisão

- **Action classes (1 use case = 1 classe):** `CreateApplication`, `SyncJobsFromSource`, `ChangeApplicationStatus`. Elimina services genéricos.
- **DTOs via `spatie/laravel-data`:** entrada/saída tipada de Actions e boundaries com APIs externas.
- **Pipeline pattern (`Illuminate\Pipeline`):** para fluxos multi-estágio (ex: agregação de vagas).
- **Strategy pattern:** drivers plugáveis (fontes de vagas, provedores OAuth).
- **Events & Listeners:** side effects (e-mails, métricas) desacoplados via fila.
- **DDD-lite:** pastas em `app/Domain/{User,Job,Application,Resume,Metrics}`.
- **CQRS-lite:** `Actions/` mutam estado; `Queries/` leem e retornam DTOs.

## Anti-patterns banidos

- Repository pattern sobre Eloquent.
- Services com 20 métodos públicos.
- Lógica de negócio em controllers.
- Magic strings para status (usar Enums PHP 8.1+).
- Abstração prematura (só introduza interface quando existirem 2+ implementações).

## Consequências

- **+** Cada arquivo faz uma coisa → código fácil de ler e testar.
- **+** Onboarding mais rápido: o padrão é óbvio.
- **+** Refactor local (uma Action de cada vez).
- **−** Mais arquivos que em projetos Laravel "tradicionais".
- **−** Custa disciplina manter a fronteira entre `Actions/` e `Queries/`.
