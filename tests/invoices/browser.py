"""Check actual native defaults, persistence, palettes, uploads and mobile UI."""
import os
import json
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
    assert page.locator('.indy-preview-body').evaluate('e=>getComputedStyle(e).backgroundColor')=='rgb(244, 248, 243)', 'White panel in paper preview'
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
    for p in pdf:
        px=p.get_pixmap(clip=fitz.Rect(300,600,306,606),alpha=False).pixel(1,1)
        assert all(abs(a-b)<4 for a,b in zip(px,(244,248,243))), 'Generated order ignores the selected full-page background'
    page.goto(base+'/indyinvoice/admin/setup.php',wait_until='networkidle')
    # Every text color and bank/order option survives an actual form POST.
    page.locator('[data-palette=cream]').click()
    custom={'TEXT':'#452060','HEADER_TEXT':'#fff4db','TABLE_TEXT':'#354080','TOTAL_TEXT':'#fff4db','FOOTER_TEXT':'#354080','BANNER':'#452060','ACCENT':'#452060'}
    for key,value in custom.items():
        page.locator('#INDYINVOICE_'+key+'_COLOR').evaluate('(e,value)=>{e.value=value;e.dispatchEvent(new Event("input",{bubbles:true}));}',value)
    for zone in ('HEADER','TABLE','TOTAL'): page.locator('[name=INDYINVOICE_'+zone+'_TEXT_AUTO]').uncheck()
    page.locator('[name=INDYINVOICE_SHOW_BANK_DETAILS]').check()
    page.locator('[name=INDYINVOICE_USE_INVOICE_TEXT_FOR_ORDERS]').check()
    page.locator('input[type=submit]').click();page.wait_for_load_state('networkidle');page.reload(wait_until='networkidle')
    assert 'saved=1' in page.url
    for key,value in custom.items(): assert page.locator('#INDYINVOICE_'+key+'_COLOR').input_value()==value
    assert page.locator('[name=INDYINVOICE_SHOW_BANK_DETAILS]').is_checked()
    assert page.locator('[name=INDYINVOICE_USE_INVOICE_TEXT_FOR_ORDERS]').is_checked()
    assert not page.locator('[name=INDYINVOICE_HEADER_TEXT_AUTO]').is_checked()
    # Insufficient contrast is rejected without modifying saved preferences.
    page.locator('#INDYINVOICE_TEXT_COLOR').evaluate('e=>e.value="#ffffff"')
    page.locator('input[type=submit]').click();page.wait_for_load_state('networkidle')
    assert 'contraste du texte insuffisant' in page.locator('body').inner_text()
    page.goto(base+'/indyinvoice/admin/setup.php',wait_until='networkidle')
    assert page.locator('#INDYINVOICE_TEXT_COLOR').input_value()=='#452060'
    # Native invoice settings, the same controls shown in the user's screenshot.
    page.goto(base+'/admin/invoice.php',wait_until='networkidle')
    mention='Données fictives — MENTION ENREGISTRÉE DEPUIS DOLIBARR. '+('Conditions de conservation et de livraison de démonstration. '*18)+'FIN MENTION NAVIGATEUR.'
    page.locator('[name=INVOICE_FREE_TEXT]').fill(mention)
    mention_row=page.locator('tr').filter(has=page.locator('[name=INVOICE_FREE_TEXT]'))
    mention_row.locator('input[type=submit]').click();page.wait_for_load_state('networkidle');page.reload(wait_until='networkidle')
    assert page.locator('[name=INVOICE_FREE_TEXT]').input_value()==mention
    bankform=page.locator('form').filter(has=page.locator('[name=action][value=setribchq]'))
    bankform.locator('[name=rib]').select_option(label='DEMONSTRATION A')
    bankform.locator('input[type=submit]').click();page.wait_for_load_state('networkidle')
    fixtures=json.loads((artifacts/'manifest.json').read_text())
    page.goto(base+'/compta/facture/card.php?facid='+str(fixtures['bank-card-shown']['id']),wait_until='networkidle')
    form=page.locator('form').filter(has=page.locator('input[name=action][value=builddoc]'))
    form.locator('select[name=model]').select_option('indy')
    form.locator('input[type=submit]').click();page.wait_for_load_state('networkidle')
    link=page.locator('a[href*="document.php?modulepart=facture"]').first
    with page.expect_download() as pending: link.click()
    pending.value.save_as(str(artifacts/'invoice-from-ui.pdf'))
    pdf=fitz.open(artifacts/'invoice-from-ui.pdf')
    text=' '.join(p.get_text() for p in pdf)
    assert 'MENTION ENREGISTRÉE DEPUIS DOLIBARR.' in text and 'FIN MENTION NAVIGATEUR.' in text
    assert max(r.y1 for r in pdf[-1].search_for('FIN MENTION NAVIGATEUR.')) < pdf[-1].search_for('1 / 1')[0].y0, 'Free text overlaps pagination with Unicode font'
    assert 'TITULAIRE FICTIF A' in text and 'DEMOFRPPXXX' in text and text.count('IBAN')==1
    spans=[s for b in pdf[0].get_text('dict')['blocks'] if 'lines' in b for l in b['lines'] for s in l['spans']]
    assert any('Accompagnement' in s['text'] and s['color']==0x452060 for s in spans)
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
print('PASS: native invoice/order defaults, saved text colors and payment switches, contrast rejection, native bank/free-text settings regenerated from UI, reversal, uploads and mobile settings.')
