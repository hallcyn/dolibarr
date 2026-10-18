/* Hallcyn · GPL-3.0-or-later */
(() => {
  const form = document.querySelector('#indy-invoice-settings');
  if (!form) return;
  const paper = form.querySelector('.indy-paper');
  const names = ['BANNER_COLOR','TABLE_COLOR','ACCENT_COLOR','PAPER_COLOR'];
  const palettes = {
    cream:['FCEBD8','FCEBD8','D82B68','FFFFFF'],
    rose:['FFF0F5','C83B69','C83B69','FFF8FA'],
    sage:['EDF5EF','286347','286347','F4F8F3'],
    clay:['FCF0E9','A84830','A84830','FFF9F4'],
    minimal:['FFFFFF','F3F5F7','354D61','FFFFFF']
  };
  const contrast = color => {
    const rgb=color.slice(1).match(/../g).map(s=>parseInt(s,16));
    const l=rgb.reduce((n,c,i)=>{c/=255;return n+(c<=.04045?c/12.92:((c+.055)/1.055)**2.4)*[.2126,.7152,.0722][i];},0);
    const white=1.05/(l+.05),ink=(l+.05)/.081;
    return white>=4.5&&white>=ink?'#fff':ink>=4.5?'#27333e':'#000';
  };
  const preview = () => {
    const colors=names.map(n=>form.elements['INDYINVOICE_'+n].value);
    ['--doc-band','--doc-table','--doc-accent','--doc-paper'].forEach((n,i)=>paper.style.setProperty(n,colors[i]));
    const noBand=Number(form.elements.INDYINVOICE_BANNER_HEIGHT.value)===0;
    if (noBand) paper.style.setProperty('--doc-band',colors[3]);
    ['HEADER','TABLE','TOTAL'].forEach((zone,i)=>{
      const auto=form.elements['INDYINVOICE_'+zone+'_TEXT_AUTO'].checked;
      const input=form.elements['INDYINVOICE_'+zone+'_TEXT_COLOR'];
      input.style.opacity=auto?'.5':'1';
      const color=zone==='HEADER'&&noBand?form.elements.INDYINVOICE_TEXT_COLOR.value:(auto?contrast(colors[i]):input.value);
      paper.style.setProperty('--doc-'+zone.toLowerCase()+'-text',color);
    });
    paper.style.setProperty('--doc-text',form.elements.INDYINVOICE_TEXT_COLOR.value);
    paper.style.setProperty('--doc-footer-text',form.elements.INDYINVOICE_FOOTER_TEXT_COLOR.value);
    const fonts={indyinter:'Inter, sans-serif',helvetica:'Arial, sans-serif',dejavusans:'Arial, sans-serif',times:'Times New Roman, serif',dejavuserif:'Georgia, serif'};
    paper.style.fontFamily=fonts[form.elements.INDYINVOICE_FONT.value];
    form.querySelector('.indy-preview-header').style.paddingBlock=Number(form.elements.INDYINVOICE_BANNER_HEIGHT.value)*.65+'px';
  };
  form.querySelectorAll('[data-palette]').forEach(button=>button.addEventListener('click',()=>{
    palettes[button.dataset.palette].forEach((value,i)=>form.elements['INDYINVOICE_'+names[i]].value='#'+value);
    ['HEADER','TABLE','TOTAL'].forEach(zone=>form.elements['INDYINVOICE_'+zone+'_TEXT_AUTO'].checked=true);
    preview();
  }));
  form.addEventListener('input',preview); form.addEventListener('change',preview);
  form.elements.banner_image.addEventListener('change',()=>{
    const file=form.elements.banner_image.files[0];
    if (!file) return;
    const photo=form.querySelector('.indy-preview-photo');
    photo.src=URL.createObjectURL(file);photo.hidden=false;form.elements.use_banner.checked=true;
  });
  form.elements.use_banner.addEventListener('change',()=>{form.querySelector('.indy-preview-photo').hidden=!form.elements.use_banner.checked;});
  preview();
})();
