#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

fail() { echo "[railway] $*" >&2; exit 1; }
[[ "${PORT:-80}" == 80 ]] || fail "Set PORT and the Railway domain target port to 80."
[[ "${DOLI_CRON:-0}" == 0 ]] || fail "Use DOLI_RAILWAY_CRON=1, not DOLI_CRON=1 (which stops the web server)."
[[ "${DOLI_INSTALL_AUTO:-1}" == 1 ]] || fail "This template requires DOLI_INSTALL_AUTO=1."
[[ "${DOLI_DB_TYPE:-mysqli}" == mysqli ]] || fail "This template supports MariaDB/MySQL only."
if [[ -n "${RAILWAY_ENVIRONMENT_ID:-}" ]]; then
    [[ "${RAILWAY_VOLUME_MOUNT_PATH:-}" == /data ]] || fail "Attach the Dolibarr volume at /data before deploying."
fi

mkdir -p /data/{documents,custom,sessions,secrets,backups}
chmod 755 /data
chmod 700 /data/secrets /data/backups
for item in documents html/custom; do
    target="/data/${item##*/}"
    source="/var/www/$item"
    if [[ ! -L "$source" ]]; then
        # Only the image's seed files live here; user files belong in /data.
        [[ ! -d "$source" ]] || cp -an "$source/." "$target/"
        rm -rf "$source"
        ln -s "$target" "$source"
    fi
done
chown -R www-data:www-data /data/documents /data/custom /data/sessions
chmod 700 /data/sessions

php /opt/railway/configure.php
php /opt/railway/check-database.php --wait

echo "[railway] Starting the official Dolibarr installer and Apache."
exec /usr/local/bin/docker-run.sh "$@"
