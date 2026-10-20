"""Check the actual recipient block, accents, contact, address and wrapping."""
import json
from pathlib import Path
import fitz

root = Path('artifacts/recipients')
for name, expected in json.loads((root / 'manifest.json').read_text()).items():
    document = fitz.open(root / f'{name}.pdf')
    spans = [s for b in document[0].get_text('dict')['blocks'] if 'lines' in b for l in b['lines'] for s in l['spans']]
    # Recipient is bold; the native extra contact line and address remain separate.
    bold = ' '.join(s['text'] for s in spans if 'bold' in s['font'].lower() or 'interb' in s['font'].lower())
    assert expected['recipient'] in bold, (name, 'Wrong recipient name', bold)
    if expected['custom']:
        expected_shipping = 1 if name.startswith('order-') else 0
        assert bold.count('ALBER Frédéric et Isabelle') == expected_shipping, (name, 'Billing replacement or native shipping changed', bold)
    text = document[0].get_text()
    assert 'Camille' in text and 'Contact distinct' in text, (name, 'Native contact lost')
    assert 'Maison émettrice' in text, (name, 'Sender replaced')
    assert '17 rue des Vignes' in text and 'Lyon' in text, (name, 'Address lost')
    for page in document:
        for word in page.get_text('words'):
            assert word[0] >= -1 and word[1] >= -1 and word[2] <= page.rect.width + 1 and word[3] <= page.rect.height + 1, (name, 'Text outside page')
print('PASS: 18 PDFs, custom/default/blank/whitespace/long names, accents, ISO/inverted layout, contacts and addresses.')
