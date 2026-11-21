#!/usr/bin/env bash
# Run on the dedicated GitHub Actions runner with synthetic data only.
set -euo pipefail
: "${RUNNER_TEMP:?Use the isolated patch-upgrade job}"
override="$RUNNER_TEMP/old-image.yml"
cat > "$override" <<'YAML'
services:
  dolibarr:
    image: dolibarr-before:24.0.2
YAML

docker compose -f compose.yaml -f "$override" up -d --no-build --wait --wait-timeout 300
db_container=$(docker compose ps -q mariadb)
for fixture in tests/indy/seed.php tests/receipts/seed.php; do
    docker compose cp "$fixture" dolibarr:/tmp/seed.php
    docker compose exec -T --user www-data dolibarr php /tmp/seed.php
done
docker compose exec -T dolibarr php /opt/railway/presentation.php
docker compose exec -T dolibarr sh -ec '
    test "$DOLI_VERSION" = 24.0.2
    echo "attachment from the old installation" > /data/documents/patch-test.txt
    echo "custom volume marker" > /data/custom/patch-test.txt
'

fingerprint() {
    docker compose exec -T mariadb sh -ec '
        export MYSQL_PWD="$MARIADB_PASSWORD"
        exec mariadb-dump --user="$MARIADB_USER" --skip-comments --compact --no-create-info \
            --skip-triggers --order-by-primary "$MARIADB_DATABASE" \
            llx_societe llx_facture llx_facturedet llx_paiement llx_paiement_facture llx_product llx_product_stock
    ' | sha256sum
    docker compose exec -T dolibarr sha256sum /data/documents/patch-test.txt /data/custom/patch-test.txt \
        /data/documents/install.lock /data/secrets/instance-id
}
fingerprint > "$RUNNER_TEMP/before.sha256"
docker compose build --pull dolibarr
docker compose up -d --no-deps --wait --wait-timeout 300 dolibarr
test "$(docker compose ps -q mariadb)" = "$db_container"
docker compose exec -T dolibarr sh -ec '
    set -- /data/backups/upgrade-24.0.2-to-24.0.3.*.tar.gz
    test "$#" = 1 && test -s "$1"
    test "$(tar -xOzf "$1" dolibarr-version.txt)" = 24.0.2
    test ! -e /data/backups/upgrade-24.0.2-to-24.0.3.in-progress
'
docker compose exec -T dolibarr php -r '
    define("NOLOGIN", 1); define("NOREQUIREMENU", 1); require "/var/www/html/main.inc.php";
    if (DOL_VERSION !== getenv("DOLI_VERSION") || getDolGlobalString("MAIN_VERSION_LAST_UPGRADE") !== DOL_VERSION) exit(1);
    echo "patch-upgrade-verified";

' | grep -qx patch-upgrade-verified
fingerprint > "$RUNNER_TEMP/after.sha256"
cmp "$RUNNER_TEMP/before.sha256" "$RUNNER_TEMP/after.sha256"
echo 'PASS: existing customer, invoice, product, stock and payment records, documents, custom data, lock and instance key preserved.'
