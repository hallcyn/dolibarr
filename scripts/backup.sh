#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
[[ -f /var/www/documents/install.lock ]] || { echo 'Dolibarr is not installed.' >&2; exit 1; }
[[ "${1:-}" == '--stdout' || -z "${1:-}" ]] || { echo 'Usage: dolibarr-backup [--stdout]' >&2; exit 1; }
scratch_dir=$(mktemp -d)
trap 'rm -rf "$scratch_dir"' EXIT
echo 'Creating SQL + documents + custom modules + instance key backup. Pause writes during this operation.' >&2
# MYSQL_PWD avoids passing the DB password in the process command line.
export MYSQL_PWD="${DOLI_DB_PASSWORD:?}"
mysqldump --protocol=tcp --host="${DOLI_DB_HOST:?}" --port="${DOLI_DB_HOST_PORT:-3306}" \
    --user="${DOLI_DB_USER:?}" --single-transaction --quick --skip-lock-tables \
    --hex-blob --default-character-set=utf8mb4 "${DOLI_DB_NAME:?}" > "$scratch_dir/database.sql"
unset MYSQL_PWD
printf '%s\n' "${DOLI_VERSION:-unknown}" > "$scratch_dir/dolibarr-version.txt"
if [[ "${1:-}" == '--stdout' ]]; then
    tar -czf - -C "$scratch_dir" database.sql dolibarr-version.txt -C /data \
        --exclude=documents/railway-cron.log documents custom secrets
else
    backup_path="/data/backups/dolibarr-$(date -u +%Y%m%dT%H%M%SZ).tar.gz"
    tar -czf "$backup_path" -C "$scratch_dir" database.sql dolibarr-version.txt -C /data \
        --exclude=documents/railway-cron.log documents custom secrets
    chmod 600 "$backup_path"
    echo "$backup_path"
fi
