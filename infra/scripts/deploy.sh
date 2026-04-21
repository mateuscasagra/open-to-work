#!/usr/bin/env bash
# Zero-downtime deploy on Hetzner VPS. Invoked by GitHub Actions via SSH.
set -euo pipefail

TAG="${1:-latest}"
cd /opt/opentowork

echo "Pulling images (tag=$TAG)..."
TAG="$TAG" docker compose -f docker-compose.prod.yml pull

echo "Running migrations..."
docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force

echo "Rolling restart..."
TAG="$TAG" docker compose -f docker-compose.prod.yml up -d --no-deps --scale backend=2 backend
sleep 10
TAG="$TAG" docker compose -f docker-compose.prod.yml up -d --no-deps --scale backend=1 backend

echo "Restarting workers..."
TAG="$TAG" docker compose -f docker-compose.prod.yml up -d --no-deps horizon scheduler

echo "Deploy OK: $TAG"
