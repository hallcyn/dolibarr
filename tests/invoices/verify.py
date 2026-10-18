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
        assert ('Commande' if name in ('order', 'long-order') else 'Facture') in page.get_text(), (name, index, 'Repeated document heading missing')
        assert 'Siège social' in page.get_text(), (name, index, 'Native footer missing')
        # Check the rendered banner, rather than merely a hidden PDF drawing.
        banner = page.get_pixmap(clip=fitz.Rect(3, 3, 9, 9), alpha=False)
        pixel = banner.pixel(2, 2)
        expected_color = (42,99,75) if name == 'banner' else ((237,245,239) if name == 'sage' else (252,235,216))
        assert all(abs(a-b)<4 for a,b in zip(pixel,expected_color)), (name,index,'Banner missing',pixel)
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
    if name not in ('unicode', 'serif'):
        assert any('Inter' in font[3] for font in document[0].get_fonts()), 'Inter is not embedded'
    if name not in ('order', 'long-order'):
        blocks=[(i,b) for i,p in enumerate(document) for b in p.get_text('blocks')]
        category=[(i,b) for i,b in blocks if "Catégorie d'opérations" in b[4]]
        notes=[(i,b) for i,b in blocks if 'Projet de démonstration.' in b[4] or 'Note publique de démonstration' in b[4]]
        assert category and notes, (name,'Category or public note missing')
        assert category[0][0]>notes[0][0] or category[0][1][1]>notes[0][1][3]+10, (name,'Category overlaps public note')
    if name == 'sage':
        spans=[s for b in document[0].get_text('dict')['blocks'] if 'lines' in b for l in b['lines'] for s in l['spans']]
        assert any('Désignation' in s['text'] and s['color']==0xffffff for s in spans), 'Dark table headings need light text'
    if name == 'long-order':
        assert len(document)>=2
        assert 'Commande ligne 35' in text
print('PASS: twelve PDF scenarios, native invoice/order totals, category-note separation, palette contrast, banner image, multi-page documents and embedded fonts.')
