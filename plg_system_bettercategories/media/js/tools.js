/**
 * Better Categories for Gridbox — administrator tools: export / import / reset of the settings and
 * generating / deleting the tile thumbnails (com_ajax actions of the plugin).
 */
(() => {
  'use strict';

  const form = () => document.getElementById('style-form') || document.querySelector('form[name="adminForm"]');
  const T = (key, fallback) => {
    const text = window.Joomla && Joomla.Text ? Joomla.Text._('PLG_SYSTEM_BETTERCATEGORIES_TOOLS_' + key) : '';
    return text && text !== 'PLG_SYSTEM_BETTERCATEGORIES_TOOLS_' + key ? text : fallback;
  };
  const fill = (text, values) => text.replace(/%(\w+)%/g, (m, k) => (k in values ? values[k] : m));

  async function call(url, fields, withForm) {
    const body = new FormData();
    if (withForm) {
      for (const [key, value] of new FormData(form()).entries()) {
        if (key.startsWith('jform[params]')) body.append(key, value);
      }
      // a page of the administrator holds the settings of that page only: the plugin takes the others from the saved ones
      if (form().dataset.bcPartial) { body.append('bc_partial', '1'); body.append('bc_fields', form().dataset.bcFields || ''); }
    }
    Object.entries(fields).forEach(([k, v]) => body.append(k, v));
    body.append(Joomla.getOptions('csrf.token'), '1');
    const response = await fetch(url, { method: 'POST', body, credentials: 'same-origin' });
    if (!response.ok) throw new Error('HTTP ' + response.status);
    const json = await response.json();
    let result = json.data;
    while (Array.isArray(result)) result = result[0];
    if (!result) throw new Error(json.message || 'No response');
    if (result.error) throw new Error(result.error);
    return result;
  }

  function download(name, data) {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = name;
    document.body.appendChild(link);
    link.click();
    setTimeout(() => { URL.revokeObjectURL(link.href); link.remove(); }, 1000);
  }

  function setup(box) {
    const url = box.dataset.bctoolsUrl;
    const status = box.querySelector('.bcat-tools-status');
    const buttons = () => box.querySelectorAll('[data-bctools-action]');
    const busy = (on) => buttons().forEach((b) => { b.disabled = on; });
    const say = (text, bad) => { status.textContent = text; status.classList.toggle('text-danger', !!bad); };
    const fail = (e) => { busy(false); say(T('FAILED', 'Failed: ') + e.message, true); };

    box.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-bctools-action]');
      if (!button) return;
      const action = button.dataset.bctoolsAction;

      if (action === 'export' || action === 'export_styles') {
        busy(true); say(T('WORKING', 'Working…'));
        try {
          const result = await call(url, { bc_action: 'export', scope: action === 'export' ? 'all' : 'styles' }, true);
          download(result.name, result.file);
          say(fill(T('EXPORT_DONE', 'Saved as %name%.'), { name: result.name }));
        } catch (e) { fail(e); return; }
        busy(false);
      }

      if (action === 'import') {
        box.querySelector('[data-bctools-file]').click();
      }

      if (action === 'reset') {
        const keep = box.querySelector('[data-bctools-keep]');
        if (!window.confirm(T('RESET_CONFIRM', 'Reset the settings to their defaults and save now? Unsaved changes in this form are lost.'))) return;
        busy(true); say(T('WORKING', 'Working…'));
        try {
          await call(url, { bc_action: 'reset', keep_site: keep && keep.checked ? '1' : '0' }, false);
          say(T('RESET_DONE', 'The defaults are restored. Reloading…'));
          setTimeout(() => window.location.reload(), 900);
        } catch (e) { fail(e); }
      }

      if (action === 'thumbs') {
        busy(true);
        try {
          for (let step = 0; step < 100; step++) {
            const r = await call(url, { bc_action: 'thumbs' }, true);
            say(fill(T(r.done ? 'THUMBS_DONE' : 'THUMBS_RUNNING', r.done ? 'Done: %ready% of %total% images have thumbnails.' : 'Working… %ready% of %total% images'), r));
            if (r.done) break;
          }
        } catch (e) { fail(e); return; }
        busy(false);
      }

      if (action === 'thumbs_clear') {
        if (!window.confirm(T('THUMBS_CLEAR_CONFIRM', 'Delete all thumbnails?'))) return;
        busy(true);
        try {
          const r = await call(url, { bc_action: 'thumbs_clear' }, false);
          say(fill(T('THUMBS_CLEARED', 'Deleted %deleted% files.'), r));
        } catch (e) { fail(e); return; }
        busy(false);
      }
    });

    const file = box.querySelector('[data-bctools-file]');
    file?.addEventListener('change', async () => {
      const chosen = file.files && file.files[0];
      file.value = '';
      if (!chosen) return;
      if (chosen.size > 262144) { say(T('FAILED', 'Failed: ') + '256 KB', true); return; }
      const stylesOnly = box.querySelector('[data-bctools-styles]').checked;
      const filtersOnly = !!box.querySelector('[data-bctools-filters]')?.checked;
      if (!window.confirm(filtersOnly
        ? T('IMPORT_FILTERS_CONFIRM', 'Add the filters of this file to the saved ones? Unsaved changes in this form are lost.')
        : T('IMPORT_CONFIRM', 'Import the settings from this file? Unsaved changes in this form are lost.'))) return;
      busy(true); say(T('IMPORT_READING', 'Importing…'));
      try {
        const text = await chosen.text();
        const r = await call(url, { bc_action: 'import', scope: filtersOnly ? 'filters' : (stylesOnly ? 'styles' : 'all'), data: text }, false);
        say(filtersOnly
          ? fill(T('IMPORT_FILTERS_DONE', 'Filters added: %added%, replaced: %replaced%, removed: %removed%. Reloading…'), r) + (r.missing ? ' ' + r.missing : '')
          : fill(T('IMPORT_DONE', 'Imported %imported% settings. Reloading…'), r));
        if (filtersOnly && r.missing) { window.alert(r.missing); }
        setTimeout(() => window.location.reload(), 900);
      } catch (e) { fail(e); }
    });
  }

  // ---------------------------------------------------------------- help tooltips ("?" beside the option names)

  // The text sits next to its "?" and is shown by CSS (hover, keyboard focus) or by a click
  // (class is-open): no positioning script, so no administrator template can push it away.
  function initHelp(form, label) {
    let n = 0;
    const closeAll = (except) => {
      form.querySelectorAll('.bs-help-wrap.is-open').forEach((w) => {
        if (w !== except) {
          w.classList.remove('is-open');
          w.querySelector('.bs-help').setAttribute('aria-expanded', 'false');
        }
      });
    };
    const add = (scope) => {
      scope.querySelectorAll('.control-group').forEach((g) => {
        const head = g.querySelector('.control-label');
        const desc = g.querySelector('[id$="-desc"]');
        if (!head || !desc || !desc.textContent.trim() || head.querySelector('.bs-help-wrap')) return;
        const wrap = document.createElement('span');
        wrap.className = 'bs-help-wrap';
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'bs-help';
        b.textContent = '?';
        b.setAttribute('aria-label', label);
        b.setAttribute('aria-expanded', 'false');
        const tip = document.createElement('span');
        tip.className = 'bs-help-tip';
        tip.id = 'bs-help-tip-' + (++n);
        tip.innerHTML = (desc.querySelector('.form-text') || desc).innerHTML;
        b.setAttribute('aria-describedby', tip.id);
        wrap.append(b, tip);
        head.appendChild(wrap);
      });
    };
    form.addEventListener('click', (e) => {
      const b = e.target.closest && e.target.closest('.bs-help');
      if (!b) {
        if (!(e.target.closest && e.target.closest('.bs-help-tip'))) closeAll(null);
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      const wrap = b.parentNode;
      const open = !wrap.classList.contains('is-open');
      closeAll(wrap);
      wrap.classList.toggle('is-open', open);
      b.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      closeAll(null);
      if (document.activeElement && document.activeElement.classList.contains('bs-help')) document.activeElement.blur();
    });
    add(form);
    document.addEventListener('subform-row-add', (e) => add((e.detail && e.detail.row) || e.target));
  }

  // "Select all / Clear all" above the parameter tick boxes of the filters (also in rows added later)
  function initDims(scope) {
    scope.querySelectorAll('.bcf-dims').forEach((box) => {
      if (box.dataset.bcfDims) return;
      box.dataset.bcfDims = '1';
      const bar = document.createElement('div');
      bar.className = 'bcf-dims-tools mb-2';
      [['ALL', 'Select all', true], ['NONE', 'Clear all', false]].forEach(([key, fallback, on]) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-sm btn-outline-secondary me-2';
        b.textContent = T('SELECT_' + key, fallback);
        b.addEventListener('click', () => {
          box.querySelectorAll('input[type="checkbox"]').forEach((c) => {
            if (c.checked !== on) { c.checked = on; c.dispatchEvent(new Event('change', { bubbles: true })); }
          });
        });
        bar.appendChild(b);
      });
      box.insertBefore(bar, box.firstChild);
    });
  }

  // ---------------------------------------------------------------- compact list of filters
  // Every filter row of the list folds to one line with a summary (type, title, options, name in the
  // address, categories); a click opens it. Above the list: fold / open all and a search. Rows start
  // folded; a row added with "+" starts open.
  const optText = (select) => select ? [...select.selectedOptions].filter((o) => o.value !== '').map((o) => o.textContent.replace(/^[\s\-–—]+|\s*\(\w+\)$/g, '').replace(/^.*?:\s*[\s\-–—]*/, '').trim()).filter(Boolean) : [];

  function rowSummary(row) {
    const q = (name) => row.querySelector('[name$="[' + name + ']"]') || row.querySelector('[name$="[' + name + '][]"]');
    const type = q('type');
    const typeText = type && type.selectedOptions[0] ? type.selectedOptions[0].textContent.trim() : '';
    const t = type ? type.value : '';
    let title = (q('label') || {}).value || '';
    let detail = '';
    if (t === 'field') {
      const field = optText(q('field'))[0] || '';
      title = title || field;
      detail = field && field !== title ? field : '';
    } else if (t === 'param') {
      const dims = [...row.querySelectorAll('.bcf-dims input:checked')].map((c) => (c.closest('div,label') || c).textContent.trim()).filter(Boolean);
      detail = dims.length > 3 ? dims.slice(0, 3).join(', ') + ' +' + (dims.length - 3) : dims.join(', ');
    } else if (t === 'feature') {
      const lines = ((q('features') || {}).value || '').split(/\r?\n/).filter((l) => l.trim() && !l.trim().startsWith('#'));
      detail = fill(T('ROWS_OPTIONS', '%count% options'), { count: lines.length });
    }
    const key = ((q('key') || {}).value || '').trim();
    const only = optText(row.querySelector('select[name$="[categories][]"]'));
    const not = optText(row.querySelector('select[name$="[exclude][]"]'));
    const where = only.length ? T('ROWS_ONLY', 'only') + ': ' + only.join(', ') : T('ROWS_EVERYWHERE', 'all categories');
    return { type: typeText, title: title || T('ROWS_AUTO', '(automatic title)'), detail, key: key ? '?f-' + key : '', where: where + (not.length ? ' · ' + T('ROWS_NOT', 'not') + ': ' + not.join(', ') : '') };
  }

  function foldRow(row, open) {
    row.classList.toggle('bcf-row-folded', !open);
    const head = row.querySelector(':scope > .bcf-row-head');
    if (head) head.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function setupRow(row, open) {
    if (!row || row.bcfHead) return;
    const head = document.createElement('button');
    head.type = 'button';
    head.className = 'bcf-row-head';
    head.innerHTML = '<span class="bcf-row-arrow" aria-hidden="true"></span><span class="bcf-row-type"></span><strong class="bcf-row-title"></strong>'
      + '<span class="bcf-row-detail"></span><code class="bcf-row-key"></code><span class="bcf-row-where"></span>';
    row.insertBefore(head, row.firstChild);
    row.bcfHead = head;
    const paint = () => {
      const s = rowSummary(row);
      head.querySelector('.bcf-row-type').textContent = s.type;
      head.querySelector('.bcf-row-title').textContent = s.title;
      head.querySelector('.bcf-row-detail').textContent = s.detail;
      head.querySelector('.bcf-row-key').textContent = s.key;
      head.querySelector('.bcf-row-where').textContent = s.where;
      row.dataset.bcfSearch = [s.type, s.title, s.detail, s.key, s.where].join(' ').toLowerCase();
    };
    head.addEventListener('click', () => foldRow(row, row.classList.contains('bcf-row-folded')));
    row.addEventListener('change', paint);
    row.addEventListener('input', paint);
    paint();
    // the fancy category lists fill their selection a moment later
    setTimeout(paint, 400);
    foldRow(row, open);
  }

  function initFilterRows() {
    const list = [...document.querySelectorAll('joomla-field-subform')].find((x) => (x.getAttribute('name') || '').endsWith('[filters_list]'));
    if (!list || list.dataset.bcfRows) return;
    list.dataset.bcfRows = '1';
    list.classList.add('bcf-rows');
    const rows = () => [...list.querySelectorAll('.subform-repeatable-group')].filter((g) => g.closest('joomla-field-subform') === list);
    rows().forEach((r) => setupRow(r, false));

    const bar = document.createElement('div');
    bar.className = 'bcf-rows-bar';
    bar.innerHTML = '<input type="search" class="form-control form-control-sm bcf-rows-search">'
      + '<button type="button" class="btn btn-sm btn-outline-secondary" data-bcf-fold="0"></button>'
      + '<button type="button" class="btn btn-sm btn-outline-secondary" data-bcf-fold="1"></button><span class="bcf-rows-count small text-muted"></span>';
    const search = bar.querySelector('input');
    search.placeholder = T('ROWS_SEARCH', 'Find a filter…');
    search.setAttribute('aria-label', search.placeholder);
    bar.querySelector('[data-bcf-fold="0"]').textContent = T('ROWS_OPEN_ALL', 'Open all');
    bar.querySelector('[data-bcf-fold="1"]').textContent = T('ROWS_FOLD_ALL', 'Fold all');
    const count = bar.querySelector('.bcf-rows-count');
    const filter = () => {
      const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);
      let shown = 0;
      rows().forEach((r) => {
        const hit = words.every((w) => (r.dataset.bcfSearch || '').includes(w));
        r.classList.toggle('bcf-row-hidden', !hit);
        shown += hit ? 1 : 0;
      });
      count.textContent = fill(T('ROWS_COUNT', '%shown% of %all% filters'), { shown, all: rows().length });
    };
    search.addEventListener('input', filter);
    bar.addEventListener('click', (e) => {
      const b = e.target.closest('[data-bcf-fold]');
      if (b) rows().forEach((r) => { if (!r.classList.contains('bcf-row-hidden')) foldRow(r, b.dataset.bcfFold === '0'); });
    });
    list.parentNode.insertBefore(bar, list);
    filter();
    document.addEventListener('subform-row-add', (e) => {
      const row = (e.detail && e.detail.row) || e.target;
      if (row && row.closest && row.closest('joomla-field-subform') === list) { setupRow(row, true); filter(); }
    });
    document.addEventListener('subform-row-remove', () => setTimeout(filter, 0));
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!form()) return;
    initDims(form());
    initFilterRows();
    document.addEventListener('subform-row-add', (e) => initDims((e.detail && e.detail.row) || e.target));
    document.querySelectorAll('[data-bctools]').forEach(setup);
    const label = window.Joomla && Joomla.Text ? Joomla.Text._('PLG_SYSTEM_BETTERCATEGORIES_TOOLS_HELP') : '';
    initHelp(form(), label && label !== 'PLG_SYSTEM_BETTERCATEGORIES_TOOLS_HELP' ? label : 'Help');
  });
})();
