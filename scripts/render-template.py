#!/usr/bin/env python3
"""Render copy/paste blocks from the documented template specification."""
import json
from pathlib import Path

root = Path(__file__).resolve().parent.parent
spec = json.loads((root / 'railway/template-spec.json').read_text())
sections = ['# Variables du template Railway', '',
    'Ces blocs sont destinés à **l’éditeur de template**, pas au terminal, à Docker Compose ou aux variables d’un projet déjà créé.', '',
    '`secret(...)` est évalué lors du déploiement d’un template. Pour créer un projet manuellement, saisir de vrais secrets aléatoires.', '',
    'Les deux services doivent porter exactement les noms `MariaDB` et `Dolibarr`.', '']
for name, service in spec['services'].items():
    sections += [f'## {name}', '', '### Raw Editor', '', '```dotenv']
    sections += [f'{key}={entry["value"]}' for key, entry in service['variables'].items()]
    sections += ['```', '', '### Descriptions à renseigner dans le template', '',
                 '| Variable | Description |', '| --- | --- |']
    sections += [f'| `{key}` | {entry["description"]} |' for key, entry in service['variables'].items()]
    sections += ['']
output = '\n'.join(sections).rstrip() + '\n'
target = root / 'docs/VARIABLES.md'
if '--check' in __import__('sys').argv:
    if not target.exists() or target.read_text() != output:
        raise SystemExit('Run python3 scripts/render-template.py and commit docs/VARIABLES.md.')
else:
    target.write_text(output)
