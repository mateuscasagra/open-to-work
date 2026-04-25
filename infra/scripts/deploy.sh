#!/usr/bin/env bash
# Deploy de produção em VPS Hostinger. Invocado pelo GitHub Actions via SSH.
# VPS apertada (4GB): rolling restart simples, sem replicação.
set -euo pipefail

TAG="${1:-latest}"
cd /opt/opentowork

echo "==> Pulling images (tag=$TAG)..."
TAG="$TAG" docker compose -f docker-compose.prod.yml pull backend frontend

echo "==> Running migrations..."
TAG="$TAG" docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force

echo "==> Caching Laravel config/routes/views/events..."
TAG="$TAG" docker compose -f docker-compose.prod.yml run --rm backend sh -c "\
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan event:cache"

echo "==> Restarting app containers..."
TAG="$TAG" docker compose -f docker-compose.prod.yml up -d --no-deps backend frontend

echo "==> Restarting workers..."
TAG="$TAG" docker compose -f docker-compose.prod.yml up -d --no-deps horizon scheduler

echo "==> Reloading nginx (in case config changed)..."
docker compose -f docker-compose.prod.yml exec -T nginx nginx -s reload || true

echo "==> Pruning old images..."
docker image prune -f

echo "==> Deploy OK: $TAG"
