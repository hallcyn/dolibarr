#!/usr/bin/env bash
# Run before the upstream entrypoint, while Apache and cron are still stopped.
set -euo pipefail
[[ ${DOLI_VERSION:-} == 24.0.3 && -f /data/documents/install.lock ]] || exit 0
umask 077
state=/data/backups/upgrade-24.0.2-to-24.0.3
exec 9>"/data/backups/.patch-upgrade.lock"
flock -x 9
if [[ -e "$state.in-progress" ]]; then
    echo "[railway] Incomplete patch upgrade. Inspect $state.log and the pre-upgrade archive before recovery." >&2
    exit 1
fi

database_version() {
    MYSQL_PWD="$DOLI_DB_PASSWORD" mysql --protocol=tcp --host="$DOLI_DB_HOST" --port="${DOLI_DB_HOST_PORT:-3306}" \
        --user="$DOLI_DB_USER" --batch --skip-column-names "$DOLI_DB_NAME" -e "
        SELECT value FROM llx_const WHERE entity=0 AND value <> ''
          AND name IN ('MAIN_VERSION_LAST_UPGRADE','MAIN_VERSION_LAST_INSTALL')
        ORDER BY (name='MAIN_VERSION_LAST_UPGRADE') DESC LIMIT 1"
}
installed=$(database_version)
[[ "$installed" == 24.0.2 ]] || exit 0

# The source version in this archive describes its database, not the new image.
archive=$(mktemp "$state.XXXXXXXX.tar.gz")
if ! DOLI_VERSION=24.0.2 dolibarr-backup --stdout > "$archive" || ! tar -tzf "$archive" >/dev/null; then
    rm -f "$archive"
    echo '[railway] Pre-upgrade backup failed. Database upgrade did not start.' >&2
    exit 1
fi
echo "[railway] Pre-upgrade archive ready: $archive"
printf '%s\n' "$archive" > "$state.in-progress"
cd /var/www/html/install
# Use the native CLI with a container-local upgrade marker. The persistent
# install.lock stays in place and the marker is never served by Apache.
unlock=/var/www/html/upgrade.unlock
trap 'rm -f "$unlock"' EXIT
touch "$unlock"
for step in upgrade.php upgrade2.php step5.php; do
    if ! php "$step" 24.0.2 24.0.0 >> "$state.log" 2>&1; then
        echo "[railway] Dolibarr $step failed; see $state.log." >&2
        exit 1
    fi
done
if [[ $(database_version) != 24.0.3 ]]; then
    echo "[railway] Dolibarr did not record version 24.0.3; see $state.log." >&2
    exit 1
fi
rm -f "$state.in-progress"
echo '[railway] Dolibarr patch upgrade completed (24.0.2 -> 24.0.3).'
