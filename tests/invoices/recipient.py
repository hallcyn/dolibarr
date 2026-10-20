"""Check the actual recipient block, accents, contact, address and wrapping."""
import json
from pathlib import Path
import fitz

root = Path('artifacts/recipients')
for name, expected in json.loads((root / 'manifest.json').read_text()).items():
    document = fitz.open(root / f'{name}.pdf')
    text = document[0].get_text()
    normalized = ' '.join(text.split())
    assert expected['recipient'] in normalized, (name, 'Wrong recipient name', normalized)
    if expected['custom']:
        expected_shipping = 1 if name.startswith('order-') else 0
        assert normalized.count('ALBER Frédéric et Isabelle') == expected_shipping, (name, 'Billing replacement or native shipping changed', normalized)
    assert 'Camille' in text and 'Contact distinct' in text, (name, 'Native contact lost')
    assert 'Maison émettrice' in text, (name, 'Sender replaced')
    assert '17 rue des Vignes' in text and 'Lyon' in text, (name, 'Address lost')
    for page in document:
        for word in page.get_text('words'):
            assert word[0] >= -1 and word[1] >= -1 and word[2] <= page.rect.width + 1 and word[3] <= page.rect.height + 1, (name, 'Text outside page')
print('PASS: 18 PDFs, custom/default/blank/whitespace/long names, accents, ISO/inverted layout, contacts and addresses.')
