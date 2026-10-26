#!/usr/bin/env python3
"""Restore a real Dolibarr 11.4 backup into fresh target volumes, in CI only.

Never a production migration tool. Uses an isolated Compose project and removes
only that project's disposable volumes in finally, including after a failure.
"""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parent.parent
BASELINE = 'mariadb:11.4.13'

# Hash every row, including NULLs and binary values, independently of SQL order.
# The native driver returns strings; serialize preserves values without lossy
# float/JSON conversions. No database contents or credentials are printed.
SNAPSHOT = r'''
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(getenv('DOLI_DB_HOST'), getenv('DOLI_DB_USER'), getenv('DOLI_DB_PASSWORD'), getenv('DOLI_DB_NAME'));
$db->set_charset('utf8mb4');
$tables = $db->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetch_all();
$result = [];
foreach ($tables as $entry) {
    $table = str_replace('`', '``', $entry[0]);
    $rows = $db->query('SELECT * FROM `'.$table.'`', MYSQLI_USE_RESULT);
    $hashes = [];
    while ($row = $rows->fetch_row()) $hashes[] = hash('sha256', serialize($row));
    $rows->free();
    sort($hashes, SORT_STRING);
    $result[$entry[0]] = ['rows' => count($hashes), 'sha256' => hash('sha256', implode('', $hashes))];
}
ksort($result);
echo json_encode($result, JSON_THROW_ON_ERROR);
'''

CONCURRENCY = r'''
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$connect = function () { return new mysqli(getenv('DOLI_DB_HOST'), getenv('DOLI_DB_USER'), getenv('DOLI_DB_PASSWORD'), getenv('DOLI_DB_NAME')); };
$a = $connect(); $b = $connect();
if ($a->query('SELECT @@innodb_snapshot_isolation')->fetch_row()[0] !== '0') throw new RuntimeException('Expected preserved snapshot-isolation setting');
$a->query('CREATE TABLE railway_migration_concurrency (id INT PRIMARY KEY, amount DECIMAL(24,8), label VARCHAR(80)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$a->set_charset('utf8mb4');
$a->query("INSERT INTO railway_migration_concurrency VALUES (1, 123456789.12345678, 'Cuvée — été 🍇')");
$a->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$a->begin_transaction();
$a->query('SELECT * FROM railway_migration_concurrency')->fetch_all();
$b->query("INSERT INTO railway_migration_concurrency VALUES (2, 0.75000000, 'Bouteille')");
// A newer committed row must retain the previous LTS's update semantics.
$a->query('UPDATE railway_migration_concurrency SET amount = amount + 1');
$a->commit();
if ($a->affected_rows < 0 || $a->query('SELECT COUNT(*) FROM railway_migration_concurrency WHERE amount > 1')->fetch_row()[0] !== '2') throw new RuntimeException('Concurrent update failed');
$a->query('DROP TABLE railway_migration_concurrency');
echo 'PASS: preserved concurrent REPEATABLE READ updates';
'''


def healthy():
    deadline = time.monotonic() + 300
    while time.monotonic() < deadline:
        try:
            with urllib.request.urlopen('http://127.0.0.1:8080/railway-health.php', timeout=5) as response:
                if json.load(response) == {'status': 'ok'}:
                    return
        except (urllib.error.URLError, TimeoutError):
            pass
        time.sleep(3)
    raise RuntimeError('Migration fixture did not become ready')


def main():
    if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('CI') != 'true':
        raise SystemExit('Disposable GitHub Actions runner only; do not run against a business instance.')
    target = json.loads((ROOT / 'railway/template-spec.json').read_text())['services']['MariaDB']['source']['image']
    project = 'dolibarr-migration-' + os.environ['GITHUB_RUN_ID'] + '-' + os.environ['GITHUB_RUN_ATTEMPT']
    with tempfile.TemporaryDirectory(prefix='dolibarr-migration-') as scratch:
        scratch = Path(scratch)
        baseline = scratch / 'baseline.yaml'
        baseline.write_text(json.dumps({'services': {'mariadb': {'image': BASELINE}}}))
        restored = scratch / 'restored.yaml'
        restored.write_text(json.dumps({
            'services': {
                'mariadb': {'volumes': ['mariadb-restored:/var/lib/mysql']},
                'dolibarr': {'volumes': ['dolibarr-restored:/data']},
            },
            'volumes': {'mariadb-restored': {}, 'dolibarr-restored': {}},
        }))

        def compose(override, *args, **kwargs):
            return subprocess.run(['docker', 'compose', '-p', project, '-f', str(ROOT / 'compose.yaml'),
                                   '-f', str(override), *args], cwd=ROOT, check=True, **kwargs)

        def php(override, code):
            return compose(override, 'run', '--rm', '--no-deps', '-T', '--entrypoint', 'php', 'dolibarr',
                           '-r', code, capture_output=True, text=True).stdout

        try:
            compose(baseline, 'build', '--pull')
            compose(baseline, 'up', '-d', '--wait', '--wait-timeout', '300')
            healthy()
            compose(baseline, 'cp', 'tests/indy/seed.php', 'dolibarr:/tmp/migration-seed.php')
            compose(baseline, 'exec', '--user', 'www-data', '-T', 'dolibarr', 'php', '/tmp/migration-seed.php')
            print(php(baseline, CONCURRENCY), flush=True)
            # Quiesce Apache and cron, then use a CLI container with the same data.
            compose(baseline, 'stop', 'dolibarr')
            before = json.loads(php(baseline, SNAPSHOT))
            for table in ('llx_societe', 'llx_facture', 'llx_facturedet', 'llx_product', 'llx_product_stock', 'llx_commande'):
                assert before[table]['rows'] > 0, f'Missing native fixture: {table}'
            archive = scratch / 'backup.tar.gz'
            with archive.open('wb') as output:
                # Runtime symlinks belong to the original container, so recreate
                # the documents link in this disposable CLI container only.
                compose(baseline, 'run', '--rm', '--no-deps', '-T', '--entrypoint', 'bash',
                        'dolibarr', '-c', 'rm -rf /var/www/documents; ln -s /data/documents /var/www/documents; exec dolibarr-backup --stdout', stdout=output)
            compose(baseline, 'stop', 'mariadb')
            # Compose replaces mounts with the same target; original volumes stay
            # untouched. The target has freshly initialized system tables/users.
            compose(restored, 'up', '-d', '--wait', '--wait-timeout', '180', 'mariadb')
            empty = php(restored, "mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); $d=new mysqli(getenv('DOLI_DB_HOST'),getenv('DOLI_DB_USER'),getenv('DOLI_DB_PASSWORD'),getenv('DOLI_DB_NAME')); echo $d->query('SHOW TABLES')->num_rows;")
            assert empty == '0', 'Refusing to import into a nonempty target database'
            with tarfile.open(archive) as backup:
                sql = backup.extractfile('database.sql').read()
                key_hash = hashlib.sha256(backup.extractfile('secrets/instance-id').read()).hexdigest()
                files = {item.name: hashlib.sha256(backup.extractfile(item).read()).hexdigest()
                         for item in backup.getmembers() if item.isfile()
                         and item.name.startswith(('documents/', 'custom/', 'secrets/'))}
            compose(restored, 'exec', '-T', 'mariadb', 'sh', '-c',
                    'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb --user="$MARIADB_USER" "$MARIADB_DATABASE"', input=sql)
            with archive.open('rb') as source:
                compose(restored, 'run', '--rm', '--no-deps', '-T', '--entrypoint', 'tar', 'dolibarr',
                        '-xzf', '-', '-C', '/data', 'documents', 'custom', 'secrets', stdin=source)
            after = json.loads(php(restored, SNAPSHOT))
            assert before == after, 'SQL row counts or value fingerprints changed during restore'
            key_after = php(restored, "echo hash_file('sha256', '/data/secrets/instance-id');")
            assert key_hash == key_after, 'Instance key changed during restore'
            restored_files = json.loads(php(restored, r'''$files = [];
foreach (['documents', 'custom', 'secrets'] as $dir) {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/data/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($items as $item) if ($item->isFile()) $files[substr($item->getPathname(), 6)] = hash_file('sha256', $item->getPathname());
}
echo json_encode($files, JSON_THROW_ON_ERROR);'''))
            assert files == restored_files, 'Document, module or secret file changed during restore'
            compose(restored, 'exec', '-T', 'mariadb', 'sh', '-c',
                    'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb-check --user="$MARIADB_USER" --check "$MARIADB_DATABASE"', stdout=subprocess.DEVNULL)
            print(php(restored, CONCURRENCY), flush=True)
            compose(restored, 'up', '-d', '--wait', '--wait-timeout', '300', 'dolibarr')
            healthy()
            with urllib.request.urlopen('http://127.0.0.1:8080/', timeout=10) as response:
                assert 'login' in response.read().decode().lower(), 'Missing restored login page'
            # Native business writes + invoice/order PDFs after the migration.
            compose(restored, 'cp', 'tests/invoices/generate.php', 'dolibarr:/tmp/migration-invoices.php')
            compose(restored, 'exec', '--user', 'www-data', '-T', 'dolibarr', 'php', '/tmp/migration-invoices.php', '/tmp/migration-pdfs')
            compose(restored, 'up', '-d', '--force-recreate', '--wait', '--wait-timeout', '300')
            healthy()
            summary = {'baseline': BASELINE, 'target': target, 'tables_preserved': len(before),
                       'rows_preserved': sum(t['rows'] for t in before.values()),
                       'all_row_fingerprints_equal': True, 'instance_key_preserved': True,
                       'backup_file_fingerprints_equal': True,
                       'native_invoice_order_generation': 'passed', 'recreation': 'passed'}
            (ROOT / 'artifacts').mkdir(exist_ok=True)
            (ROOT / 'artifacts/mariadb-migration.json').write_text(json.dumps(summary, indent=2) + '\n')
            print('PASS: ' + json.dumps(summary), flush=True)
        finally:
            # No dumps, credentials, keys or documents are uploaded as artifacts.
            try:
                compose(restored, 'logs', '--tail=100')
            finally:
                try:
                    compose(restored, 'down', '--volumes', '--remove-orphans')
                finally:
                    # Compose down removes only mounts used by that override.
                    # Also remove the original, now-detached 11.4 fixture volumes.
                    compose(baseline, 'down', '--volumes', '--remove-orphans')


if __name__ == '__main__':
    main()
