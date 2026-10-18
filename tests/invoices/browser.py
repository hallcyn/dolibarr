"""Check actual native defaults, persistence, palettes, uploads and mobile UI."""
import os
import fitz
from pathlib import Path
from playwright.sync_api import sync_playwright

base = os.environ.get('DOLIBARR_TEST_URL', 'http://127.0.0.1:8080')
artifacts = Path('artifacts/invoices'); artifacts.mkdir(parents=True, exist_ok=True)
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE'))
    page = browser.new_page(viewport={'width':1440,'height':1000},locale='fr-FR')
    page.goto(base+'/'); page.locator('#username').fill('admin'); page.locator('#password').fill(os.environ['DOLIBARR_TEST_PASSWORD'])
    page.locator('input[type=submit]').first.click(); page.wait_for_load_state('networkidle')
    page.goto(base+'/admin/modules.php?search_keyword=Indy&mode=common',wait_until='networkidle')
    assert 'Documents Indy' in page.locator('body').inner_text()
    response = page.goto(base+'/indyinvoice/admin/setup.php',wait_until='networkidle'); assert response.status==200
    assert 'Personnalisation des documents Indy' in page.locator('body').inner_text()
    page.locator('[data-palette=sage]').click()
    assert page.locator('#INDYINVOICE_TABLE_COLOR').input_value()=='#286347'
    page.locator('#INDYINVOICE_FONT').select_option('dejavusans')
    page.locator('[name=make_default]').check(); page.locator('[name=make_default_order]').check()
    page.locator('input[type=submit]').click(); page.wait_for_load_state('networkidle')
    assert 'saved=1' in page.url
    page.reload(wait_until='networkidle')
    assert page.locator('[name=make_default]').is_checked(), 'Invoice default check must reflect the saved model'
    assert page.locator('[name=make_default_order]').is_checked(), 'Order default check must reflect the saved model'
    assert page.locator('#INDYINVOICE_FONT').input_value()=='dejavusans'
    assert page.locator('#INDYINVOICE_TABLE_COLOR').input_value()=='#286347'
    for url,name in [('/admin/invoice.php','indy'),('/admin/order.php','indyorder')]:
        page.goto(base+url,wait_until='networkidle')
        row=page.locator('tr').filter(has=page.get_by_text(name,exact=True)).last
        assert row.count()==1
        assert row.locator('a[href*="action=setdoc"]').count()==0, 'Native default disagrees with settings'
    # The exact user workflow: an existing draft order has its own PDF model.
    page.goto(base+'/commande/card.php?id=1',wait_until='networkidle')
    document_form=page.locator('form').filter(has=page.locator('input[name=action][value=builddoc]'))
    document_form.locator('select[name=model]').select_option('indyorder')
    document_form.locator('input[type=submit]').click();page.wait_for_load_state('networkidle')
    assert page.locator('select[name=model]').input_value()=='indyorder'
    link=page.locator('a[href*="document.php?modulepart=commande"]').first
    with page.expect_download() as pending: link.click()
    pending.value.save_as(str(artifacts/'order-from-ui.pdf'))
    pdf=fitz.open(artifacts/'order-from-ui.pdf')
    assert 'Commande' in pdf[0].get_text()
    pixel=pdf[0].get_pixmap(clip=fitz.Rect(3,3,9,9),alpha=False).pixel(2,2)
    assert pixel[1]>pixel[0], 'Draft order used the classic PDF instead of the saved sage style'
    page.goto(base+'/indyinvoice/admin/setup.php',wait_until='networkidle')
    # Unchecking is an actual reversible preference, not a one-shot action.
    page.locator('[name=make_default]').uncheck();page.locator('[name=make_default_order]').uncheck()
    page.locator('input[type=submit]').click();page.wait_for_load_state('networkidle');page.reload(wait_until='networkidle')
    assert not page.locator('[name=make_default]').is_checked()
    assert not page.locator('[name=make_default_order]').is_checked()
    page.locator('[name=make_default]').check();page.locator('[name=make_default_order]').check()
    page.locator('input[type=submit]').click();page.wait_for_load_state('networkidle')
    # Re-encoding rejects a disguised active file before any preference is saved.
    page.locator('#banner_image').set_input_files({'name':'bad.png','mimeType':'image/png','buffer':b'<svg onload="alert(1)"/>'})
    page.locator('input[type=submit]').click();page.wait_for_load_state('networkidle')
    assert 'Réglages invalides' in page.locator('body').inner_text()
    assert page.locator('[name=make_default]').is_checked()
    page.goto(base+'/indyinvoice/admin/setup.php',wait_until='networkidle')
    image=fitz.Pixmap(fitz.csRGB,fitz.IRect(0,0,1680,160),False);image.clear_with(220)
    page.locator('#banner_image').set_input_files({'name':'demo.png','mimeType':'image/png','buffer':image.tobytes('png')})
    page.locator('input[type=submit]').click();page.wait_for_load_state('networkidle');page.reload(wait_until='networkidle')
    assert page.locator('[name=use_banner]').is_checked()
    page.wait_for_function("document.querySelector('.indy-preview-photo').naturalWidth===1680")
    page.screenshot(path=str(artifacts/'settings-desktop.png'),full_page=True)
    for width in [768,390,320]:
        page.set_viewport_size({'width':width,'height':844})
        if not page.evaluate('() => document.documentElement.scrollWidth <= innerWidth'):
            page.screenshot(path=str(artifacts/f'settings-overflow-{width}.png'),full_page=True)
            print(page.evaluate('''() => [...document.querySelectorAll('#id-right *')].filter(e=>{const r=e.getBoundingClientRect();return r.width>0 && r.right>innerWidth+1}).slice(-12).map(e=>({tag:e.tagName,cls:e.className,width:e.getBoundingClientRect().width,right:e.getBoundingClientRect().right}))'''))
            raise AssertionError(('Settings overflow',width))
    page.screenshot(path=str(artifacts/'settings-mobile.png'),full_page=True)
    browser.close()
print('PASS: native invoice/order defaults, checked-state persistence after reload, reversal, palettes, upload validation and mobile settings.')
