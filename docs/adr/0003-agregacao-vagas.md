# 3. Agregação de vagas via drivers plugáveis

Data: 2026-04-17
Status: Aceita

## Contexto

Produto precisa listar vagas. LinkedIn, Catho e Indeed **não oferecem API pública** — scraping viola ToS e arrisca o negócio. Precisamos de fontes legais + arquitetura que permita adicionar novas fontes sem reescrever tudo.

## Decisão

- **Strategy pattern:** interface `JobSourceDriver` com método `fetch(): iterable<JobDTO>`. Cada fonte é uma classe concreta.
- **Pipeline pattern:** fluxo `Fetch → Normalize → Deduplicate → Persist` como estágios reutilizáveis.
- **Scheduler:** Laravel Scheduler roda o pipeline a cada 6h.
- **Fontes MVP:** RemoteOK, Arbeitnow, Remotive, WeWorkRemotely (APIs públicas) + Gupy (scraping leve de páginas públicas de carreira).

## Deduplicação

Hash canônico SHA-256 de `normalize(title) + normalize(company) + normalize(location)`. Duplicatas compartilham uma linha em `jobs` com múltiplas entradas em `job_sources`.

## Consequências

- **+** Adicionar fonte = nova classe + registro no container. Zero mudança no core.
- **+** Testabilidade: cada driver tem seus próprios testes com fixtures.
- **+** Falha em uma fonte não afeta as demais (isolamento via jobs de fila).
- **−** Cobertura BR limitada no MVP (Gupy cobre bem empresas BR; para o resto, precisamos crescer a lista em v1.1+).
- **−** Scraping de Gupy pode quebrar com mudanças no HTML → monitorar via Sentry e ter fallback.
