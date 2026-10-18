#!/usr/bin/env python3
"""Exercise the actual image, database, durable state and recovery artefacts."""
import json
import subprocess
import time
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def compose(*args, **kwargs):
    return subprocess.run(['docker', 'compose', *args], cwd=ROOT, check=True, **kwargs)


def php(code):
    try:
        return compose('exec', '-T', 'dolibarr', 'php', '-r', code, capture_output=True, text=True).stdout
    except subprocess.CalledProcessError as error:
        raise RuntimeError(error.stderr or error.stdout or 'PHP check failed') from error


def healthy():
    deadline = time.monotonic() + 300
    unavailable_since = None
    while time.monotonic() < deadline:
        try:
            with urllib.request.urlopen('http://127.0.0.1:8080/railway-health.php', timeout=5) as reply:
                if json.load(reply) == {'status': 'ok'}:
                    return
        except urllib.error.HTTPError as error:
            if error.code == 503:
                unavailable_since = unavailable_since or time.monotonic()
                if time.monotonic() - unavailable_since > 45:
                    raise RuntimeError('Apache is running but readiness has returned 503 for 45 seconds.')
        except Exception:
            pass
        time.sleep(3)
    raise RuntimeError('Dolibarr did not become healthy in five minutes.')


healthy()
php('if (!is_readable("/var/www/html/custom/indyinvoice/core/modules/modIndyInvoice.class.php") '
    '|| !is_readable("/var/www/html/includes/tecnickcom/tcpdf/fonts/indyinter.php")) exit(1);')
with urllib.request.urlopen('http://127.0.0.1:8080/', timeout=10) as reply:
    assert 'login' in reply.read().decode().lower(), 'Missing login form'

# The image must not retain upstream anonymous volume declarations.
image = compose('images', '-q', 'dolibarr', capture_output=True, text=True).stdout.strip()
volumes = subprocess.check_output(['docker', 'image', 'inspect', image, '--format', '{{json .Config.Volumes}}'], text=True)
assert json.loads(volumes) in (None, {}), volumes

php('require "/var/www/html/conf/conf.php"; '
    'if ($dolibarr_main_db_pass !== getenv("DOLI_DB_PASSWORD")) exit(1); '
    'if ($dolibarr_main_instance_unique_id !== getenv("DOLI_INSTANCE_UNIQUE_ID")) exit(2); '
    '$db = new mysqli(getenv("DOLI_DB_HOST"), getenv("DOLI_DB_USER"), getenv("DOLI_DB_PASSWORD"), getenv("DOLI_DB_NAME")); '
    '$db->query("CREATE TABLE railway_smoke (marker VARCHAR(32))"); '
    '$statement = $db->prepare("INSERT INTO railway_smoke VALUES (?)"); '
    '$marker = "persistent"; $statement->bind_param("s", $marker); $statement->execute(); '
    'file_put_contents("/var/www/documents/railway-smoke.txt", "document"); '
    'file_put_contents("/var/www/html/custom/railway-smoke.txt", "module"); '
    'file_put_contents("/data/sessions/railway-smoke.txt", "session");')

compose('up', '-d', '--force-recreate')
healthy()
php('foreach (["documents" => "document", "custom" => "module", "sessions" => "session"] as $dir => $value) '
    '{ if (file_get_contents("/data/$dir/railway-smoke.txt") !== $value) exit(1); } '
    '$db = new mysqli(getenv("DOLI_DB_HOST"), getenv("DOLI_DB_USER"), getenv("DOLI_DB_PASSWORD"), getenv("DOLI_DB_NAME")); '
    'if ($db->query("SELECT marker FROM railway_smoke")->fetch_row()[0] !== "persistent") exit(2);')

# The archive must include actual SQL data, durable files and the permanent key.
archive = ROOT / 'backups/smoke.tar.gz'
archive.parent.mkdir(exist_ok=True)
with archive.open('wb') as output:
    compose('exec', '-T', 'dolibarr', 'dolibarr-backup', '--stdout', stdout=output)
import tarfile
with tarfile.open(archive) as backup:
    names = backup.getnames()
    for expected in ('database.sql', 'dolibarr-version.txt', 'documents/railway-smoke.txt',
                     'custom/railway-smoke.txt', 'secrets/instance-id'):
        assert expected in names, expected
    assert b'railway_smoke' in backup.extractfile('database.sql').read()
    assert not any(name.startswith('sessions/') or name.startswith('backups/') for name in names)

# A missing database must produce 503, not a false-positive deployment check.
compose('stop', 'mariadb')
try:
    urllib.request.urlopen('http://127.0.0.1:8080/railway-health.php', timeout=10)
except urllib.error.HTTPError as error:
    assert error.code == 503
    assert b'DOLI_DB_PASSWORD' not in error.read()
else:
    raise AssertionError('Healthcheck accepted an unavailable database')
compose('start', 'mariadb')
healthy()

print('PASS: installation, login page, database check, persistent SQL/files/modules/sessions, archive and 503 handling.')
