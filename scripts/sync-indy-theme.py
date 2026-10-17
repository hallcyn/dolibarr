#!/usr/bin/env python3
"""Import reviewed, immutable theme sources from the canonical public repository."""
import argparse
import hashlib
import json
import re
import subprocess
import tempfile
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--ref', required=True, help='Full reviewed upstream commit SHA')
args = parser.parse_args()
if not re.fullmatch(r'[0-9a-f]{40}', args.ref):
    parser.error('--ref must be a full lowercase 40-character commit SHA')
root = Path(__file__).resolve().parent.parent
mapping = {
    'scripts/install.sh': 'theme/install.sh',
    'LICENSE': 'theme/LICENSE',
    'tests/browser.py': 'tests/indy/browser.py',
    'tests/seed.php': 'tests/indy/seed.php',
}
for name in ('indy.css', 'style.css.php', 'theme_descriptor.php', 'theme_vars.inc.php', 'thumb.png', 'version.json'):
    mapping[f'theme/indy/{name}'] = f'theme/indy/{name}'
for name in ('inter-latin.woff2', 'OFL.txt'):
    mapping[f'theme/indy/fonts/{name}'] = f'theme/indy/fonts/{name}'
files = {}
with tempfile.TemporaryDirectory(prefix='dolibarr-indy-') as directory:
    def git(*arguments):
        return subprocess.check_output(['git', '-C', directory, *arguments], timeout=90)
    git('init', '--quiet')
    git('remote', 'add', 'origin', 'https://github.com/hallcyn/dolibarr-indy-theme.git')
    git('fetch', '--quiet', '--depth=1', 'origin', args.ref)
    if git('rev-parse', 'FETCH_HEAD').decode().strip() != args.ref:
        raise RuntimeError('Fetched commit does not match the requested commit')
    for source, target in mapping.items():
        content = git('show', f'{args.ref}:{source}')
        if len(content) > 2 * 1024 * 1024:
            raise RuntimeError(f'Unexpectedly large file: {source}')
        files[target] = content
version = json.loads(files['theme/indy/version.json'])['version']
# Download and validate everything before replacing any local file.
for target, content in files.items():
    path = root / target
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(content)
metadata = {'repository': 'hallcyn/dolibarr-indy-theme', 'commit': args.ref, 'version': version,
            'sha256': {target: hashlib.sha256(content).hexdigest() for target, content in sorted(files.items())}}
(root / 'theme/UPSTREAM.json').write_text(json.dumps(metadata, indent=2) + '\n')
print(f'Imported Indy {version} from {args.ref}; review the diff and run the repository checks.')
