"""Responsive audit and accepted-desktop comparison on disposable Dolibarr only.

The historical reference is CSS/JS from the user's accepted 1.4.1 commit.
Both renders use the same running fixture, browser, fonts and fictional data.
No production credentials or production data are used.
"""
import ast
import base64
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
desktop_routes = ('dashboard', 'clients', 'invoices', 'new-client', 'order-card', 'product-card', 'user-card')
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
    # 1.5.5 intentionally redesigns the rail utilities. Compare the accepted
    # workspace and context menu pixels, excluding only that main rail.
    # 1.5.6 intentionally repairs action-input colors. Mask only those inputs;
    # their native semantics and contrast are checked in usability.py.
    if name.startswith('desktop-') and not name.startswith('desktop-login-'):
        return page.locator('#id-container').screenshot(path=str(out / name), animations='disabled', caret='hide', mask=[page.locator('canvas'), page.locator('#id-right input:is([type=submit],[type=button],[type=reset]):not(:disabled), #id-right .datenowlink:not(:disabled)')])
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


def desktop_pixels(comparator, first, second):
    if first == second:
        return {'same_size': True, 'changed': 0, 'max_channel_delta': 0}
    # CSS reload can change a few anti-aliased icon edge pixels by 1–2/255
    # in Chromium. Compare decoded pixels, allowing only this tiny raster
    # noise, never a size change, a shifted control or a changed surface.
    return comparator.evaluate('''async sources=>{
      const images=await Promise.all(sources.map(async s=>createImageBitmap(await (await fetch('data:image/png;base64,'+s)).blob())));
      const [a,b]=images;
      if(a.width!==b.width||a.height!==b.height){images.forEach(i=>i.close());return {same_size:false,changed:0,max_channel_delta:0};}
      const canvas=new OffscreenCanvas(a.width,a.height),ctx=canvas.getContext('2d',{willReadFrequently:true});
      ctx.drawImage(a,0,0);const before=ctx.getImageData(0,0,a.width,a.height).data;
      ctx.clearRect(0,0,a.width,a.height);ctx.drawImage(b,0,0);const after=ctx.getImageData(0,0,b.width,b.height).data;
      let changed=0,max=0;
      for(let i=0;i<before.length;i+=4){let delta=0;for(let c=0;c<4;c++)delta=Math.max(delta,Math.abs(before[i+c]-after[i+c]));if(delta){changed++;max=Math.max(max,delta);}}
      images.forEach(i=>i.close());return {same_size:true,changed,max_channel_delta:max};
    }''', [base64.b64encode(v).decode() for v in (first, second)])


with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE'))
    comparator = browser.new_page()
    auth = browser.new_context(viewport={'width': 1440, 'height': 1000}, locale='fr-FR')
    # Whitelist the single historical selector repair; all other accepted CSS
    # must still be present byte-for-byte before the responsive additions.
    repaired_reference = old_css.replace(
        '#id-right input:disabled, #id-right input:read-only { background: #f5f7f8; }',
        '/* :read-only also matches submit/button controls. Only actual readonly\n'
        ' * fields should receive this surface; active actions keep their own colors. */\n'
        '#id-right input:disabled, #id-right input[readonly], #id-right textarea[readonly] { background: #f5f7f8; }')
    assert auth.request.get(base + '/theme/indy/indy.css').text().startswith(repaired_reference), 'Accepted desktop CSS was modified'
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
            pixels = desktop_pixels(comparator, first, second)
            (out / f'desktop-{name}-{width}-pixels.json').write_text(json.dumps(pixels, indent=2))
            assert pixels['same_size'] and pixels['changed'] <= 200 and pixels['max_channel_delta'] <= 2, (name, width, 'Accepted desktop pixels changed', pixels)
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
                assert a.locator('.indy-account-toolbar,.indy-account-person,.indy-account-action-label,.indy-account-disclosure-label,.indy-account-legacy-label').count() == 0
                assert a.locator('#topmenu-login-dropdown .user-body + .user-footer').count() == 1
                assert a.locator('.indy-profile-sections,.indy-vcard-share,.indy-vcard-enable').count() == 0
                assert not a.locator('body').evaluate("e=>e.classList.contains('indy-profile-responsive')")
                screenshot(a, f'desktop-{name}-{width}-restored.png')
            report.append({'kind': 'desktop-reference', 'route': name, 'width': width, 'status': 'passed'})
        before.close()
        unsigned_desktop = browser.new_context(viewport={'width': width, 'height': 1000}, locale='fr-FR')
        unsigned_desktop.route('**/theme/indy/style.css.php*', old_style)
        unsigned_desktop.route('**/theme/indy/indy.js*', old_script)
        desktop_login = unsigned_desktop.new_page()
        desktop_login.goto(base + '/', wait_until='networkidle')
        first = screenshot(desktop_login, f'desktop-login-{width}-accepted.png')
        unsigned_desktop.unroute('**/theme/indy/style.css.php*')
        unsigned_desktop.unroute('**/theme/indy/indy.js*')
        desktop_login.evaluate('''() => new Promise((resolve,reject)=>{
          const link=document.querySelector('link[href*="/theme/indy/style.css.php"]');
          link.onload=resolve;link.onerror=reject;link.href=link.href+'&indy_reference=current';
        })''')
        desktop_login.add_script_tag(url=base + '/theme/indy/indy.js?indy_reference=current')
        second = screenshot(desktop_login, f'desktop-login-{width}-current.png')
        pixels = desktop_pixels(comparator, first, second)
        # Desktop login is intentionally redesigned in 1.5.4. All seven
        # authenticated routes above still compare to the accepted pixels.
        assert desktop_login.locator('.login_table').evaluate("e=>{const r=e.getBoundingClientRect();return r.width<=480&&r.width>=440&&r.x>=0&&r.right<=innerWidth}")
        fields = desktop_login.locator('#username,#password,#login-submit-wrapper input[type=submit]')
        widths = []
        for field in fields.all():
            box = field.bounding_box()
            assert box['height'] >= 54
            widths.append(box['width'])
        assert max(widths) - min(widths) <= 1, ('Aligned desktop login', widths)
        assert desktop_login.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert desktop_login.locator('#img_logo').is_visible()
        desktop_login.locator('#password').fill('fictional-preview-password')
        desktop_login.locator('#togglepassword').click()
        assert desktop_login.locator('#password').get_attribute('type') == 'text'
        desktop_login.locator('#togglepassword').click()
        assert desktop_login.locator('#password').get_attribute('type') == 'password'
        assert desktop_login.locator('a.aloginpasswordforgotten').is_visible()
        report.append({'kind': 'desktop-login', 'route': 'login', 'width': width, 'status': 'passed'})
        unsigned_desktop.close()
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
        # The native phone stylesheet caps fields at 180px and their parent
        # at 240px. Check useful width and eye alignment, not just no overflow.
        login_geometry = start.evaluate('''()=>{
          const card=document.querySelector('.login_table'),c=getComputedStyle(card),r=card.getBoundingClientRect();
          const usable=r.width-parseFloat(c.paddingLeft)-parseFloat(c.paddingRight)-2;
          const fields=[...document.querySelectorAll('#username,#password')].map(e=>e.getBoundingClientRect());
          const eye=document.querySelector('#togglepassword').getBoundingClientRect(),password=fields[1];
          const submit=document.querySelector('#login-submit-wrapper input').getBoundingClientRect();
          return {full:fields.every(f=>f.width>=usable-1)&&submit.width>=usable-1,
            inside:eye.left>=password.left&&eye.right<=password.right&&eye.top>=password.top&&eye.bottom<=password.bottom,
            eye:eye.width>=44&&eye.height>=44,
            reserved:parseFloat(getComputedStyle(document.querySelector('#password')).paddingRight)>=eye.width+4};
        }''')
        assert all(login_geometry.values()), ('Mobile login geometry', login_geometry)
        screenshot(start, f'{profile}-login.png')
        start.locator('#username').fill('admin')
        start.locator('#password').fill(os.environ['DOLIBARR_TEST_PASSWORD'])
        start.locator('#togglepassword').tap()
        assert start.locator('#password').get_attribute('type') == 'text'
        start.locator('#togglepassword').tap()
        assert start.locator('#password').get_attribute('type') == 'password'
        with start.expect_navigation(wait_until='networkidle'):
            start.locator('#login-submit-wrapper input[type=submit]').tap()
        assert start.locator('.indy-menu-toggle').is_visible(), 'Native mobile login failed'
        start.locator('#topmenu-login-dropdown>a').tap()
        with start.expect_navigation(wait_until='networkidle'):
            start.locator('[data-indy-account-action=logout]').tap()
        assert start.locator('#username').is_visible(), 'Native account logout failed'
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
        def icon_geometry():
            return page.locator('.indy-module-nav a').evaluate_all('''es=>es.map(e=>{
              const i=e.querySelector('.indy-module-icon'),icon=i.firstElementChild,label=e.querySelector('.indy-module-label');
              const r=i.getBoundingClientRect(),v=icon.getBoundingClientRect(),l=label.getBoundingClientRect(),a=e.getBoundingClientRect();
              return {square:r.width===32&&r.height===32&&v.width>=24&&v.height>=24,
                separate:r.bottom<=l.top,inside:l.left>=a.left&&l.right<=a.right&&l.bottom<=a.bottom,
                label:label.scrollWidth<=label.clientWidth+1};
            })''')
        assert all(all(v.values()) for v in icon_geometry()), ('Squashed navigation icon', icon_geometry())
        # Long custom/translated module names must wrap without shrinking icons.
        label = page.locator('.indy-module-label').first
        original_label = label.inner_text()
        label.evaluate("e=>e.textContent='Personnalisation et administration des fonctionnalités supplémentaires'")
        assert all(all(v.values()) for v in icon_geometry()), ('Long menu label damages icons', icon_geometry())
        label.evaluate('(e,text)=>e.textContent=text', original_label)
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
        # Native account URLs, disclosures and virtual-card dialog remain
        # functional inside the responsive presentation.
        trigger = page.locator('#topmenu-login-dropdown>a')
        trigger.tap()
        account = page.locator('#indy-account-panel')
        assert account.is_visible()
        assert trigger.get_attribute('aria-expanded') == 'true'
        assert account.evaluate('e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight}')
        assert trigger.evaluate('e=>{const r=e.getBoundingClientRect(),i=e.querySelector(".photo").getBoundingClientRect();return r.width===44&&r.height===44&&Math.abs((r.left+r.right-i.left-i.right)/2)<1&&Math.abs((r.top+r.bottom-i.top-i.bottom)/2)<1}')
        assert trigger.evaluate("e=>getComputedStyle(e,'::after').display==='none'"), 'Native caret distorts avatar button'
        actions=page.locator('[data-indy-account-action]')
        assert actions.count() == 3
        for action in actions.all():
            assert action.bounding_box()['height'] >= 48
            assert action.locator('.indy-account-action-label').is_visible()
        assert page.locator('[data-indy-account-action=profile]').get_attribute('href') == trigger.get_attribute('href')
        assert '/user/logout.php?' in page.locator('[data-indy-account-action=logout]').get_attribute('href')
        assert 'token=' in page.locator('[data-indy-account-action=logout]').get_attribute('href'), 'Native logout token lost'
        name=page.locator('.indy-account-person strong');original=name.inner_text()
        name.evaluate("e=>e.textContent='Alexandrine de la Très Longue Organisation Internationale'")
        assert account.evaluate('e=>e.scrollWidth<=e.clientWidth+1'), 'Long user name overflows account panel'
        name.evaluate('(e,text)=>e.textContent=text',original)
        screenshot(page, f'{profile}-account.png')
        company=page.locator('#topmenulogincompanyinfo-btn');session=page.locator('#topmenuloginmoreinfo-btn')
        company.tap()
        page.wait_for_function("document.querySelector('#topmenulogincompanyinfo-btn').getAttribute('aria-expanded')==='true'")
        assert 'Atelier Hallcyn' in page.locator('#topmenulogincompanyinfo').inner_text()
        session.tap()
        page.wait_for_function("document.querySelector('#topmenuloginmoreinfo-btn').getAttribute('aria-expanded')==='true'")
        page.wait_for_function("document.querySelector('#topmenulogincompanyinfo-btn').getAttribute('aria-expanded')==='false'")
        assert 'indy' in page.locator('#topmenuloginmoreinfo').inner_text()
        assert account.evaluate('e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight}'), 'Expanded account escapes viewport'
        session.focus();page.keyboard.press('Enter')
        page.wait_for_function("document.querySelector('#topmenuloginmoreinfo-btn').getAttribute('aria-expanded')==='false'")
        page.keyboard.press('Escape')
        assert not account.is_visible() and trigger.get_attribute('aria-expanded') == 'false'
        assert trigger.evaluate('e=>e===document.activeElement')
        page.keyboard.press('Space')
        assert account.is_visible()
        page.locator('.indy-account-close').tap()
        assert not account.is_visible() and trigger.evaluate('e=>e===document.activeElement')
        trigger.tap();page.touchscreen.tap(4,height-8)
        assert not account.is_visible(), 'Outside tap fails to close account'
        trigger.tap();page.locator('.indy-menu-toggle').tap()
        assert not account.is_visible(), 'Account overlaps main navigation'
        page.locator('.indy-menu-close').tap()
        trigger.tap();page.locator('[data-indy-account-action=card]').tap()
        dialog=page.locator('.ui-dialog:visible')
        dialog.wait_for(state='visible')
        assert not account.is_visible()
        frame=page.frame_locator('#iframedialogpublicvirtualcardmenu')
        frame.locator('body').wait_for(state='visible')
        frame.locator('#USER_ENABLE_PUBLIC').wait_for(state='attached')
        assert 'Fatal error' not in frame.locator('body').inner_text()
        dialog.locator('.ui-dialog-titlebar-close').tap()
        assert trigger.evaluate('e=>e===document.activeElement'), 'Virtual card fails to return focus'
        trigger.tap()
        with page.expect_navigation(wait_until='networkidle'):
            page.locator('[data-indy-account-action=profile]').tap()
        assert '/user/card.php?' in page.url and page.locator('#id-right').is_visible()

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
            assert page.locator('#select_type').evaluate('''e=>{const a=e.getBoundingClientRect(),b=document.querySelector('#prod_entry_mode_free').getBoundingClientRect();return Math.abs(a.y+a.height/2-b.y-b.height/2)<3}'''), 'Free-line choice overlaps its type field'
            assert page.locator('#select2-idprod-container').evaluate('''e=>{const a=e.closest('.select2-selection').getBoundingClientRect(),b=document.querySelector('#prod_entry_mode_predef').getBoundingClientRect();return Math.abs(a.y+a.height/2-b.y-b.height/2)<3}'''), 'Predefined product choice is misaligned'
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
