"""Run only against the disposable fixture, after generate.php."""
import os
from pathlib import Path
from playwright.sync_api import sync_playwright

base = os.environ.get('DOLIBARR_TEST_URL', 'http://127.0.0.1:8080')
artifacts = Path('artifacts/invoices')
artifacts.mkdir(parents=True, exist_ok=True)
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get('PLAYWRIGHT_CHROMIUM_EXECUTABLE'))
    page = browser.new_page(viewport={'width': 1440, 'height': 1000}, locale='fr-FR')
    page.goto(base + '/', wait_until='networkidle')
    page.locator('#username').fill('admin')
    page.locator('#password').fill(os.environ['DOLIBARR_TEST_PASSWORD'])
    page.locator('input[type="submit"]').first.click()
    page.wait_for_load_state('networkidle')
    response = page.goto(base + '/custom/indyinvoice/admin/setup.php', wait_until='networkidle')
    assert response.status == 200
    assert 'Présentation des factures Indy' in page.locator('body').inner_text()
    page.locator('#INDYINVOICE_FONT').select_option('dejavusans')
    page.locator('input[name="make_default"]').check()
    page.locator('input[type="submit"]').click()
    page.wait_for_load_state('networkidle')
    assert page.locator('#INDYINVOICE_FONT').input_value() == 'dejavusans'
    assert 'saved=1' in page.url
    page.screenshot(path=str(artifacts / 'settings-desktop.png'), full_page=True)
    page.goto(base + '/admin/invoice.php', wait_until='networkidle')
    assert 'indy' in page.locator('body').inner_text().lower(), 'Native invoice model list does not expose indy'
    page.set_viewport_size({'width': 390, 'height': 844})
    page.goto(base + '/custom/indyinvoice/admin/setup.php', wait_until='networkidle')
    assert page.evaluate('() => document.documentElement.scrollWidth <= innerWidth'), 'Mobile settings overflow'
    page.screenshot(path=str(artifacts / 'settings-mobile.png'), full_page=True)
    browser.close()
print('PASS: native invoice model registration, appearance settings, saved values and mobile settings.')
