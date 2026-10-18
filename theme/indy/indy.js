/* Hallcyn · GPL-3.0-or-later. Progressive presentation of native controls.
 * Move the existing filter nodes, never clone inputs or replace business logic.
 */
(() => {
  'use strict';
  function enhance() {
    let sequence = 0;
    document.querySelectorAll('#id-right tr.liste_titre_filter').forEach(row => {
      const table = row.closest('table');
      const form = row.closest('form');
      const heading = row.nextElementSibling;
      if (!table || !form || !heading || row.dataset.indyEnhanced) return;
      const cells = [...row.children];
      const titles = [...heading.children];
      if (cells.length !== titles.length) return; // Unknown native structure: retain it.
      const panel = document.createElement('details');
      panel.className = 'indy-filters';
      panel.open = window.innerWidth > 800;
      const summary = document.createElement('summary');
      summary.textContent = document.documentElement.lang.startsWith('fr') ? 'Filtres de la liste' : 'List filters';
      panel.append(summary);
      const grid = document.createElement('div');
      grid.className = 'indy-filter-grid';
      const actions = document.createElement('div');
      actions.className = 'indy-filter-actions';
      cells.forEach((cell, index) => {
        const controls = [...cell.querySelectorAll('input:not([type="hidden"]),select,button')];
        if (!controls.length) return;
        if (cell.classList.contains('actioncolumn') || controls.every(c => c.matches('button,input[type=submit],input[type=image]'))) {
          while (cell.firstChild) actions.append(cell.firstChild);
          return;
        }
        const field = document.createElement('div');
        field.className = 'indy-filter-field';
        const label = document.createElement('div');
        label.className = 'indy-filter-label';
        label.id = 'indy-filter-label-' + (++sequence);
        const title = titles[index].cloneNode(true);
        title.querySelectorAll('script,style,.dropdown,.select2-container').forEach(e => e.remove());
        label.textContent = (title.querySelector('a[href*="sortfield="]')?.textContent || title.textContent).trim();
        if (!label.textContent) return;
        const value = document.createElement('div');
        value.className = 'indy-filter-controls';
        value.setAttribute('role', 'group');
        value.setAttribute('aria-labelledby', label.id);
        controls.forEach(control => {
          if (!control.getAttribute('aria-label') && !control.labels?.length) {
            control.setAttribute('aria-label', label.textContent + (control.placeholder ? ' — ' + control.placeholder : ''));
          }
        });
        while (cell.firstChild) value.append(cell.firstChild);
        field.append(label, value);
        grid.append(field);
      });
      if (!grid.children.length) return;
      actions.querySelectorAll('button').forEach(button => {
        const label = document.createElement('span');
        label.textContent = button.name.includes('removefilter')
          ? (document.documentElement.lang.startsWith('fr') ? 'Réinitialiser' : 'Reset')
          : (document.documentElement.lang.startsWith('fr') ? 'Filtrer' : 'Apply filters');
        button.append(label);
      });
      const extra = form.querySelector('div.liste_titre_bydiv');
      if (extra && !extra.closest('.indy-filters')) {
        extra.classList.add('indy-filter-extra');
        panel.append(extra);
      }
      panel.append(grid, actions);
      const wrapper = table.closest('.div-table-responsive,.div-table-responsive-no-min');
      const anchor = wrapper && wrapper.closest('form') === form ? wrapper : table;
      anchor.before(panel);
      // Some native Select2s attach their popup to the original table cell.
      // Recreate only those widgets with the same native options/callbacks,
      // otherwise their popup would stay inside the now-hidden filter row.
      panel.querySelectorAll('select').forEach(control => {
        const jq = window.jQuery;
        const instance = jq ? jq(control).data('select2') : null;
        const parent = instance?.dropdown?.$dropdownParent?.[0];
        if (parent && parent !== document.body && !panel.contains(parent)) {
          const options = {...instance.options.options, dropdownParent: jq(control.parentElement)};
          jq(control).select2('destroy').select2(options);
        }
      });
      panel.querySelectorAll('input:not([type="hidden"]),select').forEach(control => {
        if (!control.getAttribute('aria-label') && !control.labels?.length) {
          const rendered = control.closest('.divsearchfield')?.querySelector('.select2-selection__rendered');
          control.setAttribute('aria-label', rendered?.title || rendered?.textContent.trim() || control.getAttribute('placeholder') || control.name.replace(/^search_/, '').replace(/_/g,' ') || 'Recherche');
        }
        const widget = control.nextElementSibling?.querySelector('.select2-selection');
        if (widget) widget.setAttribute('aria-label', control.getAttribute('aria-label') || 'Recherche');
      });

      row.dataset.indyEnhanced = '1';
      row.hidden = true;
    });
    // Only entry forms: detail cards and nested/native line tables stay intact.
    document.querySelectorAll('#id-right form').forEach(form => {
      if (!form.querySelector('input[name="action"][value="add"],input[name="action"][value="create"],input[name="action"][value="update"]')) return;
      form.querySelectorAll('table.border').forEach(table => {
        if (table.parentElement.closest('table') || !table.querySelector('input:not([type="hidden"]),select,textarea')) return;
        table.classList.add('indy-form-grid');
        [...table.tBodies].forEach(body => [...body.rows].forEach(row => {
          if (![2,4].includes(row.cells.length)) return;
          row.classList.add('indy-entry-row');
          if (row.cells.length === 4) row.classList.add('indy-entry-row-pairs');
          [...row.cells].forEach((cell,index) => cell.classList.add(index % 2 ? 'indy-entry-value' : 'indy-entry-label'));
        }));
      });
    });
  }
  // Dolibarr registers its Select2 initializers first, during DOM ready.
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => setTimeout(enhance, 0));
  else setTimeout(enhance, 0);
})();

/* 1.5 · responsive shell. Desktop nodes, controls and handlers are restored
 * when leaving this layout. Native form inputs are moved, never duplicated. */
(() => {
  'use strict';
  const media = matchMedia('(max-width:1099px), (max-width:1366px) and (pointer:coarse)');
  const fr = document.documentElement.lang.startsWith('fr');
  let cleanup = null;
  function mount() {
    const side = document.querySelector('#id-container > .side-nav');
    const top = document.querySelector('#id-top');
    const content = document.querySelector('#id-right');
    if (!side || !top || !content) return null;
    const undo = [], owned = [];
    const add = (node) => { owned.push(node); return node; };
    const attr = (el, name, value) => {
      const old = el.getAttribute(name);
      undo.push(() => old === null ? el.removeAttribute(name) : el.setAttribute(name, old));
      el.setAttribute(name, value);
    };
    const move = (node, parent, before = null) => {
      if (!node) return;
      const mark = document.createComment('indy responsive position');
      node.before(mark); parent.insertBefore(node, before);
      undo.push(() => { mark.replaceWith(node); });
    };
    const button = (text, className) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = className; b.textContent = text; return b;
    };
    document.body.classList.add('indy-responsive');
    document.querySelectorAll('.table-fiche-title.titleforhome').forEach(title=>{
      const controls=[...title.querySelectorAll('a,button,input,select')].some(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.height>0;});
      if(!controls&&!title.innerText.trim())attr(title,'data-indy-empty-title','1');
    });
    const bar = add(document.createElement('header')); bar.className = 'indy-appbar indy-r-only';
    const toggle = button(fr ? '☰ Menu' : '☰ Menu', 'indy-menu-toggle');
    toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-controls', 'indy-responsive-navigation');
    const brand = document.createElement('a'); brand.className = 'indy-appbar-brand'; brand.textContent = 'Dolibarr';
    brand.href = top.querySelector('#mainmenua_home')?.href || top.querySelector('a[href]:not([href="#"])')?.href || location.href;
    const sub = document.createElement('small'); sub.textContent = top.querySelector('li.tmenusel .mainmenuaspan')?.textContent.trim() || (fr ? 'Mon espace' : 'Workspace');
    brand.append(sub);bar.append(toggle, brand); document.body.prepend(bar);
    const account = document.querySelector('#topmenu-login-dropdown');
    if (account) {
      move(account, bar);
      const trigger = account.querySelector('a.dropdown-toggle');
      if (trigger) { attr(trigger, 'aria-label', fr ? 'Mon compte' : 'My account'); attr(trigger, 'title', fr ? 'Mon compte' : 'My account'); }
      const panel=account.querySelector('.dropdown-menu');
      if(trigger&&panel){
        attr(trigger,'role','button');attr(trigger,'aria-haspopup','dialog');attr(trigger,'aria-controls','indy-account-panel');attr(trigger,'aria-expanded',String(account.classList.contains('open')));
        attr(panel,'id','indy-account-panel');attr(panel,'role','dialog');attr(panel,'aria-label',fr?'Mon compte':'My account');
        const toolbar=add(document.createElement('div'));toolbar.className='indy-account-toolbar';
        const heading=document.createElement('strong');heading.textContent=fr?'Mon compte':'My account';
        const dismiss=button('×','indy-account-close');dismiss.setAttribute('aria-label',fr?'Fermer le compte':'Close account');toolbar.append(heading,dismiss);panel.prepend(toolbar);
        const closeAccount=(restore=false)=>{account.classList.remove('open');if(restore)trigger.focus({preventScroll:true});};
        dismiss.addEventListener('click',()=>closeAccount(true));
        const header=panel.querySelector('.user-header'),nativeName=header?.querySelector('p');
        if(header&&nativeName){
          const copy=nativeName.cloneNode(true);copy.querySelectorAll('small,br,.fa,.fas,.far,.fab').forEach(e=>e.remove());
          const text=copy.textContent.trim(),match=text.match(/^(.*?)\s*\(([^()]*)\)$/);
          const name=match?.[1]||text,login=match?.[2]||'';
          const identity=add(document.createElement('div'));identity.className='indy-account-person';
          const avatar=document.createElement('span');avatar.className='indy-account-avatar';avatar.setAttribute('aria-hidden','true');
          const photo=header.querySelector('img.dropdown-user-image:not([aria-hidden=true])');
          if(photo)avatar.append(photo.cloneNode(true));
          else avatar.textContent=name.split(/\s+/).filter(Boolean).slice(0,2).map(v=>[...v][0]).join('').toLocaleUpperCase().slice(0,2)||'•';
          const info=document.createElement('div'),title=document.createElement('strong'),meta=document.createElement('small');
          title.textContent=name;meta.textContent=[login?'@'+login:'',nativeName.querySelector('.fa-star')?(fr?'Administrateur':'Administrator'):''].filter(Boolean).join(' · ');
          info.append(title,meta);identity.append(avatar,info);header.append(identity);
        }
        const hideNativeText=element=>[...element.childNodes].filter(n=>n.nodeType===Node.TEXT_NODE&&n.textContent.trim()).forEach(node=>{
          const legacy=add(document.createElement('span'));legacy.className='indy-account-legacy-label';node.before(legacy);move(node,legacy);
        });
        const footer=panel.querySelector('.user-footer'),body=panel.querySelector('.user-body');
        if(footer&&body)move(footer,panel,body);
        for(const [key,kind,label] of [['u','profile',fr?'Mon profil':'My profile'],['v','card',fr?'Ma carte de visite':'My business card'],['l','logout',fr?'Se déconnecter':'Sign out']]){
          const link=footer?.querySelector('a[accesskey="'+key+'"]');if(!link)continue;
          hideNativeText(link);
          attr(link,'data-indy-account-action',kind);attr(link,'aria-label',label);attr(link,'title',label);
          const caption=add(document.createElement('span'));caption.className='indy-account-action-label';caption.textContent=label;link.append(caption);
        }
        const expandedObservers=[];
        for(const [id,label] of [['companyinfo',fr?'Entreprise':'Company'],['moreinfo',fr?'Informations de session':'Session information']]){
          const control=panel.querySelector('#topmenulogin'+id+'-btn'),details=panel.querySelector('#topmenulogin'+id);if(!control||!details)continue;
          hideNativeText(control);attr(control,'aria-label',label);
          attr(control,'role','button');attr(control,'tabindex','0');attr(control,'aria-controls',details.id);attr(control,'aria-expanded',String(getComputedStyle(details).display!=='none'));
          const caption=add(document.createElement('span'));caption.className='indy-account-disclosure-label';caption.textContent=label;control.append(caption);
          const keys=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();control.click();}};
          control.addEventListener('keydown',keys);undo.push(()=>control.removeEventListener('keydown',keys));
          const observer=new MutationObserver(()=>control.setAttribute('aria-expanded',String(getComputedStyle(details).display!=='none')));
          observer.observe(details,{attributes:true,attributeFilter:['style']});expandedObservers.push(observer);
        }
        const observer=new MutationObserver(()=>{
          const isOpen=account.classList.contains('open');trigger.setAttribute('aria-expanded',String(isOpen));
          if(isOpen)dismiss.focus({preventScroll:true});
        });observer.observe(account,{attributes:true,attributeFilter:['class']});
        const accountKey=e=>{if(e.key==='Escape'&&account.classList.contains('open')){e.preventDefault();closeAccount(true);}else if(e.target===trigger&&e.key===' '){e.preventDefault();trigger.click();}};
        let accountActive=true;
        const accountFocus=()=>{setTimeout(()=>{if(accountActive&&account.isConnected&&!account.contains(document.activeElement))closeAccount();},0);};
        account.addEventListener('keydown',accountKey);account.addEventListener('focusout',accountFocus);
        const cardDialog=account.querySelector('#idfordialogpublicvirtualcardmenu');
        if(cardDialog&&window.jQuery){
          const restore=()=>trigger.focus({preventScroll:true});
          window.jQuery(cardDialog).on('dialogclose.indyAccount',restore);
          undo.push(()=>window.jQuery(cardDialog).off('dialogclose.indyAccount',restore));
        }
        undo.push(()=>{accountActive=false;observer.disconnect();expandedObservers.forEach(o=>o.disconnect());account.removeEventListener('keydown',accountKey);account.removeEventListener('focusout',accountFocus);closeAccount();});
      }
    }
    const backdrop = add(button(fr ? 'Fermer le menu' : 'Close menu', 'indy-nav-backdrop indy-r-only'));backdrop.tabIndex = -1;backdrop.hidden = true;
    document.body.append(backdrop);
    const head = add(document.createElement('div'));head.className = 'indy-drawer-head indy-r-only';
    const title = document.createElement('strong');title.textContent = fr ? 'Navigation' : 'Navigation';
    const close = button('×', 'indy-menu-close');close.setAttribute('aria-label', fr ? 'Fermer le menu' : 'Close menu');head.append(title,close);side.prepend(head);
    attr(side,'id','indy-responsive-navigation');attr(side,'role','dialog');attr(side,'aria-label',fr?'Menu de navigation':'Navigation menu');
    const hadInert = side.inert; side.inert = true;undo.push(() => { side.inert=hadInert; });
    const modules = add(document.createElement('nav')); modules.className='indy-module-nav indy-r-only';modules.setAttribute('aria-label',fr?'Modules':'Modules');
    top.querySelectorAll('li:not(.menuhider) > .tmenucenter > a.tmenulabel[href]:not([href="#"])').forEach(link => {
      if (!link.textContent.trim()) return;
      const item = document.createElement('a');item.href=link.href;
      if (link.closest('li').classList.contains('tmenusel')) { item.classList.add('is-current');item.setAttribute('aria-current','page'); }
      const iconBox=document.createElement('span');iconBox.className='indy-module-icon';iconBox.setAttribute('aria-hidden','true');
      const icon=link.parentElement.querySelector('.mainmenu > :is(span,img,svg)');if(icon)iconBox.append(icon.cloneNode(true));
      const label=document.createElement('span');label.className='indy-module-label';label.textContent=link.textContent.trim();item.append(iconBox,label);modules.append(item);
    });
    head.after(modules);
    const search = document.querySelector('#blockvmenusearch');if(search)move(search,side,modules);
    let open = false, overflow = '', previousFocus = null;
    function setOpen(next) {
      if(open===next)return;
      if(next)account?.classList.remove('open');
      open=next;toggle.setAttribute('aria-expanded',String(open));side.inert=!open;backdrop.hidden=!open;
      document.body.classList.toggle('indy-nav-open',open);
      if(open){previousFocus=document.activeElement;overflow=document.body.style.overflow;document.body.style.overflow='hidden';content.inert=true;side.setAttribute('aria-modal','true');close.focus();}
      else{document.body.style.overflow=overflow;content.inert=false;side.removeAttribute('aria-modal');if(previousFocus?.isConnected)previousFocus.focus({preventScroll:true});}
    }
    toggle.addEventListener('click',()=>setOpen(!open));close.addEventListener('click',()=>setOpen(false));backdrop.addEventListener('click',()=>setOpen(false));
    const key = e => {
      if(!open)return;
      if(document.querySelector('.select2-container--open'))return;
      if(e.key==='Escape'){e.preventDefault();setOpen(false);}
      if(e.key==='Tab'){
        const nodes=[...side.querySelectorAll('a[href],button,input,select,[tabindex="0"]')].filter(n=>n.getBoundingClientRect().width>0&&!n.disabled);
        const first=nodes[0],last=nodes[nodes.length-1];
        if(e.shiftKey&&document.activeElement===first){e.preventDefault();last?.focus();}
        else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first?.focus();}
      }
    };
    document.addEventListener('keydown',key);undo.push(()=>document.removeEventListener('keydown',key));
    document.querySelectorAll('.indy-filters').forEach(panel=>{
      const before=panel.open;panel.open=false;undo.push(()=>{panel.open=before;});
      const summary=panel.querySelector('summary');if(!summary)return;
      const badge=add(document.createElement('small'));badge.className='indy-filter-count';summary.append(badge);
      const update=()=>{
        const values=[...panel.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]),select,textarea')].filter(input=>{
          if(input.disabled)return false;
          if(input.type==='checkbox'||input.type==='radio')return input.checked;
          if(input.tagName==='SELECT')return [...input.selectedOptions].some(o=>o.value!==''&&o.value!=='-1'&&o.value!=='-2'&&!/^(all|any|tous|toutes|aucun|select|sélectionner|choisir)\b/i.test(o.textContent.trim()));
          return !!input.value.trim();
        }).length;
        badge.textContent=values?(values+(fr?' critère'+(values>1?'s':''):' filter'+(values>1?'s':''))):'';
      };
      update();panel.addEventListener('input',update);panel.addEventListener('change',update);
      undo.push(()=>{panel.removeEventListener('input',update);panel.removeEventListener('change',update);});
    });
    // Dolibarr puts overflow tabs in a hover-only popup on phones. Reuse
    // those actual links in a touch-scrollable strip instead.
    content.querySelectorAll('[id^=moretabs]:not([id^=moretabsList])').forEach(more=>{
      const list=more.querySelector('[id^=moretabsList]'),tabs=more.closest('.tabs');
      if(!list||!tabs)return;
      const links=[...list.querySelectorAll('a[href]')];if(!links.length)return;
      links.forEach(link=>{
        const wrapper=add(document.createElement('div'));wrapper.className='tab tabunactive';tabs.insertBefore(wrapper,more);
        attr(link,'class',link.className+' tab');move(link,wrapper);
      });
      attr(more,'data-indy-more-tabs','1');
    });
    let sequence=0;
    document.querySelectorAll('#id-right table.liste').forEach(table=>{
      if(table.querySelector('#qty,#price_ht')||table.closest('.ui-dialog'))return;
      const heading=table.querySelector('tr.liste_titre:not(.liste_titre_filter)');
      const rows=[...table.querySelectorAll(':scope > tbody > tr.oddeven')];
      if(!heading||!rows.length)return;
      const headers=[...heading.children];
      const labels=headers.map(h=>{const c=h.cloneNode(true);c.querySelectorAll('script,style,.dropdown').forEach(e=>e.remove());return c.textContent.trim();});
      const fields=headers.map(h=>{try{return new URL(h.querySelector('a[href*="sortfield="]')?.href||location.href).searchParams.get('sortfield')||'';}catch{return '';}});
      if(!rows.every(row=>row.children.length===headers.length&&[...row.children].every(c=>c.colSpan===1)&&[...row.children].some((c,i)=>labels[i]&&c.querySelector('a[href]:not([href="#"])'))))return;
      const tools=add(document.createElement('div'));tools.className='indy-list-tools indy-r-only';
      const selectAll=[...heading.querySelectorAll('input[type=checkbox]')].find(c=>!c.closest('.dropdown-menu')&&c.getBoundingClientRect().width>0&&c.getBoundingClientRect().height>0);
      if(selectAll){const label=document.createElement('label');label.textContent=fr?'Tout sélectionner':'Select all';tools.append(label);move(selectAll,label,label.firstChild);}
      const columns=heading.querySelector('.dropdown');
      if(columns){
        move(columns,tools);
        const trigger=columns.querySelector('a,button');
        if(trigger&&!trigger.getAttribute('aria-label'))attr(trigger,'aria-label',fr?'Choisir les colonnes':'Choose columns');
        const list=columns.querySelector('dd ul');
        if(trigger&&list){
          const actions=add(document.createElement('li'));actions.className='indy-column-actions';
          const title=document.createElement('strong');title.textContent=fr?'Colonnes affichées':'Visible columns';
          const done=button(fr?'Fermer':'Close','indy-column-close');
          actions.append(title,done);list.prepend(actions);
          // Use Dolibarr's existing toggle, which also saves changed column
          // choices through the native form when closing the popup.
          done.addEventListener('click',()=>{trigger.click();trigger.focus({preventScroll:true});});
          attr(trigger,'aria-expanded','false');
          const observer=new MutationObserver(()=>trigger.setAttribute('aria-expanded',String(list.classList.contains('open'))));
          observer.observe(list,{attributes:true,attributeFilter:['class']});undo.push(()=>observer.disconnect());
          const escape=e=>{if(e.key==='Escape'&&list.classList.contains('open')){e.preventDefault();done.click();}};
          document.addEventListener('keydown',escape);undo.push(()=>document.removeEventListener('keydown',escape));
        }
      }
      const sorting=document.createElement('select');sorting.setAttribute('aria-label',fr?'Trier la liste':'Sort list');
      const placeholder=document.createElement('option');placeholder.textContent=fr?'Trier la liste':'Sort list';placeholder.value='';sorting.append(placeholder);
      headers.forEach((h,i)=>{const link=h.querySelector('a[href*="sortfield="]');if(!link||!labels[i])return;for(const direction of ['ASC','DESC']){const option=document.createElement('option');const url=new URL(link.href);url.searchParams.set('sortorder',direction);option.value=url.href;option.textContent=labels[i]+' '+(direction==='ASC'?'↑':'↓');sorting.append(option);}});
      if(sorting.options.length>1){tools.append(sorting);sorting.addEventListener('change',()=>{if(sorting.value)location.assign(sorting.value);});}
      const wrapper=table.closest('.div-table-responsive,.div-table-responsive-no-min'),form=table.closest('form');
      (wrapper&&wrapper.closest('form')===form?wrapper:table).before(tools);
      table.classList.add('indy-card-list');undo.push(()=>table.classList.remove('indy-card-list'));
      rows.forEach(row=>{
        const cells=[...row.children];if(cells.length!==headers.length||cells.some(c=>c.colSpan>1))return;
        const primary=cells.findIndex((c,i)=>!!c.querySelector('a[href]:not([href="#"])')&&!!labels[i]);
        if(primary<0)return;
        const details=[];
        cells.forEach((cell,i)=>{
          if(cell.hidden||cell.style.display==='none'||cell.classList.contains('hideobject'))return;
          attr(cell,'data-indy-label',labels[i]);
          const checkbox=!!cell.querySelector('input[type=checkbox]');
          const important=i===primary||/\.(nom|name|label|lieu|phone|phone_mobile|email|price|stock|reel|qty|total_ttc|fk_statut|statut|status|datef|date_commande)$/.test(fields[i])||/^(état|statut|status|libellé|solde|stock|quantité)$/i.test(labels[i]);
          const kind=checkbox?'check':i===primary?'key':important?'summary':'detail';
          attr(cell,'data-indy-cell',kind);
          if(checkbox){
            const input=cell.querySelector('input[type=checkbox]');
            if(!input.closest('label')){
              const label=add(document.createElement('label'));label.className='indy-row-select';cell.append(label);move(input,label);
              if(!input.getAttribute('aria-label'))attr(input,'aria-label',(fr?'Sélectionner ':'Select ')+cells[primary].textContent.trim());
            }
          }
          if(kind==='detail')details.push(cell);
        });
        if(details.length){
          const expand=add(button(fr?'Détails':'Details','indy-row-details indy-r-only'));expand.setAttribute('aria-expanded','false');
          const id='indy-row-'+(++sequence);attr(row,'id',row.id||id);
          expand.addEventListener('click',()=>{const value=!row.classList.contains('indy-row-expanded');row.classList.toggle('indy-row-expanded',value);expand.setAttribute('aria-expanded',String(value));expand.textContent=value?(fr?'Réduire':'Collapse'):(fr?'Détails':'Details');});
          cells[primary].append(expand);undo.push(()=>row.classList.remove('indy-row-expanded'));
        }
      });
    });
    const qty=document.querySelector('#qty');const entry=qty?.closest('tr');
    if(entry&&entry.querySelector('input,textarea,select')){
      entry.querySelectorAll('#qty,#price_ht,#price_ttc,#remise_percent').forEach(input=>attr(input,'inputmode','decimal'));
      entry.classList.add('indy-mobile-line-entry');undo.push(()=>entry.classList.remove('indy-mobile-line-entry'));
      const nativeTable=entry.closest('table');
      [...entry.children].forEach(cell=>{
        const column=[...cell.classList].find(c=>c.startsWith('linecol'));
        const header=column?nativeTable.querySelector('th.'+column):null;
        const label=header?.textContent.trim();
        if(label){
          attr(cell,'data-indy-label',label);
          cell.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(input=>{if(!input.getAttribute('aria-label')&&!input.labels?.length)attr(input,'aria-label',label);});
        }
        if(cell.querySelector('textarea,.select2-container')){cell.classList.add('indy-line-description');undo.push(()=>cell.classList.remove('indy-line-description'));}
      });
      // Keep the real inputs and their native form/Select2 handlers, while
      // separating entry from the horizontally scrollable existing lines.
      const originalTable=entry.closest('table'),form=qty.form;
      const wrapper=originalTable.closest('.div-table-responsive,.div-table-responsive-no-min');
      const anchor=wrapper?.closest('form')===form?wrapper:originalTable;
      if(form&&form.contains(anchor)){
        const marker=document.createComment('indy line entry position');entry.before(marker);
        const editor=add(document.createElement('table'));editor.className='indy-line-editor indy-r-only';
        const body=document.createElement('tbody');editor.append(body);anchor.after(editor);
        const phone=matchMedia('(max-width:600px)');
        const reflow=()=>{if(phone.matches)body.append(entry);else marker.after(entry);};
        reflow();phone.addEventListener('change',reflow);
        undo.push(()=>{phone.removeEventListener('change',reflow);marker.replaceWith(entry);});
      }
    }
    const scrollObserver=new ResizeObserver(()=>{
      scrollHints.forEach(([wrapper,hint])=>{hint.hidden=wrapper.scrollWidth<=wrapper.clientWidth+2;});
    });
    const scrollHints=[];
    content.querySelectorAll('.div-table-responsive,.div-table-responsive-no-min').forEach(wrapper=>{
      const hint=add(document.createElement('p'));hint.className='indy-table-hint indy-r-only';hint.hidden=true;
      hint.textContent=fr?'↔ Faites glisser le tableau pour voir toutes les colonnes.':'↔ Swipe the table to see all columns.';
      wrapper.before(hint);scrollHints.push([wrapper,hint]);scrollObserver.observe(wrapper);
    });
    undo.push(()=>scrollObserver.disconnect());
    return ()=>{setOpen(false);undo.reverse().forEach(fn=>fn());owned.forEach(n=>n.remove());document.body.classList.remove('indy-responsive','indy-nav-open');};
  }
  function sync(){if(media.matches&&!cleanup)cleanup=mount();else if(!media.matches&&cleanup){cleanup();cleanup=null;}}
  function start(){sync();media.addEventListener('change',sync);}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(start,0));else setTimeout(start,0);
})();
