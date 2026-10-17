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
    assert page.locator('body').evaluate("e => getComputedStyle(e).getPropertyValue('--indy-primary').trim()") == '#d82b68'
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
        'product-space': '/product/index.php?mainmenu=products&leftmenu=product',
        'products': '/product/list.php?mainmenu=products&type=0',
        'product-card': '/product/card.php?id=1&mainmenu=products',
        'product-price': '/product/price.php?id=1&mainmenu=products',
        'product-stock': '/product/stock/product.php?id=1&mainmenu=products',
        'new-product': '/product/card.php?action=create&type=0&mainmenu=products',
        'stocks': '/product/reassort.php?type=0&mainmenu=products',
        'warehouses': '/product/stock/list.php?mainmenu=products',
        'warehouse-card': '/product/stock/card.php?id=1&mainmenu=products',
        'new-warehouse': '/product/stock/card.php?action=create&mainmenu=products',
        'stock-movements': '/product/stock/movement_list.php?mainmenu=products',
        'stock-transfer': '/product/stock/massstockmove.php?mainmenu=products',
        'stock-at-date': '/product/stock/stockatdate.php?mainmenu=products',
        'inventories': '/product/inventory/list.php?mainmenu=products',
        'shipments': '/expedition/list.php?mainmenu=products',
        'orders': '/commande/list.php?mainmenu=commercial',
        'order-lines': '/commande/list_det.php?mainmenu=commercial',
        'order-card': '/commande/card.php?id=1&mainmenu=commercial',
        'new-order': '/commande/card.php?action=create&mainmenu=commercial',
        'new-invoice': '/compta/facture/card.php?action=create&mainmenu=billing',
        'client-card': '/societe/card.php?socid=1&mainmenu=companies',
        'client-contacts': '/societe/contact.php?socid=1&mainmenu=companies',
        'client-notes': '/societe/note.php?id=1&mainmenu=companies',
        'contacts': '/contact/list.php?mainmenu=companies',
        'new-contact': '/contact/card.php?action=create&mainmenu=companies',
        'banks': '/compta/bank/list.php?mainmenu=bank',
        'bank-entries': '/compta/bank/bankentries_list.php?mainmenu=bank',
        'new-bank': '/compta/bank/card.php?action=create&mainmenu=bank',
        'bank-transfer': '/compta/bank/transfer.php?mainmenu=bank',
        'payments': '/compta/paiement/list.php?mainmenu=billing',
        'configuration': '/admin/index.php?mainmenu=home&leftmenu=setup',
        'company-settings': '/admin/company.php?mainmenu=home&leftmenu=setup',
        'modules': '/admin/modules.php?mainmenu=home&leftmenu=setup',
        'widgets': '/admin/boxes.php?mainmenu=home&leftmenu=setup',
        'menus': '/admin/menus.php?mainmenu=home&leftmenu=setup',
        'users': '/user/list.php?mainmenu=home&leftmenu=users',
        'groups': '/user/group/list.php?mainmenu=home&leftmenu=users',
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
            assert create.evaluate('e => getComputedStyle(e).backgroundColor') == 'rgb(216, 43, 104)'
            assert create.evaluate('e => getComputedStyle(e).borderRadius') == '999px'
            sidebar = page.locator('#id-container > .side-nav').bounding_box()
            assert sidebar['width'] == 224 and sidebar['height'] == 1000, sidebar
            assert page.locator('#id-right').bounding_box()['x'] >= sidebar['x'] + sidebar['width'], 'Context menu overlaps workspace'
        if name == 'theme-settings':
            assert page.locator('input[value="indy"]').count() > 0
        # Regression checks for the bugs observed on the production interface.
        for field in page.locator('#id-right input[name^="search_"][type="text"]').all():
            if field.is_visible():
                assert field.bounding_box()['height'] >= 36, (name, 'Filter is too small')
                assert field.evaluate('e => getComputedStyle(e).borderTopColor !== getComputedStyle(e).backgroundColor'), (name, 'Invisible filter border')
        for tab in page.locator('div.tabs a.tab').all():
            if tab.is_visible():
                assert 40 <= tab.bounding_box()['height'] <= 48, (name, 'Tab padded twice')
                assert tab.evaluate('e => getComputedStyle(e.parentElement).padding') == '0px', (name, 'Tab wrapper has duplicate padding')
        for action in page.locator('.tabsAction a.butAction').all():
            if action.is_visible():
                assert action.evaluate('e => getComputedStyle(e).color !== getComputedStyle(e).backgroundColor'), (name, 'Action label is invisible')
        for placeholder in page.locator('.select2-selection__rendered .placeholder').all():
            if placeholder.is_visible():
                assert placeholder.evaluate('e => getComputedStyle(e).opacity') == '1', (name, 'Placeholder is faded twice')
        page.screenshot(path=str(artifacts / f'{name}-desktop.png'), full_page=name != 'modules')
        assert page.evaluate('() => document.documentElement.scrollWidth <= innerWidth'), f'{name}: desktop overflow'
        print(f'PASS page: {name}', flush=True)

    # The long configuration column scrolls independently and every native link
    # has a real, clickable rectangle; the workspace must not cover those links.
    page.set_viewport_size({'width': 1280, 'height': 720})
    page.goto(base + routes['configuration'], wait_until='networkidle')
    setup_link = page.locator('#id-left a[href*="/admin/const.php"]').first
    setup_link.scroll_into_view_if_needed()
    assert setup_link.is_visible() and setup_link.bounding_box()['width'] > 30
    assert page.locator('#id-container > .side-nav').evaluate('e => e.scrollHeight > e.clientHeight'), 'Expected a scrollable long menu'
    page.goto(base + routes['new-product'], wait_until='networkidle')
    page.locator('#select2-statut-container').click()
    assert page.locator('.select2-dropdown').is_visible(), 'Product selector does not open'
    page.keyboard.press('Escape')
    page.goto(base + routes['clients'], wait_until='networkidle')
    page.locator('input[name="search_nom"]').fill('Maison')
    page.locator('button[name="button_search_x"]').click()
    page.wait_for_load_state('networkidle')
    assert 'Maison Aubépine' in page.locator('table.liste').inner_text()
    assert 'Studio Horizon' not in page.locator('table.liste').inner_text()
    page.locator('button[name="button_removefilter_x"]').click()
    page.wait_for_load_state('networkidle')
    assert 'Studio Horizon' in page.locator('table.liste').inner_text(), 'Filter reset failed'

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

    for width in (1024, 768):
        page.set_viewport_size({'width': width, 'height': 900})
        for name in ('invoices', 'new-product', 'new-client', 'product-card', 'stock-movements', 'configuration'):
            page.goto(base + routes[name], wait_until='networkidle')
            assert page.evaluate('() => document.documentElement.scrollWidth <= innerWidth'), f'{name}: overflow at {width}px'
        page.screenshot(path=str(artifacts / f'configuration-tablet-{width}.png'), full_page=True)

    # Retain native mobile navigation and test horizontal list scrolling.
    page.set_viewport_size({'width': 390, 'height': 844})
    for name in ('dashboard', 'invoices', 'new-client', 'new-product', 'products', 'product-card', 'product-stock', 'new-order', 'new-invoice', 'new-contact', 'new-bank', 'stock-movements', 'configuration'):
        page.goto(base + routes[name], wait_until='networkidle')
        assert page.locator('#id-right').is_visible(), name
        assert page.evaluate('() => document.documentElement.scrollWidth <= innerWidth'), f'{name}: mobile overflow'
        if name in ('new-product', 'new-client'):
            assert page.locator('.tabBar').evaluate('''e => [...e.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]),textarea,.select2-selection')].every(x => {
                const r = x.getBoundingClientRect(), row = x.closest('tr');
                if (!row || !r.width || !r.height) return true;
                const p = row.getBoundingClientRect();
                return r.top >= p.top - 1 && r.bottom <= p.bottom + 1;
            })'''), f'{name}: stacked controls overlap another row'
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
print(f'PASS: {len(routes)} desktop routes, 12 tablet checks, 13 mobile routes, visible filters, single-layer tabs, contextual menu, dropdowns, native links, print and theme assets.')
