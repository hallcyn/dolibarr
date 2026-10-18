"""Validate actual Dolibarr-generated PDFs, including a multi-page invoice."""
import json
import re
from pathlib import Path
import fitz

root = Path('artifacts/invoices')
manifest = json.loads((root / 'manifest.json').read_text())


def amount(value):
    return f'{value:.2f}'.replace('.', ',')


for name, expected in manifest.items():
    document = fitz.open(root / f'{name}.pdf')
    assert 1 <= len(document) <= 10, name
    text = '\n'.join(page.get_text() for page in document)
    compact = re.sub(r'\s+', '', text)
    assert 'TotalHT' in compact and 'TotalTTC' in compact, name
    assert amount(expected['ht']) in compact and amount(expected['ttc']) in compact, (name, 'Totals missing')
    assert 'Données fictives' in text and 'Maison Démonstration' in text, (name, 'Fixture information missing')
    assert "Un mode de paiement a été défini" not in text, (name, 'Incomplete payment fixture')
    for index, page in enumerate(document):
        assert 'Facture' in page.get_text(), (name, index, 'Repeated document heading missing')
        assert 'Siège social' in page.get_text(), (name, index, 'Native footer missing')
        # Check the rendered banner, rather than merely a hidden PDF drawing.
        banner = page.get_pixmap(clip=fitz.Rect(3, 3, 9, 9), alpha=False)
        pixel = banner.pixel(2, 2)
        assert pixel[0] > 240 and 215 < pixel[1] < 245 and 190 < pixel[2] < 230, (name, index, 'Banner missing')
        for word in page.get_text('words'):
            assert word[0] >= -1 and word[1] >= -1 and word[2] <= page.rect.width + 1 and word[3] <= page.rect.height + 1, (name, index, 'Text outside the page')
    if name == 'long-invoice':
        assert len(document) >= 3
        for number in range(1, 49):
            assert re.search(r'Ligne\s*' + str(number) + r'\s*[—-]', text), ('Missing invoice line', number)
    if name == 'vat-discounts':
        assert '10%' in compact and 'TotalTVA20%' in compact and 'TotalTVA5,5%' in compact
    if name == 'credit-note':
        assert 'avoir' in text.lower() and '-144,00' in compact
    if name != 'unicode':
        assert any('Inter' in font[3] for font in document[0].get_fonts()), 'Inter is not embedded'
print('PASS: six PDF scenarios, native totals, VAT, discounts, credit notes, 48 lines, repeated banners, footers, embedded fonts and page bounds.')
