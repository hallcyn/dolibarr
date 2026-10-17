"""Version policy and idempotent issue management, without external network calls."""
import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
from datetime import date

spec = importlib.util.spec_from_file_location('version_watch', Path(__file__).parents[1] / 'scripts/check-versions.py')
watch = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = watch
spec.loader.exec_module(watch)

TODAY = date(2026, 10, 17)


def release(branch, status='Stable', support='Long Term Support', eol='2030-01-01'):
    return {'release_id': branch, 'release_status': status,
            'release_support_type': support, 'release_eol_date': eol}


class Sources:
    def __init__(self):
        self.releases = [release('13.0', support='Rolling'), release('12.3'),
                         release('14.3', status='RC'), release('11.4')]
        self.doli = ['24.0.2', '23.0.10', '24.0.10']
        self.db = ['12.3.2', '12.3.3']

    def docker_versions(self, repository, prefix=None):
        return self.doli if repository == 'dolibarr/dolibarr' else self.db

    def request(self, url):
        assert url == watch.MARIADB
        return {'major_releases': self.releases}


class IssueAPI:
    def __init__(self, issues=()):
        self.issues = list(issues)
        self.writes = []

    def request(self, url, method='GET', data=None):
        if method == 'GET':
            return self.issues
        self.writes.append((method, data))
        if method == 'POST':
            self.issues.append({'number': len(self.issues) + 1, 'state': 'open',
                                'user': {'login': 'github-actions[bot]'}, **data})
        else:
            issue = next(i for i in self.issues if i['number'] == int(url.rsplit('/', 1)[1]))
            issue.update(data)
        return self.issues[-1]


class ReleaseSelection(unittest.TestCase):
    def reports(self, db='11.4', sources=None):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'railway').mkdir()
            (root / 'Dockerfile').write_text('FROM dolibarr/dolibarr:24.0.2 AS upstream\nENV DOLI_VERSION=24.0.2\n')
            (root / 'compose.yaml').write_text(f'services:\n  mariadb:\n    image: mariadb:{db}\n')
            (root / 'railway/template-spec.json').write_text(json.dumps({
                'services': {'MariaDB': {'source': {'image': 'mariadb:' + db}}}}))
            return watch.collect_reports(root, sources or Sources(), TODAY)

    def test_versions_are_sorted_numerically(self):
        reports = self.reports()
        self.assertEqual(reports[0].latest, '24.0.10')
        self.assertTrue(reports[0].needed)

    def test_only_maintained_stable_lts_is_selected(self):
        report = self.reports()[1]
        self.assertEqual(report.latest, '12.3.3')
        self.assertTrue(report.needed)

    def test_floating_branch_does_not_claim_patch_is_missing(self):
        self.assertFalse(self.reports(db='12.3')[1].needed)

    def test_pinned_patch_is_checked(self):
        self.assertTrue(self.reports(db='12.3.2')[1].needed)
        self.assertFalse(self.reports(db='12.3.3')[1].needed)

    def test_newer_dolibarr_pin_never_triggers_a_downgrade(self):
        source = Sources()
        source.doli = ['24.0.1']
        self.assertFalse(self.reports(sources=source)[0].needed)

    def test_missing_images_or_sources_fail_the_check(self):
        source = Sources()
        source.db = []
        with self.assertRaises(ValueError):
            self.reports(sources=source)
        source.releases = [release('11.4', eol='2020-01-01')]
        with self.assertRaises(ValueError):
            self.reports(sources=source)

    def test_eol_is_explained_in_the_issue(self):
        source = Sources()
        source.releases[-1]['release_eol_date'] = '2026-10-01'
        report = self.reports(sources=source)[1]
        self.assertTrue(report.needed)
        self.assertIn('en fin de support', report.body)

    def test_network_failure_cannot_close_an_update_issue(self):
        with patch.object(watch, 'collect_reports', side_effect=OSError('network unavailable')), \
             patch.object(watch, 'sync_issues') as sync, \
             patch.object(sys, 'argv', ['check-versions.py', '--write-issues']):
            with self.assertRaises(OSError):
                watch.main()
            sync.assert_not_called()


class DockerTags(unittest.TestCase):
    def test_prereleases_variants_and_wrong_architectures_are_ignored(self):
        def tag(name, arch='amd64', status='active'):
            return {'name': name, 'tag_status': status,
                    'images': [{'architecture': arch, 'os': 'linux', 'status': 'active'}]}
        api = watch.API()
        pages = [
            {'results': [tag('24.0.2'), tag('develop'), tag('25.0.0-rc'), tag('24.0.9-php8.2')],
             'next': watch.HUB + 'dolibarr/dolibarr/tags?page=2'},
            {'results': [tag('24.0.10'), tag('26.0.0', 'arm64'), tag('27.0.0', status='inactive')], 'next': None},
        ]
        with patch.object(api, 'request', side_effect=pages):
            self.assertEqual(api.docker_versions('dolibarr/dolibarr'), ['24.0.2', '24.0.10'])

    def test_unexpected_or_empty_registry_result_is_not_up_to_date(self):
        api = watch.API()
        with patch.object(api, 'request', return_value={'results': [], 'next': None}):
            with self.assertRaises(ValueError):
                api.docker_versions('dolibarr/dolibarr')


class IssueLifecycle(unittest.TestCase):
    def report(self, latest='24.0.3', needed=True):
        return watch.Report('dolibarr', 'Dolibarr', '24.0.2', latest, needed, 'Stable image.', 'https://hub.docker.com')

    def test_first_run_creates_one_issue_and_repeated_run_changes_nothing(self):
        api = IssueAPI()
        for _ in range(3):
            watch.sync_issues(api, 'hallcyn/dolibarr', [self.report()])
        self.assertEqual(len(api.issues), 1)
        self.assertEqual(len(api.writes), 1)

    def test_new_release_updates_the_open_issue(self):
        api = IssueAPI()
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report()])
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report('25.0.0')])
        self.assertEqual(len(api.issues), 1)
        self.assertIn('25.0.0', api.issues[0]['title'])
        self.assertEqual(api.writes[-1][0], 'PATCH')

    def test_updating_the_repository_closes_the_issue(self):
        api = IssueAPI()
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report()])
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report(needed=False)])
        self.assertEqual(api.issues[0]['state'], 'closed')
        self.assertEqual(api.issues[0]['state_reason'], 'completed')

    def test_dismissed_version_is_not_reopened_but_next_version_is_reported(self):
        api = IssueAPI()
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report()])
        api.issues[0].update(state='closed', state_reason='not_planned')
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report()])
        self.assertEqual(len(api.writes), 1)
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report('25.0.0')])
        self.assertEqual(len(api.issues), 2)

    def test_rollback_reopens_existing_completed_issue(self):
        api = IssueAPI()
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report()])
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report(needed=False)])
        watch.sync_issues(api, 'hallcyn/dolibarr', [self.report()])
        self.assertEqual(len(api.issues), 1)
        self.assertEqual(api.issues[0]['state'], 'open')

    def test_other_peoples_issues_are_not_edited(self):
        report = self.report()
        api = IssueAPI([{'number': 1, 'user': {'login': 'human'}, 'state': 'open',
                         'title': report.title, 'body': report.body}])
        watch.sync_issues(api, 'hallcyn/dolibarr', [report])
        self.assertEqual(len(api.issues), 2)
        self.assertEqual(api.writes[0][0], 'POST')


if __name__ == '__main__':
    unittest.main()
