"""Exact PDF sheet geometry and three-line label placement, not a mocked renderer."""
import json, math
from pathlib import Path
import fitz
root=Path('artifacts/labels')
mm=72/25.4

def visible_lines(page, area):
    # TCPDF includes an invisible (alpha=0) attribution at the bottom of the PDF.
    # Check printed label content, preserving this native library metadata.
    result=[]
    for block in page.get_text('dict',clip=area)['blocks']:
        for line in block.get('lines',[]):
            spans=[span for span in line['spans'] if span.get('alpha',255)>0]
            if spans:result.append({**line,'spans':spans})
    return result
for case,data in json.loads((root/'manifest.json').read_text()).items():
    doc=fitz.open(root/(case+'.pdf'))
    assert len(doc)==math.ceil((len(data['recipients'])+data['start']-1)/16),case
    for page in doc:
        assert abs(page.rect.width/mm-210)<0.02 and abs(page.rect.height/mm-297)<0.02,case
    for i,recipient in enumerate(data['recipients']):
        slot=i+data['start']-1; page=doc[slot//16]; pos=slot%16
        x=(pos%2)*105; y=0.5+(pos//2)*37
        area=fitz.Rect(x*mm,y*mm,(x+105)*mm,(y+37)*mm)
        lines=visible_lines(page,area)
        text=' '.join(' '.join(span['text'] for line in lines for span in line['spans']).split())
        for line in recipient['lines']:
            assert line in text,(case,pos,line,text)
        assert len(lines)==len(recipient['lines']),(case,pos,'Wrong line count',lines)
        for line in lines:
            assert line['bbox'][0]>=area.x0+(data.get('padding',10)-0.1)*mm and line['bbox'][2]<=area.x1-4*mm,(case,pos,'Horizontal overflow')
            assert line['bbox'][1]>area.y0 and line['bbox'][3]<area.y1,(case,pos,'Vertical overflow')
    if data['start']>1:
        page=doc[0]
        for pos in range(data['start']-1):
            x=(pos%2)*105;y=0.5+(pos//2)*37
            assert not visible_lines(page,fitz.Rect(x*mm,y*mm,(x+105)*mm,(y+37)*mm)),(case,'Used label overwritten')
print('PASS: exact A4, 105 × 37 mm cells, 2 × 8 layout, single/multiline addresses, custom/fallback names, empty start slots, pagination and no clipping.')
