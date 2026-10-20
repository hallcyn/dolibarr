"""Real selection, preview, download and mobile layout on disposable localhost."""
import os,json,fitz
from pathlib import Path
from playwright.sync_api import sync_playwright
base='http://127.0.0.1:8080';out=Path('artifacts/labels');data=json.loads((out/'ui.json').read_text())
with sync_playwright() as p:
    browser=p.chromium.launch()
    context=browser.new_context(viewport={'width':1440,'height':1000},locale='fr-FR',accept_downloads=True)
    page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(base+'/');page.locator('#username').fill('admin');page.locator('#password').fill(os.environ['DOLIBARR_TEST_PASSWORD']);page.locator('input[type=submit]').first.click();page.wait_for_load_state('networkidle')
    page.goto(base+'/societe/index.php?mainmenu=companies',wait_until='networkidle')
    assert page.locator('a[href*="/indylabels/index.php"]').count()>0,'Labels menu missing from third parties'
    response=page.goto(base+'/indylabels/index.php',wait_until='networkidle');assert response.status==200
    page.locator('#thirdparties').select_option([str(x) for x in data['ids'][:17]])
    # Select2 listens to change through jQuery.
    page.locator('#thirdparties').evaluate("e=>window.jQuery(e).trigger('change')")
    assert '17 tiers' in page.locator('#indy-labels-count').inner_text()
    page.get_by_role('button',name='Vérifier les adresses',exact=True).click();page.wait_for_load_state('networkidle')
    assert page.locator('.indy-envelope-label').count()==17
    assert 'Mme Zoé' in page.locator('.indy-envelope-label').first.inner_text()
    page.screenshot(path=str(out/'desktop.png'),full_page=True)
    with page.expect_download() as download:page.get_by_role('button',name='Télécharger le PDF',exact=True).click()
    file=out/'download.pdf';download.value.save_as(file)
    assert len(fitz.open(file))==2
    page.set_viewport_size({'width':390,'height':844})
    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Mobile overflow'
    page.screenshot(path=str(out/'mobile.png'),full_page=True)
    page.locator('#indy-labels-clear').click();assert '0 tiers' in page.locator('#indy-labels-count').inner_text()
    page.locator('#indy-labels-all').click();assert len(page.locator('#thirdparties').evaluate('e=>Array.from(e.selectedOptions)'))>=33
    page.locator('#thirdparties').select_option(str(data['incomplete']))
    page.get_by_role('button',name='Télécharger le PDF',exact=True).click();page.wait_for_load_state('networkidle')
    assert page.locator('[role=alert]').count()==1,'Incomplete address must not download'
    assert not errors,errors
    # A download POST without a valid CSRF token must be blocked by native Dolibarr.
    response=context.request.post(base+'/indylabels/index.php',form={'action':'download','thirdparties[]':str(data['ids'][0]),'start':'1','token':'invalid'})
    assert 'application/pdf' not in response.headers.get('content-type',''),'Invalid CSRF accepted'
    browser.close()
print('PASS: desktop/mobile, native multiselect, preview, 17-label PDF download, select/clear, incomplete address and CSRF protection.')
