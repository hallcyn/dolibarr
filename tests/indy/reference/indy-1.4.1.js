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
