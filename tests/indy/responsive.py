"""Responsive audit and accepted-desktop comparison on disposable Dolibarr only.

The historical reference is CSS/JS from the user's accepted 1.4.1 commit.
Both renders use the same running fixture, browser, fonts and fictional data.
No production credentials or production data are used.
"""
import ast
import hashlib
import json
import os
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parent
out = Path(os.environ.get('INDY_ARTIFACTS', 'artifacts')) / 'responsive'
out.mkdir(parents=True, exist_ok=True)
base = os.environ.get('DOLIBARR_TEST_URL', 'http://127.0.0.1:8080')
assert urlsplit(base).hostname in ('127.0.0.1', 'localhost'), 'This mutating audit requires a local disposable fixture'
report, errors = [], []


def routes_from(name):
    for node in ast.walk(ast.parse((root / name).read_text())):
        if isinstance(node, ast.Assign) and any(isinstance(t, ast.Name) and t.id == 'routes' for t in node.targets):
            return ast.literal_eval(node.value)
    raise AssertionError('Route inventory missing: ' + name)


routes = {**routes_from('browser.py'), **routes_from('usability.py'),
          'indy-settings': '/indyinvoice/admin/setup.php'}
desktop_routes = ('dashboard', 'clients', 'invoices', 'new-client', 'order-card', 'product-card')
reference = json.loads((root / 'reference/desktop.json').read_text())
for name, digest in reference['sha256'].items():
    assert hashlib.sha256((root / 'reference' / name).read_bytes()).hexdigest() == digest
old_css = (root / 'reference/indy-1.4.1.css').read_text()
old_js = (root / 'reference/indy-1.4.1.js').read_text()


def settled(page):
    page.evaluate('() => document.fonts.ready')
    page.evaluate('() => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))')


def screenshot(page, name):
    settled(page)
    # Native Chart.js bitmap animation is independent of the theme. Mask only
    # its pixels; canvas position/size and the surrounding UI still compare.
    # A full-page capture temporarily changes mobile device metrics. Some
    # Chromium versions then lose touch emulation on the next navigation.
    # Keep real touch viewports for the audit; desktop references stay full.
    return page.screenshot(path=str(out / name), full_page=name.startswith('desktop-'), animations='disabled', caret='hide',
                           mask=[page.locator('canvas')] if name.startswith('desktop-') else [])


def old_style(route):
    response = route.fetch()
    css = response.text()
    marker = '/* Indy for Dolibarr'
    assert marker in css, 'Theme CSS reference insertion point missing'
    route.fulfill(response=response, body=css.split(marker, 1)[0] + old_css)


def old_script(route):
    route.fulfill(status=200, content_type='application/javascript', body=old_js)


def desktop_structure(page):
    return page.evaluate('''()=>['#id-top','#id-right','.side-nav','#topmenu-login-dropdown','#blockvmenusearch','#qty'].map(s=>{
      const e=document.querySelector(s);if(!e)return null;
      const r=e.getBoundingClientRect(),c=getComputedStyle(e);
      return {selector:s,parent:e.parentElement.tagName+'.'+e.parentElement.className+'#'+e.parentElement.id,
        id:e.id,role:e.getAttribute('role'),inert:e.inert,font:c.fontSize,padding:c.padding,
        x:s==='#qty'?null:r.x,width:s==='#qty'?null:r.width};
    })''')


with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE'))
    auth = browser.new_context(viewport={'width': 1440, 'height': 1000}, locale='fr-FR')
    assert auth.request.get(base + '/theme/indy/indy.css').text().startswith(old_css), 'Accepted desktop CSS was modified'
    assert auth.request.get(base + '/theme/indy/indy.js').text().startswith(old_js), 'Accepted desktop JS was modified'
    login = auth.new_page()
    login.goto(base + '/')
    login.locator('#username').fill('admin')
    login.locator('#password').fill(os.environ['DOLIBARR_TEST_PASSWORD'])
    login.locator('input[type=submit]').first.click()
    login.wait_for_load_state('networkidle')
    state = auth.storage_state()

    # A large touch tablet must use the responsive shell, while a computer at
    # the same width keeps the accepted desktop design.
    for width in (1280, 1440, 1920):
        before = browser.new_context(viewport={'width': width, 'height': 1000}, locale='fr-FR', storage_state=state)
        before.route('**/theme/indy/style.css.php*', old_style)
        before.route('**/theme/indy/indy.js*', old_script)
        a = before.new_page()
        for name in desktop_routes:
            before.route('**/theme/indy/style.css.php*', old_style)
            before.route('**/theme/indy/indy.js*', old_script)
            a.goto(base + routes[name], wait_until='networkidle')
            first = screenshot(a, f'desktop-{name}-{width}-accepted.png')
            # Compare the exact same native DOM. Select2 measures its columns
            # at initialization, so independent cold-font loads can differ by
            # a pixel even with identical CSS. Reload the stylesheet in place
            # and apply the new script after the historical initialization.
            before.unroute('**/theme/indy/style.css.php*')
            before.unroute('**/theme/indy/indy.js*')
            a.evaluate('''() => new Promise((resolve,reject)=>{
              const link=document.querySelector('link[href*="/theme/indy/style.css.php"]');
              link.onload=resolve;link.onerror=reject;link.href=link.href+'&indy_reference=current';
            })''')
            a.add_script_tag(url=base + '/theme/indy/indy.js?indy_reference=current')
            a.wait_for_function("!document.body.classList.contains('indy-responsive')")
            second = screenshot(a, f'desktop-{name}-{width}-current.png')
            assert first == second, (name, width, 'Accepted desktop pixels changed')
            if width == 1440:
                structure = desktop_structure(a)
                a.set_viewport_size({'width': 390, 'height': 844})
                a.locator('.indy-menu-toggle').wait_for(state='visible')
                a.locator('.indy-menu-toggle').click()
                a.set_viewport_size({'width': width, 'height': 1000})
                a.wait_for_function("!document.body.classList.contains('indy-responsive')")
                assert not a.locator('#id-right').evaluate('e => e.inert')
                # Check restored native parents, menu geometry and typography.
                # Chart.js itself may resize its canvas after an orientation
                # change, so this second check does not compare chart heights.
                assert desktop_structure(a) == structure, (name, 'Native desktop structure not restored')
                assert a.locator('.indy-r-only').count() == 0
                screenshot(a, f'desktop-{name}-{width}-restored.png')
            report.append({'kind': 'desktop-reference', 'route': name, 'width': width, 'status': 'passed'})
        before.close()
        print('PASS accepted desktop pixels and resize restoration:', width, flush=True)

    profiles = [('small-phone', 'Pixel 7', 320, 740), ('phone', 'iPhone 13', 390, 844),
                ('landscape', 'iPhone 13', 844, 390), ('tablet', 'iPad Mini', 768, 1024),
                ('tablet-landscape', 'iPad Mini', 1024, 768), ('large-tablet', 'iPad Pro 11', 1366, 1024)]
    for profile, device, width, height in profiles:
        unsigned = browser.new_context(**{**p.devices[device], 'viewport': {'width': width, 'height': height}, 'device_scale_factor': 1, 'locale': 'fr-FR'})
        start = unsigned.new_page()
        start.goto(base + '/', wait_until='networkidle')
        for field in start.locator('#username,#password').all():
            assert field.bounding_box()['height'] >= 44
            assert field.evaluate('e=>parseFloat(getComputedStyle(e).fontSize)') >= 16
        assert start.evaluate('document.documentElement.scrollWidth <= innerWidth')
        screenshot(start, f'{profile}-login.png')
        unsigned.close()
        context = browser.new_context(**{**p.devices[device], 'viewport': {'width': width, 'height': height},
                                         'device_scale_factor': 1, 'locale': 'fr-FR', 'storage_state': state})
        page = context.new_page()
        page.on('pageerror', lambda e: errors.append(str(e)))
        for name, path in routes.items():
            response = page.goto(base + path, wait_until='networkidle')
            text = page.locator('body').inner_text()
            if response.status == 404 or 'Accès refusé' in text or 'Access denied' in text:
                report.append({'kind': profile, 'route': name, 'status': 'unavailable', 'http': response.status})
                continue
            try:
                assert response.status == 200, (name, response.status)
                assert 'Fatal error' not in text and 'Warning: ' not in text
                assert page.evaluate('navigator.maxTouchPoints > 0'), 'Browser fixture lost touch emulation'
                page.locator('.indy-menu-toggle').wait_for(state='visible')
                assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Page overflow'
                assert page.locator('#id-right').bounding_box()['width'] >= width - 2, 'Navigation consumes tablet workspace'
                assert page.locator('.side-nav').evaluate('e => e.inert'), 'Closed menu remains focusable'
                violations = page.evaluate('''() => [...document.querySelectorAll('#id-right input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=image]),#id-right textarea,#id-right select,#id-right .select2-selection')].filter(e=>{
                  const r=e.getBoundingClientRect(),s=getComputedStyle(e);
                  if(!r.width||!r.height||r.x<0||e.closest('[hidden]')||s.visibility==='hidden'||e.classList.contains('select2-hidden-accessible'))return false;
                  return r.height<43||parseFloat(s.fontSize)<16;
                }).slice(0,8).map(e=>({id:e.id,cls:e.className,height:e.getBoundingClientRect().height,font:getComputedStyle(e).fontSize}))''')
                assert not violations, ('Cramped controls / mobile zoom', violations)
                if width <= 600 and name == 'products':
                    label = page.locator('table.indy-card-list [data-indy-cell=summary]').filter(has_text='Cuvée de démonstration')
                    assert label.is_visible(), 'Product name hidden behind extra details'
                if name in ('dashboard', 'invoices', 'new-client', 'order-card', 'indy-settings'):
                    screenshot(page, f'{profile}-{name}.png')
                report.append({'kind': profile, 'route': name, 'status': 'passed'})
            except Exception:
                screenshot(page, f'FAIL-{profile}-{name}.png')
                (out / 'report.json').write_text(json.dumps(report, indent=2))
                raise

        # Real navigation, keyboard focus, touch dismissal, native account menu,
        # native global search and a module link. No made-up menu destinations.
        page.goto(base + '/compta/facture/list.php?mainmenu=billing', wait_until='networkidle')
        toggle = page.locator('.indy-menu-toggle')
        toggle.tap()
        assert toggle.get_attribute('aria-expanded') == 'true'
        assert page.locator('#id-right').evaluate('e => e.inert')
        assert page.locator('body').evaluate('e => e.style.overflow') == 'hidden'
        assert page.locator('.indy-menu-close').evaluate('e => e === document.activeElement')
        assert page.locator('.indy-module-nav a').count() >= 5
        search = page.locator('.side-nav .select2-selection').first
        if search.count():
            search.tap()
            assert page.locator('.select2-dropdown').is_visible()
            page.keyboard.press('Escape')
        else:
            page.locator('#divsearchforms1 a').tap()
            field = page.locator('#divsearchforms2 form[action*="/societe/list.php"] input[name=search_all]')
            field.fill('Maison')
            with page.expect_navigation(wait_until='networkidle'):
                page.locator('#divsearchforms2 form[action*="/societe/list.php"] button').tap()
            # Dolibarr may open the only matching record directly.
            result = page.locator('#id-right').inner_text() if '/societe/card.php' in page.url else page.locator('table.liste').inner_text()
            assert 'Maison Aubépine' in result
            assert 'Studio Horizon' not in result
            toggle = page.locator('.indy-menu-toggle')
            toggle.tap()
        screenshot(page, f'{profile}-navigation.png')
        page.keyboard.press('Escape')
        assert toggle.get_attribute('aria-expanded') == 'false'
        assert toggle.evaluate('e => e === document.activeElement')
        toggle.tap()
        # Tapping the exposed strip outside the drawer dismisses it.
        page.locator('.indy-nav-backdrop').tap(position={'x': width - 8, 'y': height / 2})
        assert toggle.get_attribute('aria-expanded') == 'false'
        toggle.tap()
        with page.expect_navigation(wait_until='networkidle'):
            page.locator('.indy-module-nav a[href*="mainmenu=companies"]').first.tap()
        assert 'mainmenu=companies' in page.url
        page.locator('#topmenu-login-dropdown>a').tap()
        account = page.locator('#topmenu-login-dropdown .dropdown-menu')
        assert account.is_visible()
        assert account.evaluate('e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight}')
        page.locator('#topmenu-login-dropdown>a').tap()

        page.goto(base + '/compta/facture/list.php', wait_until='networkidle')
        if width <= 600:
            assert page.locator('table.indy-card-list tr.oddeven').count() == 6
            row = page.locator('table.indy-card-list tr.oddeven').first
            for cell in row.locator('[data-indy-cell=summary]').all():
                assert cell.is_visible()
            details = row.locator('.indy-row-details')
            if details.count():
                details.tap()
                assert details.get_attribute('aria-expanded') == 'true'
                assert row.locator('[data-indy-cell=detail]').first.is_visible()
                details.tap()
            allbox = page.locator('.indy-list-tools input.checkallactions')
            allbox.check()
            assert page.locator('table.liste tr.oddeven input[type=checkbox]:checked').count() == 6
            allbox.uncheck()
            columns = page.locator('.indy-list-tools [aria-label="Choisir les colonnes"]')
            if columns.count():
                columns.tap()
                popup = page.locator('.indy-list-tools .dropdown dd ul:visible')
                assert popup.is_visible()
                assert popup.evaluate('e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth}')
                page.locator('.indy-column-close').tap()
                assert not popup.is_visible()
            sort = page.locator('.indy-list-tools select')
            option = sort.locator('option').nth(1).get_attribute('value')
            with page.expect_navigation(wait_until='networkidle'):
                sort.select_option(option)
            assert 'sortfield=' in page.url and 'sortorder=ASC' in page.url

        page.goto(base + '/societe/list.php', wait_until='networkidle')
        panel = page.locator('.indy-filters')
        assert not panel.evaluate('e=>e.open')
        panel.locator('summary').tap()
        panel.locator('[name=search_nom]').fill('Maison')
        with page.expect_navigation(wait_until='networkidle'):
            panel.locator('[name=button_search_x]').tap()
        assert 'Maison Aubépine' in page.locator('table.liste').inner_text()
        assert 'Studio Horizon' not in page.locator('table.liste').inner_text()
        page.locator('.indy-filters summary').tap()
        with page.expect_navigation(wait_until='networkidle'):
            page.locator('[name=button_removefilter_x]').tap()
        assert 'Studio Horizon' in page.locator('table.liste').inner_text()

        page.goto(base + '/commande/list.php', wait_until='networkidle')
        page.locator('.indy-filters summary').tap()
        datepicker = page.locator('.indy-filters .ui-datepicker-trigger').first
        if datepicker.count():
            datepicker.tap()
            calendar = page.locator('#ui-datepicker-div:visible')
            assert calendar.is_visible()
            assert calendar.evaluate('e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth}')
            page.keyboard.press('Escape')

        page.goto(base + '/commande/card.php?id=1', wait_until='networkidle')
        assert not page.locator('.moretab:visible').count(), 'Hover-only tabs remain on a touch device'
        notes = page.locator('#id-right .tabs a[href*="/commande/note.php"]').first
        assert notes.is_visible(), 'A native tab was lost in responsive layout'
        with page.expect_navigation(wait_until='networkidle'):
            notes.tap()
        assert '/commande/note.php' in page.url
        page.goto(base + '/commande/card.php?id=1', wait_until='networkidle')
        if width <= 600:
            for selector in ('#price_ht', '#price_ttc', '#qty'):
                field = page.locator(selector)
                assert field.bounding_box()['width'] >= 80
                assert field.evaluate('e=>e.form===document.querySelector("#addline").form'), 'Native line form lost'
            page.locator('.indy-line-editor').scroll_into_view_if_needed()
            screenshot(page, f'{profile}-line-entry.png')
            page.locator('#select2-idprod-container').tap()
            page.locator('.select2-dropdown input.select2-search__field').fill('INDY')
            page.locator('.select2-results__option[role=option]').filter(has_text='INDY-DEMO-01').first.tap()
            page.wait_for_load_state('networkidle')
            page.wait_for_function("document.querySelector('#price_ht').value !== ''", timeout=5000)
            assert page.locator('#price_ht').input_value(), 'Native product callback lost'
            page.locator('#price_ht').fill('25')
            page.locator('#qty').fill('2')
            old_count = page.locator('tr.oddeven[data-id]').count()
            with page.expect_navigation(wait_until='networkidle'):
                page.locator('#addline').tap()
            assert page.locator('tr.oddeven[data-id]').count() == old_count + 1, 'Native add-line submit failed'
            assert '50,00' in page.locator('tr.oddeven[data-id]').last.locator('.linecolht').inner_text(), 'Native quantity/price calculation changed'
        print('PASS responsive pages and native touch interactions:', profile, flush=True)
        (out / 'report.json').write_text(json.dumps(report, indent=2))
        context.close()
    auth.close()
    browser.close()

(out / 'report.json').write_text(json.dumps(report, indent=2))
assert not errors, errors
passed = sum(r['status'] == 'passed' for r in report)
unavailable = sum(r['status'] == 'unavailable' for r in report)
print(f'PASS responsive audit: {passed} checks; {unavailable} unavailable page/viewport combinations explicitly reported; accepted desktop pixels unchanged.')
