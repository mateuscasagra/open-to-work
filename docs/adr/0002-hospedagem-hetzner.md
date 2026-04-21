# 2. Hospedagem em Hetzner VPS self-hosted

Data: 2026-04-17
Status: Aceita

## Contexto

Requisito: custo de operação o mais baixo possível, com espaço para escalar. Avaliamos managed PaaS (Fly.io, Railway, Render) versus VPS self-hosted (Hetzner, DigitalOcean, Contabo).

## Decisão

Hetzner Cloud CPX21 (~€7.59/mês: 3 vCPU / 4 GB RAM / 80 GB SSD) com Docker Compose gerenciando app, Postgres, Redis, Meilisearch.

## Alternativas consideradas

- **Fly.io + Neon + Upstash:** free tiers excelentes mas custo cresce rápido após free tier; vendor lock-in maior.
- **AWS/GCP:** robustez desnecessária no MVP; custo imprevisível.
- **Railway/Render:** UX excelente mas $5–20/mês por serviço; fica caro com múltiplos serviços.

## Consequências

- **+** Custo fixo baixíssimo (€8/mês para infra completa)
- **+** Controle total; zero vendor lock-in
- **+** Migração futura para Swarm/K8s é direta (Docker desde o dia 1)
- **−** Precisamos operar (backup, monitoring, security patches)
- **−** Um único VPS = SPOF → mitigado com backup diário + script de provisionamento reproduzível

## Plano de escala

| Estágio | Usuários | Infra |
|---|---|---|
| MVP | 0–1k | 1 VPS CPX21 |
| Growth | 1k–10k | CPX31 + storage externo (R2) |
| Scale | 10k–50k | DB dedicado + workers separados + Swarm |
