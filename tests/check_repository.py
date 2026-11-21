#!/usr/bin/env python3
"""Check references and generated documentation before any Docker execution."""
import json
import hashlib
import re
import subprocess
from pathlib import Path
import yaml

root = Path(__file__).resolve().parent.parent
spec = json.loads((root / 'railway/template-spec.json').read_text())
compose = yaml.safe_load((root / 'compose.yaml').read_text())
dockerfile = (root / 'Dockerfile').read_text()
version = re.search(r'FROM dolibarr/dolibarr:([\d.]+)', dockerfile).group(1)
assert f'DOLI_VERSION={version}' in dockerfile
assert 'COPY theme/ /opt/indy-theme/' in dockerfile
assert 'bash /opt/indy-theme/install.sh /var/www/html' in dockerfile
assert 'COPY modules/indyinvoice/ /var/www/html/indyinvoice/' in dockerfile
assert 'fonts/indyinter*' in dockerfile
assert (root / 'modules/indyinvoice/core/modules/facture/doc/pdf_indy.modules.php').is_file()
theme = json.loads((root / 'theme/UPSTREAM.json').read_text())
assert re.fullmatch(r'[0-9a-f]{40}', theme['commit'])
assert theme['repository'] == 'hallcyn/dolibarr-indy-theme'
for path, checksum in theme['sha256'].items():
    assert hashlib.sha256((root / path).read_bytes()).hexdigest() == checksum, f'Theme snapshot changed: {path}'
assert json.loads((root / 'theme/indy/version.json').read_text())['version'] == theme['version']
assert spec['services']['MariaDB']['source']['image'] == compose['services']['mariadb']['image']
assert '--innodb-snapshot-isolation=OFF' in compose['services']['mariadb']['command']
assert '--innodb-snapshot-isolation=OFF' in spec['services']['MariaDB']['settings']['startCommand']

assert spec['services']['Dolibarr']['volume']['mountPath'] == '/data'
assert spec['services']['MariaDB']['volume']['mountPath'] == '/var/lib/mysql'
assert spec['services']['Dolibarr']['settings']['healthcheckPath'] == '/railway-health.php'
for service in spec['services'].values():
    for name, value in service['variables'].items():
        assert value['description'], name
for name in ('DOLI_ADMIN_PASSWORD', 'DOLI_INSTANCE_UNIQUE_ID', 'DOLI_CRON_KEY'):
    assert 'secret(' in spec['services']['Dolibarr']['variables'][name]['value']
assert spec['services']['Dolibarr']['variables']['DOLI_DB_PASSWORD']['value'] == '${{MariaDB.MARIADB_PASSWORD}}'
for document in [root / 'README.md', *root.glob('docs/*.md')]:
    for link in re.findall(r'\]\(([^)]+)\)', document.read_text()):
        if link.startswith(('https://', 'http://', '#')) or '${' in link:
            continue
        assert (document.parent / link.split('#')[0]).exists(), (document, link)
subprocess.run(['python3', 'scripts/render-template.py', '--check'], cwd=root, check=True)
for script in [*root.glob('docker/*.sh'), *root.glob('scripts/*.sh'), root / 'theme/install.sh']:
    subprocess.run(['bash', '-n', str(script)], check=True)
print('PASS: versions, storage paths, references, generated documentation, local links and shell syntax.')
