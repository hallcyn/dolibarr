#!/usr/bin/env bash
set -euo pipefail
# The upstream runner calls this after installation, before Apache starts.
mkdir -p /var/www/html/custom/indyui
cp -R /opt/indy-theme/module/indyui/. /var/www/html/custom/indyui/
chown -R www-data:www-data /var/www/html/custom/indyui
php /opt/railway/presentation.php
if [[ "${DOLI_RAILWAY_CRON:-0}" == 1 ]]; then
    printf '%s\n' '*/5 * * * * www-data /usr/bin/flock -n /data/sessions/cron.lock /usr/local/bin/php /opt/railway/cron.php >> /data/documents/railway-cron.log 2>&1' > /etc/cron.d/railway-dolibarr
    chmod 644 /etc/cron.d/railway-dolibarr
    cron
    echo '[railway] Cron enabled every five minutes; enable Scheduled Jobs in Dolibarr too.'
fi
