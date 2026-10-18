"""Validate actual Dolibarr-generated PDFs, including a multi-page invoice."""
import json
from collections import Counter
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
        if name != 'long-invoice' or index == 0: assert ('Commande' if name in ('order', 'order-shared', 'order-own', 'order-bank', 'long-order') else 'Facture') in page.get_text(), (name, index, 'Repeated document heading missing')
        assert 'Siège social' in page.get_text(), (name, index, 'Native footer missing')
        # Check the rendered banner, rather than merely a hidden PDF drawing.
        banner = page.get_pixmap(clip=fitz.Rect(3, 3, 9, 9), alpha=False)
        pixel = banner.pixel(2, 2)
        expected_color = (42,99,75) if name == 'banner' else ((237,245,239) if name == 'sage' else ((69,32,96) if name == 'text-colors' else (252,235,216)))
        if name == 'long-invoice' and index > 0: expected_color = (234,244,238)
        assert all(abs(a-b)<4 for a,b in zip(pixel,expected_color)), (name,index,'Banner missing',pixel)
        if name in ('sage', 'long-invoice', 'mentions-long-pages', 'long-order', 'grid'):
            # Empty content and margin areas must share the chosen paper color,
            # including pages whose native header is intentionally hidden.
            paper=(255,240,245) if name=='grid' else (234,244,238)
            for x,y in [(1,180),(30,180),(105,230),(207,270)]:
                swatch=page.get_pixmap(clip=fitz.Rect(x*72/25.4,y*72/25.4,(x+2)*72/25.4,(y+2)*72/25.4),alpha=False)
                px=Counter(tuple(swatch.samples[i:i+3]) for i in range(0,len(swatch.samples),3)).most_common(1)[0][0]
                assert all(abs(a-b)<4 for a,b in zip(px,paper)), (name,index,'Hardcoded white content background',x,y,px)
        for word in page.get_text('words'):
            assert word[0] >= -1 and word[1] >= -1 and word[2] <= page.rect.width + 1 and word[3] <= page.rect.height + 1, (name, index, 'Text outside the page')
    if name in ('long-invoice', 'mentions-long-pages'):
        assert len(document) >= 3
        for number in range(1, 49):
            assert re.search(r'Ligne\s*' + str(number) + r'\s*[—-]', text), ('Missing invoice line', number)
    if name == 'vat-discounts':
        assert '10%' in compact and 'TotalTVA20%' in compact and 'TotalTVA5,5%' in compact
    if name == 'credit-note':
        assert 'avoir' in text.lower() and '-144,00' in compact
    if name not in ('unicode', 'serif'):
        assert any('Inter' in font[3] for font in document[0].get_fonts()), 'Inter is not embedded'
    if name not in ('order', 'order-shared', 'order-own', 'order-bank', 'long-order'):
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
    if name.startswith('bank-') or name in ('mentions-long', 'mentions-long-pages', 'order-bank'):
        shown = name not in ('bank-card-hidden', 'bank-credit')
        assert ('IBAN' in text) == shown, (name, 'Unexpected native bank visibility')
        if shown:
            assert text.count('IBAN') == 1, (name, 'Duplicate bank block')
            chosen = 'B' if name == 'bank-document' else 'A'
            assert 'TITULAIRE FICTIF '+chosen in text, (name, 'Wrong native bank account')
            assert 'DEMOFRPPXXX' in text, (name, 'BIC missing')
    if name in ('mentions-long', 'mentions-long-pages'):
        for page in [document[-1]]:
            assert 'FIN DES CONDITIONS TEST.' in re.sub(r'\s+', ' ', page.get_text()), (name, 'Truncated long mention')
            legal=page.search_for('Siège social')[0]
            end=max(page.search_for('FIN DES CONDITIONS TEST.'), key=lambda r:r.y1)
            start=page.search_for('CONDITIONS DE DÉMONSTRATION.')[0]
            assert end.y1<legal.y0, (name, 'Mention overlaps legal footer')
            # No line items, payment or totals in the reserved text area.
            for b in page.get_text('blocks'):
                if 'Total TTC' in b[4] or 'IBAN' in b[4] or 'Ligne ' in b[4]:
                    assert b[3] < start.y0, (name, 'Content overlaps the free text')
        if name == 'mentions-long': assert 'BROUILLON TEST' in text
    if name == 'mentions-html': assert 'FIN MENTION HTML.' in text and '<b>' not in text
    if name == 'order': assert 'MENTION FACTURE PARTAGÉE' not in text
    if name == 'order-shared': assert 'MENTION FACTURE PARTAGÉE' in text
    if name == 'order-own': assert 'MENTION COMMANDE PRIORITAIRE' in text and 'MENTION FACTURE PARTAGÉE' not in text
    if name == 'text-colors':
        spans=[s for b in document[0].get_text('dict')['blocks'] if 'lines' in b for l in b['lines'] for s in l['spans']]
        for fragment,color in [('Facture',0xfff4db),('Désignation',0x354080),('Accompagnement',0x452060),('Total net avant remise',0x452060),('Total TTC',0xfff4db),('Données fictives',0x354080),('Maison Démonstration',0x452060)]:
            matching=[s for s in spans if fragment in s['text']]
            assert matching and any(s['color']==color for s in matching), (name, fragment, 'Text color not applied', matching)
print(f'PASS: {len(manifest)} PDF scenarios; native bank rules, account priority, wrapped/HTML mentions, separate order settings, custom text colors, native totals and multi-page layout.')
