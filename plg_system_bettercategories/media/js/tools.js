/**
 * Better Categories for Gridbox — administrator tools: export / import of the settings and
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
      if (!window.confirm(T('IMPORT_CONFIRM', 'Import the settings from this file? Unsaved changes in this form are lost.'))) return;
      busy(true); say(T('IMPORT_READING', 'Importing…'));
      try {
        const text = await chosen.text();
        const r = await call(url, { bc_action: 'import', scope: stylesOnly ? 'styles' : 'all', data: text }, false);
        say(fill(T('IMPORT_DONE', 'Imported %imported% settings. Reloading…'), r));
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

  document.addEventListener('DOMContentLoaded', () => {
    if (!form()) return;
    document.querySelectorAll('[data-bctools]').forEach(setup);
    const label = window.Joomla && Joomla.Text ? Joomla.Text._('PLG_SYSTEM_BETTERCATEGORIES_TOOLS_HELP') : '';
    initHelp(form(), label && label !== 'PLG_SYSTEM_BETTERCATEGORIES_TOOLS_HELP' ? label : 'Help');
  });
})();
