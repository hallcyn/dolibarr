"""Real native profile/virtual-card and stale-cache regressions; disposable localhost only."""
import hashlib
import json
import os
import re
from pathlib import Path
from urllib.parse import urlsplit, parse_qs
from playwright.sync_api import sync_playwright

base=os.environ.get('DOLIBARR_TEST_URL','http://127.0.0.1:8080')
assert urlsplit(base).hostname in ('127.0.0.1','localhost'), 'Disposable localhost fixture required'
root=Path(__file__).resolve().parent
out=Path(os.environ.get('INDY_ARTIFACTS','artifacts'))/'profile';out.mkdir(parents=True,exist_ok=True)
report=[]
profiles=[('small-phone','Pixel 7',320,740),('phone','iPhone 13',390,844),('landscape','iPhone 13',844,390),('tablet','iPad Mini',768,1024),('tablet-landscape','iPad Mini',1024,768),('large-tablet','iPad Pro 11',1366,1024)]

def snap(page,name):
    page.evaluate('()=>document.fonts.ready')
    page.screenshot(path=str(out/name),full_page=False,animations='disabled',caret='hide')

def fits(page):
    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'), 'Horizontal page overflow'

with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=os.environ.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE'))
    for name,device,width,height in profiles:
        context=browser.new_context(**{**p.devices[device],'viewport':{'width':width,'height':height},'device_scale_factor':1,'locale':'fr-FR'})
        page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        stale=[]
        def cached_script(route):
            if 'indy_version=' not in route.request.url:
                stale.append(route.request.url)
                route.fulfill(status=200,content_type='application/javascript',body=(root/'reference/indy-1.4.1.js').read_text())
            else:route.continue_()
        page.route('**/theme/indy/indy.js*',cached_script)
        page.goto(base+'/',wait_until='networkidle');page.locator('#username').fill('admin');page.locator('#password').fill(os.environ['DOLIBARR_TEST_PASSWORD']);page.locator('input[type=submit]').first.click();page.wait_for_load_state('networkidle')
        scripts=page.locator('script[src*="/theme/indy/indy.js"]').evaluate_all('es=>es.map(e=>e.src)')
        expected=hashlib.sha256(context.request.get(base+'/theme/indy/indy.js').body()).hexdigest()[:16]
        assert len(scripts)==1 and parse_qs(urlsplit(scripts[0]).query).get('indy_version')==[expected], ('Theme cache key',scripts)
        assert not stale, 'Old cacheable theme URL was still requested'
        page.locator('#topmenu-login-dropdown>a').tap()
        assert page.locator('[data-indy-account-action]').count()==3
        assert page.locator('.indy-account-toolbar').is_visible() and page.locator('.indy-account-person').is_visible()
        snap(page,name+'-account.png');page.locator('.indy-account-close').tap()
        page.goto(base+'/user/card.php?id=1',wait_until='networkidle');fits(page)
        selector=page.locator('.indy-profile-sections select');assert selector.is_visible() and selector.locator('option').count()>=5
        snap(page,name+'-profile.png')
        for part in ('/user/perms.php','/user/param_ihm.php'):
            option=selector.locator('option').evaluate_all('(es,part)=>es.find(o=>o.value.includes(part))?.value',part)
            assert option, ('Missing native profile section',part)
            with page.expect_navigation(wait_until='networkidle'):selector.select_option(option)
            assert part in page.url;fits(page)
            selector=page.locator('.indy-profile-sections select');assert selector.is_visible()
        page.goto(base+'/user/card.php?id=1&action=edit',wait_until='networkidle');fits(page);snap(page,name+'-profile-edit.png')
        page.goto(base+'/',wait_until='networkidle');page.locator('#topmenu-login-dropdown>a').tap();page.locator('[data-indy-account-action=card]').tap()
        dialog=page.locator('.ui-dialog[data-indy-vcard-dialog]:visible');dialog.wait_for()
        assert dialog.locator('.ui-dialog-title').inner_text()=='Ma carte de visite'
        assert dialog.evaluate('e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.top>=0&&r.right<=innerWidth&&r.bottom<=innerHeight}')
        assert dialog.locator('.ui-dialog-titlebar-close').bounding_box()['width']>=44
        frame=page.frame_locator('#iframedialogpublicvirtualcardmenu')
        frame.locator('body.indy-profile-responsive').wait_for()
        native_frame=page.frame(url=re.compile(r'/user/virtualcard\.php'))
        assert native_frame is not None
        switch=frame.locator('[role=switch][aria-label="Carte de visite publique"]')
        if switch.get_attribute('aria-checked')=='false':
            with native_frame.expect_navigation(wait_until='networkidle'):switch.tap()
        frame.locator('#publicurluser').wait_for(state='visible')
        assert frame.locator('.indy-appbar').count()==0, 'Nested application menu after visibility toggle'
        assert frame.locator('body').evaluate('()=>document.documentElement.scrollWidth<=innerWidth'), 'Virtual-card iframe overflow'
        url=frame.locator('#publicurluser').input_value()
        assert frame.locator('.indy-vcard-actions a').get_attribute('href')==url
        frame.get_by_role('button',name='Copier le lien',exact=True).tap()
        frame.get_by_role('status').filter(has_text='Lien copié').wait_for()
        advanced=frame.locator('#lnk');advanced.tap()
        assert advanced.get_attribute('aria-expanded')=='true'
        note=frame.locator('#USER_PUBLIC_MORE');note.fill('Mention fictive pour vérifier la présentation de la carte.')
        with native_frame.expect_navigation(wait_until='networkidle'):frame.locator('input[name=save]').tap()
        frame.locator('#USER_ENABLE_PUBLIC').wait_for(state='attached')
        assert frame.locator('#USER_PUBLIC_MORE').input_value()=='Mention fictive pour vérifier la présentation de la carte.'
        assert frame.locator('.indy-appbar').count()==0, 'Nested application menu after form save'
        assert frame.locator('body').evaluate('()=>document.documentElement.scrollWidth<=innerWidth'), 'Advanced options overflow'
        # Reset the fictional content and keep the native Save workflow.
        if frame.locator('#lnk').get_attribute('aria-expanded')!='true':frame.locator('#lnk').tap()
        frame.locator('#USER_PUBLIC_MORE').fill('')
        with native_frame.expect_navigation(wait_until='networkidle'):frame.locator('input[name=save]').tap()
        frame.locator('#USER_ENABLE_PUBLIC').wait_for(state='attached')
        if frame.locator('#lnk').get_attribute('aria-expanded')=='true':frame.locator('#lnk').tap()
        preview=frame.frame_locator('#virtualcard-iframe')
        preview.locator('body.indy-vcard-preview-responsive').wait_for()
        assert preview.locator('body').evaluate('e=>getComputedStyle(e).zoom')=='1'
        native_frame.wait_for_load_state('networkidle')
        native_frame.evaluate('scrollTo(0,0)')
        native_frame.evaluate('()=>document.fonts.ready')
        native_frame.evaluate('()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))')
        assert page.locator('#iframedialogpublicvirtualcardmenu').bounding_box()['height'] >= height-120, 'Virtual-card iframe collapsed'
        snap(page,name+'-virtual-card.png')
        dialog.locator('.ui-dialog-titlebar-close').tap()
        assert page.locator('#topmenu-login-dropdown>a').evaluate('e=>e===document.activeElement')
        page.locator('#topmenu-login-dropdown>a').tap();page.locator('[data-indy-account-action=card]').tap()
        dialog.wait_for(state='visible')
        assert dialog.locator('.ui-dialog-title').inner_text()=='Ma carte de visite', 'Native reinitialization replaced the short title'
        page.frame_locator('#iframedialogpublicvirtualcardmenu').locator('body.indy-profile-responsive').wait_for()
        dialog.locator('.ui-dialog-titlebar-close').tap()
        assert not errors,errors
        assert not stale, 'A subpage requested stale theme JS'
        report.append({'profile':name,'status':'passed','asset_version':expected})
        context.close();print('PASS native profile, virtual card, copy/save and versioned assets:',name,flush=True)
    browser.close()
(out/'report.json').write_text(json.dumps(report,indent=2))
print('PASS six profile/virtual-card contexts and stale-asset cache regression.')
