# Open to Work

Plataforma para organizar e acelerar a jornada de busca por emprego: agrega vagas de múltiplas fontes, gerencia candidaturas em Kanban, cria currículos e mede performance.

**Stack:** Laravel 12 + Vue 3 (TS) + PostgreSQL + Redis
**Hospedagem:** VPS Hostinger (Docker) + Cloudflare (DNS/CDN)

Ver [`ARQUITETURA.md`](./ARQUITETURA.md) para detalhes completos.

---

## Estrutura

```
open-to-work/
├── backend/        # Laravel 12 API (PHP 8.3 / FrankenPHP)
├── frontend/       # Vue 3 SPA (TS + Vite)
├── infra/          # Docker compose, scripts
├── .github/        # Pipelines CI/CD
└── docs/           # ARQUITETURA, módulos, ADRs
```

**Storage:** Currículos e anexos em **Cloudflare R2** (prod) / MinIO (dev — mesma API S3).
**Busca de vagas:** Postgres full-text via Scout `database` driver (Meilisearch foi removido).

## Requisitos locais

- PHP 8.3+, Composer 2
- Node 20+, npm 10+
- Docker + Docker Compose

## Subir ambiente de desenvolvimento

```bash
# Sobe Postgres, Redis, MinIO, MailHog
docker compose -f infra/docker-compose.yml up -d

# Backend
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve            # http://localhost:8000

# Frontend (outro terminal)
cd frontend
cp .env.example .env
npm install
npm run dev                  # http://localhost:5173
```

## Testes

```bash
# Backend
cd backend && vendor/bin/pest

# Frontend
cd frontend && npm test
```

## Linting

```bash
cd backend && vendor/bin/pint && vendor/bin/phpstan analyse
cd frontend && npm run lint && npm run type-check
```

## Licença

MIT (TBD).
