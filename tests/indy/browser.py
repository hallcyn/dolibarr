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
    assert page.locator('body').evaluate("e => getComputedStyle(e).getPropertyValue('--indy-primary').trim()") == '#ff5489'
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

    rail = page.locator('#id-top').bounding_box()
    assert rail['x'] == 0 and rail['width'] == 108, rail
    assert page.locator('#id-top').evaluate('e => getComputedStyle(e).position') == 'fixed'
    page.locator('#topmenu-login-dropdown > a.dropdown-toggle').click()
    account = page.locator('#topmenu-login-dropdown .dropdown-menu')
    assert account.is_visible(), 'Account menu no longer opens from the vertical rail'
    bounds = account.bounding_box()
    assert bounds['x'] >= rail['width'] and bounds['x'] + bounds['width'] <= 1440, bounds
    assert bounds['y'] >= 0 and bounds['y'] + bounds['height'] <= 1000, bounds
    page.locator('#topmenu-login-dropdown > a.dropdown-toggle').click()

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
            create = page.locator('.table-fiche-title a.btnTitlePlus').first
            assert create.is_visible()
            assert create.evaluate('e => getComputedStyle(e).backgroundColor') == 'rgb(255, 84, 137)'
            assert create.evaluate('e => getComputedStyle(e).borderRadius') == '999px'
            assert page.locator('#id-left').bounding_box()['height'] < 140, 'Context menu unexpectedly stacked'
        if name == 'theme-settings':
            assert page.locator('input[value="indy"]').count() > 0
        page.screenshot(path=str(artifacts / f'{name}-desktop.png'), full_page=True)
        assert page.evaluate('() => document.documentElement.scrollWidth <= innerWidth'), f'{name}: desktop overflow'

    # Exercise real primary menu links and a short window, where the rail scrolls.
    page.locator('#id-top a[href*="mainmenu=companies"]').first.click()
    page.wait_for_load_state('networkidle')
    assert page.locator('#id-right').is_visible()
    assert 'mainmenu=companies' in page.url
    page.set_viewport_size({'width': 1280, 'height': 720})
    page.goto(base + routes['invoices'], wait_until='networkidle')
    page.locator('#id-top a[href*="mainmenu=tools"]').first.scroll_into_view_if_needed()
    assert page.locator('#id-top a[href*="mainmenu=tools"]').first.is_visible()
    page.screenshot(path=str(artifacts / 'invoices-compact.png'), full_page=True)

    # Retain native mobile navigation and test horizontal list scrolling.
    page.set_viewport_size({'width': 390, 'height': 844})
    for name in ('dashboard', 'invoices', 'new-client'):
        page.goto(base + routes[name], wait_until='networkidle')
        assert page.locator('#id-right').is_visible(), name
        assert page.evaluate('() => document.documentElement.scrollWidth <= innerWidth'), f'{name}: mobile overflow'
        if name == 'new-client':
            country = page.locator('#select2-selectcountry_id-container')
            assert country.is_visible() and country.bounding_box()['width'] > 200, 'Mobile country field is too narrow'
        page.screenshot(path=str(artifacts / f'{name}-mobile.png'), full_page=True)
    toggle = page.locator('#mainmenutd_menu a').first
    assert toggle.is_visible(), 'Native mobile menu toggle is missing'
    toggle.click()
    assert page.locator('.side-nav').is_visible(), 'Native mobile drawer did not open'
    page.screenshot(path=str(artifacts / 'navigation-mobile.png'), full_page=True)
    toggle.click()
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
print('PASS: cream rail, account dropdown, pink actions, native links, six screens, mobile drawer, print and theme assets.')
