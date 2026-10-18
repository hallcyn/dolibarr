"""Broader, disposable-only regression audit: forms, filters, menus and line entry."""
import json,os
from pathlib import Path
from playwright.sync_api import sync_playwright
base=os.environ.get('DOLIBARR_TEST_URL','http://127.0.0.1:8080')
out=Path(os.environ.get('INDY_ARTIFACTS','artifacts'))/'usability';out.mkdir(parents=True,exist_ok=True)
routes={
 'client-prices':'/societe/price.php?socid=1',
 'product-statistics':'/product/stats/index.php?id=1',
 'product-order-statistics':'/product/stats/commande.php?id=1',
 'product-invoice-statistics':'/product/stats/facture.php?id=1',
 'product-shipment-statistics':'/product/stats/expedition.php?id=1',
 'user-appearance':'/user/param_ihm.php?id=1',
 'edit-client':'/societe/card.php?socid=1&action=edit',
 'client-documents':'/societe/document.php?socid=1',
 'client-agenda':'/societe/agenda.php?socid=1',
 'edit-product':'/product/card.php?id=1&action=edit',
 'product-documents':'/product/document.php?id=1',
 'product-notes':'/product/note.php?id=1',
 'order-documents':'/commande/document.php?id=1',
 'order-notes':'/commande/note.php?id=1',
 'order-contacts':'/commande/contact.php?id=1',
 'order-agenda':'/commande/agenda.php?id=1',
 'order-statistics':'/commande/stats/index.php',
 'invoice-documents':'/compta/facture/document.php?facid=1',
 'invoice-notes':'/compta/facture/note.php?facid=1',
 'invoice-contacts':'/compta/facture/contact.php?facid=1',
 'invoice-agenda':'/compta/facture/agenda.php?id=1',
 'invoice-statistics':'/compta/facture/stats/index.php',
 'pdf-settings':'/admin/pdf.php',
 'pdf-options':'/admin/pdf_other.php',
 'invoice-settings':'/admin/invoice.php',
 'order-settings':'/admin/order.php',
 'payment-settings':'/admin/payment.php',
 'stock-settings':'/admin/stock.php',
 'user-card':'/user/card.php?id=1',
 'user-permissions':'/user/perms.php?id=1',
}
# Use only pages shipped by the exact Dolibarr version (no invented endpoints).
# These routes have no state-changing GET actions.
report=[]
with sync_playwright() as p:
 b=p.chromium.launch(executable_path=os.environ.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE'))
 page=b.new_page(viewport={'width':1440,'height':1000},locale='fr-FR')
 page.goto(base+'/');page.locator('#username').fill('admin');page.locator('#password').fill(os.environ['DOLIBARR_TEST_PASSWORD']);page.locator('input[type=submit]').click();page.wait_for_load_state('networkidle')
 for width in [1440,768,390]:
  page.set_viewport_size({'width':width,'height':1000})
  for name,path in routes.items():
   response=page.goto(base+path,wait_until='networkidle')
   text=page.locator('body').inner_text()
   if response.status==404 or 'Accès refusé' in text:
    report.append({'route':name,'width':width,'status':'unavailable','http':response.status});continue
   assert response.status<400,(name,response.status)
   assert page.locator('#id-right').is_visible(),(name,'Empty native page; verify the route parameters')
   assert 'Fatal error' not in text and 'Warning: ' not in text,(name,'PHP output')
   try: page.wait_for_function('document.documentElement.scrollWidth <= innerWidth',timeout=3000)
   except Exception:
    page.screenshot(path=str(out/f'overflow-{name}-{width}.png'),full_page=True)
    print(page.evaluate('''()=>[...document.querySelectorAll('#id-right .dolgraphchart,#id-right .div-table-responsive-no-min')].map(e=>({cls:e.className,width:e.getBoundingClientRect().width,overflow:getComputedStyle(e).overflowX,scroll:e.scrollWidth}))'''))
    raise AssertionError((name,width,'Page overflow'))
   for field in page.locator('.indy-entry-row input:not([type=hidden]):not([type=checkbox]):not([type=radio]),.indy-entry-row textarea,.indy-entry-row .select2-selection').all():
    if not field.is_visible():continue
    assert field.evaluate('''e=>{const r=e.getBoundingClientRect(),p=e.closest('tr').getBoundingClientRect();return r.top>=p.top-1&&r.bottom<=p.bottom+1&&r.right<=innerWidth+1}'''),(name,width,'Overlapping form field')
   report.append({'route':name,'width':width,'status':'passed'})
  print('PASS audit additional pages:',width,flush=True)
 # The filter controls remain the original successful form inputs and Select2s.
 for width in [1440,390]:
  page.set_viewport_size({'width':width,'height':1000})
  for name,path in [('clients','/societe/list.php'),('orders','/commande/list.php'),('products','/product/list.php?type=0'),('invoices','/compta/facture/list.php')]:
   page.goto(base+path,wait_until='networkidle')
   panel=page.locator('.indy-filters');assert panel.count()==1,(name,'Filters not enhanced')
   if width<=800:
    assert not panel.evaluate('e=>e.open'),'Mobile filters should leave room for results'
    panel.locator('summary').click()
   assert panel.evaluate('e=>e.open')
   # A moved Select2 must open its real popup outside the hidden old row.
   choice=panel.locator('.select2-selection').first
   if choice.count():
    choice.click()
    popup=page.locator('.select2-dropdown');popup.wait_for(state='visible')
    assert popup.evaluate('e=>e.getBoundingClientRect().width>100')
    page.keyboard.press('Escape')
   assert panel.locator('.indy-filter-label').count()>0
   assert panel.locator('.indy-filter-label').evaluate_all('es=>es.every(e=>e.textContent.length<120)'),(name,'Toolbar interpreted as a filter label')
   assert panel.evaluate('''e=>[...e.querySelectorAll('input:not([type=hidden]),select')].every(c=>c.form&&c.form===e.closest('form')&&!!(c.getAttribute('aria-label')||c.labels.length))'''),(name,'Filter labels or form association lost')
   for field in panel.locator('input[type=text]').all():
    if field.is_visible():assert field.bounding_box()['width']>=100,(name,width,'Cramped filter')
   try: page.wait_for_function('document.documentElement.scrollWidth <= innerWidth',timeout=3000)
   except Exception:
    page.screenshot(path=str(out/f'overflow-filter-{name}-{width}.png'),full_page=True)
    raise AssertionError((name,width,'Filter page overflow'))
   page.screenshot(path=str(out/f'{name}-filters-{width}.png'),full_page=True)
 page.set_viewport_size({'width':1440,'height':1000})
 page.goto(base+'/societe/list.php',wait_until='networkidle')
 page.locator('[name=search_nom]').fill('Maison');page.locator('[name=button_search_x]').click();page.wait_for_load_state('networkidle')
 assert 'Maison Aubépine' in page.locator('table.liste').inner_text()
 assert 'Studio Horizon' not in page.locator('table.liste').inner_text()
 page.locator('[name=button_removefilter_x]').click();page.wait_for_load_state('networkidle')
 assert 'Studio Horizon' in page.locator('table.liste').inner_text()
 page.goto(base+'/commande/list.php',wait_until='networkidle')
 date=page.locator('[name=search_dateorder_start_]');date.fill('01/01/2099');date.press('Tab')
 page.locator('[name=button_search_x]').click();page.wait_for_load_state('networkidle')
 assert 'Aucun enregistrement' in page.locator('table.liste').inner_text(),'Native date filter stopped working'
 page.locator('[name=button_removefilter_x]').click();page.wait_for_load_state('networkidle')
 assert '(PROV1)' in page.locator('table.liste').inner_text(),'Date reset failed'
 # Reproduce the supplied screenshot: open an AJAX product choice and inspect
 # the foreground of every stock/description span while highlighted.
 page.goto(base+'/commande/card.php?id=1',wait_until='networkidle')
 page.locator('#select2-idprod-container').click()
 search=page.locator('.select2-dropdown input.select2-search__field');search.fill('INDY')
 option=page.locator('.select2-results__option[role=option]').filter(has_text='INDY-DEMO-01').first
 option.wait_for();option.hover()
 assert option.evaluate('''e=>{
  const lum=s=>{const a=s.match(/[\\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4});return a[0]*.2126+a[1]*.7152+a[2]*.0722};
  const bg=lum(getComputedStyle(e).backgroundColor);
  return [e,...e.querySelectorAll('span')].filter(x=>x.textContent.trim()).every(x=>{const fg=lum(getComputedStyle(x).color);return (Math.max(fg,bg)+.05)/(Math.min(fg,bg)+.05)>=4.5});
 }'''),'Unreadable product/stock option'
 assert search.evaluate('e=>getComputedStyle(e).color!==getComputedStyle(e).backgroundColor'),'Invisible Select2 search text'
 page.screenshot(path=str(out/'product-dropdown.png'),full_page=True)
 option.click();page.wait_for_load_state('networkidle')
 for selector in ['#price_ht','#price_ttc','#qty']:
  field=page.locator(selector)
  if field.count() and field.is_visible():assert field.bounding_box()['width']>=70,'Line amount field clips its content'
 page.screenshot(path=str(out/'order-line.png'),full_page=True)
 for width in [1440,390]:
  page.set_viewport_size({'width':width,'height':1000})
  page.goto(base+'/societe/card.php?action=create',wait_until='networkidle')
  assert page.locator('.indy-form-grid').count()>0
  assert page.evaluate('() => document.documentElement.scrollWidth <= innerWidth')
  page.screenshot(path=str(out/f'new-client-{width}.png'),full_page=True)
 # Rich tooltip regression: native invoice model capabilities and free-text help.
 for width in [1440,390,320]:
  page.set_viewport_size({'width':width,'height':1000})
  page.goto(base+'/admin/invoice.php',wait_until='networkidle')
  for name,trigger in [('sponge',page.locator('tr').filter(has=page.get_by_text('sponge',exact=True)).last.locator('.classfortooltip').last),('freetext',page.locator('[dolid="freetexttooltip"]'))]:
   if name == 'freetext':
    trigger.click();tip=page.locator('.ui-dialog:visible').last
   else:
    trigger.hover();tip=page.locator('div.ui-tooltip:visible').last
   tip.wait_for(state='visible')
   assert tip.inner_text().strip(),(name,width,'Empty info popup')
   assert tip.evaluate(r'''e=>{
    const lum=s=>{const c=s.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4});return c[0]*.2126+c[1]*.7152+c[2]*.0722};
    const r=e.getBoundingClientRect();
    if(r.left<0||r.right>innerWidth||r.width<200) return false;
    const text=[e,...e.querySelectorAll('*')].filter(x=>x.getBoundingClientRect().width>1&&x.getBoundingClientRect().height>1&&[...x.childNodes].some(n=>n.nodeType===Node.TEXT_NODE&&n.textContent.trim()));
    return text.every(x=>{
     let p=x,bg;
     while(p){bg=getComputedStyle(p).backgroundColor;if(bg!=='rgba(0, 0, 0, 0)'&&bg!=='transparent')break;p=p.parentElement;}
     const a=lum(getComputedStyle(x).color),b=lum(bg||'rgb(255,255,255)');
     return (Math.max(a,b)+.05)/(Math.min(a,b)+.05)>=4.5;
    });
   }'''),(name,width,'Unreadable or overflowing info popup')
   page.screenshot(path=str(out/f'tooltip-{name}-{width}.png'),full_page=True)
   page.mouse.move(0,0);page.keyboard.press('Escape')
 # Also inspect rich tables and links in a native jQuery tooltip variant.
 page.set_viewport_size({'width':1440,'height':1000})
 page.evaluate('''()=>{
  const b=document.createElement('button');b.id='indy-tooltip-fixture';b.textContent='Aide de démonstration';document.querySelector('#id-right').prepend(b);
  jQuery(b).tooltip({items:'button',classes:{'ui-tooltip':'jquery-tooltip --dark-mode'},content:'<table><tr><td>Compte bancaire</td><td><a href="#">Consulter le compte</a></td></tr></table>'});
 }''')
 page.locator('#indy-tooltip-fixture').hover()
 tip=page.locator('div.ui-tooltip:visible').last;tip.wait_for(state='visible')
 assert tip.evaluate('e=>getComputedStyle(e).backgroundColor')=='rgb(255, 255, 255)'
 assert tip.locator('a').evaluate('e=>getComputedStyle(e).color')=='rgb(53, 77, 97)'
 page.mouse.move(0,0);page.keyboard.press('Escape')
 print('PASS: six native information tooltips at desktop/390/320px and rich jQuery content, text/link contrast and viewport bounds.',flush=True)
 # Touch devices use Dolibarr's native click-to-open information dialog.
 touch=b.new_page(viewport={'width':390,'height':844},is_mobile=True,has_touch=True,user_agent='Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36')
 touch.goto(base+'/');touch.locator('#username').fill('admin');touch.locator('#password').fill(os.environ['DOLIBARR_TEST_PASSWORD']);touch.locator('input[type=submit]').first.tap();touch.wait_for_load_state('networkidle')
 touch.goto(base+'/admin/invoice.php',wait_until='networkidle')
 touch.locator('[dolid="freetexttooltip"]').tap()
 dialog=touch.locator('.ui-dialog:visible');dialog.wait_for(state='visible')
 assert dialog.inner_text().strip()
 assert dialog.evaluate('e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth}')
 assert dialog.locator('.ui-dialog-content').evaluate('e=>getComputedStyle(e).color')=='rgb(39, 51, 62)'
 touch.screenshot(path=str(out/'tooltip-help-touch.png'),full_page=True)
 touch.close()
 b.close()
(out/'audit-report.json').write_text(json.dumps(report,indent=2))
passed=sum(r['status']=='passed' for r in report)
print(f'PASS: {passed} additional page/viewport checks, filter submit/reset/date behaviour, field associations, product/stock contrast and entry forms.')
