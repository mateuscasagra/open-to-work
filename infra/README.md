# Infra

## Dev local

```bash
docker compose -f infra/docker-compose.yml up -d
```

Serviços expostos:

| Serviço | Porta | Credenciais |
|---|---|---|
| Postgres | 5432 | opentowork / opentowork |
| Redis | 6379 | — |
| Meilisearch | 7700 | master key `dev_master_key_change_me` |
| MinIO API | 9000 | opentowork / opentowork_minio |
| MinIO Console | 9001 | ↑ |
| MailHog SMTP | 1025 | — |
| MailHog UI | 8025 | http://localhost:8025 |

Dados persistem em `infra/data/` (ignorado pelo git).

## Produção

```
docker compose -f infra/docker-compose.prod.yml up -d
```

Secrets ficam em `infra/secrets/*.env` (NÃO commitar).

### Estrutura esperada no VPS

```
/opt/opentowork/
├── docker-compose.prod.yml
├── nginx/
├── scripts/
└── secrets/
    ├── backend.env
    ├── postgres.env
    └── meilisearch.env
```

### Provisionamento

1. Instalar Docker + Docker Compose
2. `ufw allow 22,80,443/tcp`
3. Clonar repo e copiar `infra/` para `/opt/opentowork/`
4. Preencher `secrets/*.env`
5. `certbot --nginx -d opentowork.app` (certificado inicial)
6. Cron para backup: `0 3 * * * /opt/opentowork/scripts/backup.sh`
