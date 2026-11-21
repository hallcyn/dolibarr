"""Exercise the payment-menu entry, period boundaries, allocations and real PDF."""
import os
from pathlib import Path

import fitz
from playwright.sync_api import sync_playwright

base = os.environ.get('DOLIBARR_TEST_URL', 'http://127.0.0.1:8080')
dest = Path('artifacts/invoices/receipts.pdf')
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path=os.environ.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE'))
    page = browser.new_page(locale='fr-FR')
    page.goto(base)
    page.locator('#username').fill('admin')
    page.locator('#password').fill(os.environ['DOLIBARR_TEST_PASSWORD'])
    page.locator('input[type=submit]').first.click()
    page.goto(base+'/compta/paiement/list.php?mainmenu=billing&leftmenu=customers_bills_payment')
    page.locator('a[href*="/indyreceipts/index.php"]').first.click()
    page.locator('[name=date_from]').fill('2026-11-01')
    page.locator('[name=date_to]').fill('2026-11-01')
    view = page.get_by_role('button', name='Afficher le rapport')
    view.click()
    blocks = page.locator('section.fichecenter')
    assert blocks.count() == 2
    first = blocks.filter(has_text='Chardonnay')
    assert first.get_by_role('row').filter(has_text='Chardonnay').inner_text().split()[-1] == '6'
    assert 'Maison des encaissements' in first.inner_text() and '0045821' in first.inner_text()
    assert 'VIR-381' not in first.inner_text() and '0045800' not in first.inner_text()
    page.locator('[name=settled]').check()
    view.click()
    assert blocks.count() == 1 and 'Pinot noir' in blocks.inner_text()
    page.locator('[name=date_to]').fill('2026-11-12')
    view.click()
    assert blocks.count() == 2
    with page.expect_download() as download:
        page.get_by_role('button', name='Télécharger le PDF').click()
    download.value.save_as(dest)
    with fitz.open(dest) as pdf:
        text = ' '.join(p.get_text() for p in pdf)
        for expected in ('Chardonnay', 'Livraison', 'Pinot noir', '0045821', 'VIR-381', '200'):
            assert expected in text, expected
        assert '0045800' not in text
    page.locator('[name=date_to]').fill('2026-10-31')
    view.click()
    assert 'chronologique' in page.get_by_role('alert').inner_text()
    page.locator('[name=date_from]').fill('2027-01-31')
    page.locator('[name=date_to]').fill('2027-01-31')
    view.click()
    assert 'Aucun règlement' in page.locator('body').inner_text()
    browser.close()
print('PASS: receipts menu, shared cheque, single day, end of day, settled cutoff, PDF, invalid and empty periods.')
