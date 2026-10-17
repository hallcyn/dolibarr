#!/usr/bin/env python3
"""Generate local credentials once. Never overwrite an existing environment."""
from pathlib import Path
import secrets

root = Path(__file__).resolve().parent.parent
target = root / '.env'
values = (root / '.env.example').read_text()
for name in ('DOLI_ADMIN_PASSWORD', 'DOLI_INSTANCE_UNIQUE_ID', 'DOLI_CRON_KEY',
             'MARIADB_PASSWORD', 'MARIADB_ROOT_PASSWORD'):
    values = values.replace(f'{name}=\n', f'{name}={secrets.token_hex(32)}\n')
try:
    with target.open('x') as output:
        target.chmod(0o600)
        output.write(values)
except FileExistsError:
    raise SystemExit('.env already exists; it was not changed.')
print('Created .env. Open it locally for your admin password, then run docker compose up --build -d.')
