#!/usr/bin/env bash
set -euo pipefail
# The upstream runner calls this after installation, before Apache starts.
if [[ "${DOLI_RAILWAY_CRON:-0}" == 1 ]]; then
    printf '%s\n' '*/5 * * * * www-data /usr/bin/flock -n /data/sessions/cron.lock /usr/local/bin/php /opt/railway/cron.php >> /data/documents/railway-cron.log 2>&1' > /etc/cron.d/railway-dolibarr
    chmod 644 /etc/cron.d/railway-dolibarr
    cron
    echo '[railway] Cron enabled every five minutes; enable Scheduled Jobs in Dolibarr too.'
fi
