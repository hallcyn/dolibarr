#!/usr/bin/env python3
"""Monitor published stable images; optionally maintain GitHub update issues.

Uses only Python's standard library. Never updates code or Railway services.
"""
import argparse
from dataclasses import dataclass
from datetime import date
import json
import os
from pathlib import Path
import re
import time
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlparse
from urllib.request import Request, urlopen

HUB = 'https://hub.docker.com/v2/repositories/'
MARIADB = 'https://downloads.mariadb.org/rest-api/mariadb/'
GITHUB = 'https://api.github.com'


def version(value, parts=3):
    """Only numeric stable tags: no rc, beta, develop or variant suffixes."""
    if not re.fullmatch(r'\d+' + r'\.\d+' * (parts - 1), value):
        raise ValueError(f'Not a stable {parts}-part version: {value!r}')
    return tuple(map(int, value.split('.')))


class API:
    def __init__(self, token=None):
        self.token = token

    def request(self, url, method='GET', data=None):
        headers = {'User-Agent': 'hallcyn-dolibarr-version-watch', 'Accept': 'application/json'}
        # Authenticate GitHub calls only; Docker Hub and MariaDB use public APIs.
        if urlparse(url).hostname == 'api.github.com':
            headers['Accept'] = 'application/vnd.github+json'
            headers['X-GitHub-Api-Version'] = '2022-11-28'
            if self.token:
                headers['Authorization'] = f'Bearer {self.token}'
        body = None if data is None else json.dumps(data).encode()
        if body is not None:
            headers['Content-Type'] = 'application/json'
        for attempt in range(3):
            try:
                with urlopen(Request(url, data=body, headers=headers, method=method), timeout=30) as response:
                    return json.load(response)
            except HTTPError as error:
                # Never retry POST: a lost response must not create two issues.
                if method != 'GET' or error.code not in (429, 500, 502, 503, 504) or attempt == 2:
                    raise
            except (URLError, TimeoutError):
                if method != 'GET' or attempt == 2:
                    raise
            time.sleep(2 ** attempt)

    def docker_versions(self, repository, prefix=None):
        query = {'page_size': 100}
        if prefix:
            query['name'] = prefix
        base = HUB + repository + '/tags'
        url = base + '?' + urlencode(query)
        result = []
        for _ in range(20):
            page = self.request(url)
            for tag in page['results']:
                name = tag['name']
                if not re.fullmatch(r'\d+\.\d+\.\d+', name):
                    continue
                images = tag.get('images', [])
                if tag.get('tag_status', 'active') != 'active':
                    continue
                if not any(i.get('os') == 'linux' and i.get('architecture') == 'amd64'
                           and i.get('status', 'active') == 'active' for i in images):
                    continue
                result.append(name)
            url = page.get('next')
            if not url:
                if not result:
                    raise ValueError(f'No stable Linux amd64 images found for {repository}')
                return sorted(set(result), key=version)
            if not url.startswith(base + '?') and not url.startswith(base + '/?'):
                raise ValueError('Unexpected Docker Hub pagination URL')
        raise ValueError('Docker Hub pagination limit reached; refusing an incomplete result')


@dataclass(frozen=True)
class Report:
    key: str
    name: str
    current: str
    latest: str
    needed: bool
    detail: str
    source: str

    @property
    def marker(self):
        return f'<!-- version-watch:{self.key} -->'

    @property
    def target_marker(self):
        return f'<!-- version-watch-target:{self.latest} -->'

    @property
    def title(self):
        return f'[Mise à jour] {self.name} : {self.current} → {self.latest}'

    @property
    def body(self):
        return f'''{self.marker}
{self.target_marker}
## Mise à jour à examiner

| Composant | Référence du dépôt | Version disponible |
| --- | --- | --- |
| {self.name} | `{self.current}` | `{self.latest}` |

{self.detail}

Source : {self.source}

## Vérifications avant mise à jour

- [ ] Lire les notes de version et vérifier la compatibilité Dolibarr / MariaDB / modules.
- [ ] Sauvegarder les deux volumes et la clé d’instance ; tester la restauration.
- [ ] Mettre à jour les références du dépôt et la documentation sur une branche de travail.
- [ ] Tester les migrations et attendre la réussite de la CI Docker.
- [ ] Mettre à jour le template Railway si sa source MariaDB change, puis déployer explicitement.

Voir [la procédure de maintenance](https://github.com/hallcyn/dolibarr/blob/main/docs/OPERATIONS.md).

Cette issue est gérée par la CI : son titre et son corps peuvent être actualisés.
Conserver les échanges dans les commentaires. Fermer comme « not planned » ignore
cette version précise ; une version ultérieure pourra déclencher une nouvelle alerte.
La CI ne modifie ni les versions ni les services Railway.
'''


def collect_reports(root, api, today=None):
    today = today or date.today()
    dockerfile = (root / 'Dockerfile').read_text()
    match = re.search(r'^FROM dolibarr/dolibarr:(\d+\.\d+\.\d+)(?:\s|$)', dockerfile, re.M)
    if not match:
        raise ValueError('Cannot read the pinned Dolibarr version from Dockerfile')
    current_doli = match.group(1)
    if f'DOLI_VERSION={current_doli}' not in dockerfile:
        raise ValueError('DOLI_VERSION and the Dolibarr image do not match')
    spec = json.loads((root / 'railway/template-spec.json').read_text())
    image = spec['services']['MariaDB']['source']['image']
    match = re.fullmatch(r'mariadb:(\d+\.\d+(?:\.\d+)?)', image)
    if not match:
        raise ValueError('MariaDB must use a numeric branch or patch tag')
    current_db = match.group(1)
    branch = '.'.join(current_db.split('.')[:2])
    compose_image = re.search(r'^\s+image:\s*(\S+)', (root / 'compose.yaml').read_text(), re.M)
    if not compose_image or compose_image.group(1) != image:
        raise ValueError('Compose and the Railway reference use different MariaDB images')

    latest_doli = max(api.docker_versions('dolibarr/dolibarr'), key=version)
    releases = api.request(MARIADB)['major_releases']
    # Stay with maintained LTS branches, even if a newer rolling / RC exists.
    supported = [r for r in releases if r['release_status'] == 'Stable'
                 and r.get('release_support_type') == 'Long Term Support'
                 and r.get('release_eol_date')
                 and date.fromisoformat(r['release_eol_date']) > today]
    if not supported:
        raise ValueError('MariaDB API returned no maintained stable LTS branches')
    lts = max(supported, key=lambda r: version(r['release_id'], 2))
    target_branch = lts['release_id']
    images = api.docker_versions('library/mariadb', target_branch + '.')
    images = [v for v in images if v.startswith(target_branch + '.')]
    if not images:
        raise ValueError(f'MariaDB LTS {target_branch} has no published stable image yet')
    latest_db = max(images, key=version)
    current_release = next((r for r in releases if r['release_id'] == branch), None)
    if current_release is None:
        raise ValueError(f'MariaDB API does not describe the configured branch {branch}')
    eol = current_release.get('release_eol_date')
    expired = bool(eol and date.fromisoformat(eol) <= today)
    newer_db = (version(latest_db, 3) > version(current_db, 3) if current_db.count('.') == 2
                else version(target_branch, 2) > version(branch, 2))
    detail = ('Nouvelle branche LTS stable à évaluer. Une branche plus récente ne signifie pas '
              'que la branche actuelle est incompatible ou vulnérable. '
              if version(target_branch, 2) > version(branch, 2) else '')
    if eol:
        detail += f'Fin de support annoncée pour `{branch}` : **{eol}**. '
    if expired:
        detail += '**La branche configurée est en fin de support.** '
    if current_db.count('.') == 1:
        detail += (f'Le tag `{current_db}` est flottant : les correctifs de cette branche sont '
                   'récupérés au prochain pull de l’image, pas par cette CI. '
                   'La version réellement exécutée sur Railway n’est pas inspectée.')

    # Collect every source successfully before creating, updating or closing issues.
    return [Report('dolibarr', 'Dolibarr', current_doli, latest_doli,
                   version(latest_doli) > version(current_doli),
                   'Version stable publiée dans l’image officielle, sans tags develop, RC ou variantes PHP.',
                   'https://hub.docker.com/r/dolibarr/dolibarr/tags'),
            Report('mariadb', 'MariaDB', current_db, latest_db, newer_db or expired,
                   detail, 'https://mariadb.org/download/ — https://hub.docker.com/_/mariadb/tags')]


def sync_issues(api, repository, reports):
    base = f'{GITHUB}/repos/{repository}/issues'
    issues = []
    for page in range(1, 21):
        batch = api.request(f'{base}?state=all&per_page=100&page={page}')
        issues.extend(i for i in batch if 'pull_request' not in i
                      and i.get('user', {}).get('login') == 'github-actions[bot]')
        if len(batch) < 100:
            break
    else:
        raise ValueError('Issue pagination limit reached; refusing to create possible duplicates')
    for report in reports:
        matches = [i for i in issues if report.marker in (i.get('body') or '')]
        opened = [i for i in matches if i['state'] == 'open']
        if not report.needed:
            for issue in opened:
                api.request(f'{base}/{issue["number"]}', 'PATCH',
                            {'state': 'closed', 'state_reason': 'completed'})
            continue
        payload = {'title': report.title, 'body': report.body}
        if opened:
            issue = opened[0]
            if issue['title'] != report.title or issue['body'] != report.body:
                api.request(f'{base}/{issue["number"]}', 'PATCH', payload)
            continue
        previous = next((i for i in matches if report.target_marker in (i.get('body') or '')), None)
        if previous:
            if previous.get('state_reason') != 'not_planned':
                api.request(f'{base}/{previous["number"]}', 'PATCH', {**payload, 'state': 'open'})
            continue
        api.request(base, 'POST', payload)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--write-issues', action='store_true', help='Maintain issues using GITHUB_TOKEN')
    args = parser.parse_args()
    api = API(os.environ.get('GITHUB_TOKEN'))
    reports = collect_reports(Path(__file__).resolve().parent.parent, api)
    summary = '# Versions amont\n\n| Composant | Dépôt | Stable / LTS disponible | Action |\n| --- | --- | --- | --- |\n'
    for report in reports:
        summary += f'| {report.name} | {report.current} | {report.latest} | {"À examiner" if report.needed else "À jour"} |\n'
    summary += '\nAucune mise à jour ni aucun déploiement automatique.\n'
    print(summary)
    if os.environ.get('GITHUB_STEP_SUMMARY'):
        with open(os.environ['GITHUB_STEP_SUMMARY'], 'a') as stream:
            stream.write(summary)
    if args.write_issues:
        repository = os.environ.get('GITHUB_REPOSITORY', '')
        if not re.fullmatch(r'[\w.-]+/[\w.-]+', repository) or not api.token:
            raise ValueError('GITHUB_REPOSITORY and GITHUB_TOKEN are required to write issues')
        sync_issues(api, repository, reports)


if __name__ == '__main__':
    main()
