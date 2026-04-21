#!/usr/bin/env bash
# Restaura o banco a partir de um backup criptografado no R2/S3.
# Uso:
#   ./restore.sh                    # pega o mais recente
#   ./restore.sh otw-20260418.sql.gz.gpg   # pega backup específico
#
# Requer: awscli, gpg, docker, e as vars R2_ENDPOINT + /root/.backup-key.
set -euo pipefail

BACKUP_BUCKET="s3://opentowork-backups/postgres"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

FILE="${1:-}"

if [[ -z "$FILE" ]]; then
    echo "→ Buscando backup mais recente em ${BACKUP_BUCKET}..."
    FILE="$(aws s3 ls "${BACKUP_BUCKET}/" --endpoint-url "$R2_ENDPOINT" \
        | sort | tail -n1 | awk '{print $4}')"
    if [[ -z "$FILE" ]]; then
        echo "✗ Nenhum backup encontrado."
        exit 1
    fi
fi

echo "→ Baixando ${FILE}..."
aws s3 cp "${BACKUP_BUCKET}/${FILE}" "${WORK_DIR}/${FILE}" \
    --endpoint-url "$R2_ENDPOINT"

echo "→ Decriptando..."
gpg --batch --yes --passphrase-file /root/.backup-key \
    --decrypt "${WORK_DIR}/${FILE}" > "${WORK_DIR}/dump.sql.gz"

echo "→ Aviso: o banco atual será SUBSTITUÍDO. Ctrl-C em 5s pra cancelar."
sleep 5

echo "→ Drop + recreate do schema..."
docker exec -i otw-postgres psql -U opentowork -d postgres \
    -c "DROP DATABASE IF EXISTS opentowork;" \
    -c "CREATE DATABASE opentowork OWNER opentowork;"

echo "→ Restaurando dump..."
gunzip -c "${WORK_DIR}/dump.sql.gz" \
    | docker exec -i otw-postgres psql -U opentowork -d opentowork

echo "→ Sanity check: contando tabelas..."
docker exec otw-postgres psql -U opentowork -d opentowork -tAc \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';"

echo "✓ Restore OK a partir de ${FILE}."
