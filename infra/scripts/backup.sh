#!/usr/bin/env bash
# Daily Postgres dump + upload to S3/R2. Called via cron.
set -euo pipefail

BACKUP_DIR="/var/backups/opentowork"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
FILE="${BACKUP_DIR}/otw-${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

docker exec otw-postgres pg_dump -U opentowork opentowork | gzip > "$FILE"

# Encrypt before upload (replace key path)
gpg --batch --yes --passphrase-file /root/.backup-key \
    --symmetric --cipher-algo AES256 "$FILE"

# Upload (requires awscli + R2 endpoint)
aws s3 cp "${FILE}.gpg" "s3://opentowork-backups/postgres/" \
    --endpoint-url "$R2_ENDPOINT"

# Retention: delete local dumps older than 7 days
find "$BACKUP_DIR" -name '*.sql.gz*' -mtime +7 -delete

echo "Backup OK: ${FILE}.gpg"
