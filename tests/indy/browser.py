"""Real Dolibarr UI checks. Run only against the disposable Compose fixture."""
import json
import os
from pathlib import Path
from playwright.sync_api import sync_playwright

base = os.environ.get('DOLIBARR_TEST_URL', 'http://127.0.0.1:8080')
password = os.environ['DOLIBARR_TEST_PASSWORD']
artifacts = Path(os.environ.get('INDY_ARTIFACTS', 'artifacts'))
artifacts.mkdir(parents=True, exist_ok=True)

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE'))
    context = browser.new_context(viewport={'width': 1440, 'height': 1000}, locale='fr-FR')
    page = context.new_page()
    failures = []
    page.on('response', lambda response: failures.append(f'{response.status} {response.url}')
            if response.status >= 400 and '/theme/indy/' in response.url else None)
    page.goto(base + '/', wait_until='networkidle')
    assert page.locator('input#username').is_visible()
    assert page.locator('link[href*="/theme/indy/style.css.php"]').count() > 0
    assert page.locator('body').evaluate("e => getComputedStyle(e).getPropertyValue('--indy-primary').trim()") == '#6942a4'
    page.evaluate('() => document.fonts.ready')
    assert page.evaluate('() => document.fonts.check(\'14px "Indy Inter"\')'), 'Bundled font did not load'
    page.screenshot(path=str(artifacts / 'login-desktop.png'), full_page=True)
    page.locator('#username').fill('admin')
    page.locator('#password').fill(password)
    page.locator('input[type="submit"]').first.click()
    page.wait_for_load_state('networkidle')
    if not page.locator('#id-right').is_visible():
        page.screenshot(path=str(artifacts / 'login-result.png'), full_page=True)
        raise AssertionError('Login failed: ' + page.locator('body').inner_text()[:1800])

    routes = {
        'dashboard': '/',
        'clients': '/societe/list.php?mainmenu=companies',
        'invoices': '/compta/facture/list.php?mainmenu=billing',
        'invoice-card': '/compta/facture/card.php?facid=1',
        'new-client': '/societe/card.php?action=create&mainmenu=companies',
        'theme-settings': '/admin/ihm.php?mode=template',
    }
    for name, route in routes.items():
        response = page.goto(base + route, wait_until='networkidle')
        assert response.status == 200, (name, response.status)
        assert page.locator('#id-right').is_visible(), name
        text = page.locator('body').inner_text()
        assert 'Fatal error' not in text and 'Parse error' not in text, name
        if name == 'clients':
            assert 'Maison Aubépine' in text
        if name == 'invoices':
            assert 'FA2609-0001' in text
        if name == 'theme-settings':
            assert page.locator('input[value="indy"]').count() > 0
        page.screenshot(path=str(artifacts / f'{name}-desktop.png'), full_page=True)
        assert page.evaluate('() => document.documentElement.scrollWidth <= innerWidth'), f'{name}: desktop overflow'

    # Retain native mobile navigation and test horizontal list scrolling.
    page.set_viewport_size({'width': 390, 'height': 844})
    for name in ('dashboard', 'invoices', 'new-client'):
        page.goto(base + routes[name], wait_until='networkidle')
        assert page.locator('#id-right').is_visible(), name
        page.screenshot(path=str(artifacts / f'{name}-mobile.png'), full_page=True)
    page.emulate_media(reduced_motion='reduce')
    assert page.locator('body').evaluate('e => getComputedStyle(e).transitionDuration') in ('1e-05s', '0.00001s')
    page.emulate_media(media='print')
    page.wait_for_function("['rgb(255, 255, 255)', 'rgba(0, 0, 0, 0)'].includes(getComputedStyle(document.body).backgroundColor)", timeout=5000)
    print_background = page.locator('body').evaluate('e => getComputedStyle(e).backgroundColor')
    assert print_background in ('rgb(255, 255, 255)', 'rgba(0, 0, 0, 0)'), print_background
    page.emulate_media(media='screen')
    context.clear_cookies()
    page.goto(base + '/', wait_until='networkidle')
    assert page.locator('#username').is_visible()
    assert page.locator('body').evaluate('e => e.scrollWidth <= innerWidth'), 'Login overflows mobile viewport'
    page.screenshot(path=str(artifacts / 'login-mobile.png'), full_page=True)
    assert not failures, json.dumps(failures)
    browser.close()
print('PASS: native theme, login, six screens, mobile, print and all requested theme assets.')
