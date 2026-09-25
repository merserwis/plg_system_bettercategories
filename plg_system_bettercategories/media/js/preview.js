/**
 * Better Categories for Gridbox — live preview in the plugin settings.
 * Sends the unsaved form values to com_ajax (the plugin renders the list itself) and shows the result
 * in an iframe; the iframe width follows the Desktop / Tablet / Phone buttons, so column settings per
 * device and all @media rules apply exactly as on the site.
 */
(() => {
  'use strict';

  // Real device widths, scaled down to the panel: @media rules see the true width.
  const WIDTHS = { desktop: 1280, tablet: 820, mobile: 390 };
  const BASE_CSS = 'body{margin:16px;font:16px/1.5 Roboto,"Segoe UI",Arial,sans-serif;color:#333;background:#fff}'
    + 'a{color:#1a73e8;text-decoration:none}h2{font-size:28px;line-height:1.2;margin:0}'
    + '.bcat-products{margin-top:20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px}'
    + '.bcat-products div{height:90px;border-radius:6px;background:#eef1f4;display:flex;align-items:center;justify-content:center;color:#9aa3ad;font-size:12px}'
    + '.bcat-note{margin-top:20px;padding:10px;border:1px dashed #c5ccd3;border-radius:6px;color:#6c757d;font-size:13px}';

  const form = () => document.getElementById('style-form') || document.querySelector('form[name="adminForm"]');
  const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);

  function collect(category) {
    const data = new FormData(form());
    const body = new FormData();
    for (const [key, value] of data.entries()) {
      if (key.startsWith('jform[params]')) {
        body.append(key, value);
      }
    }
    body.append('category', category || '0');
    body.append(Joomla.getOptions('csrf.token'), '1');
    return body;
  }

  function document_(html, result) {
    const products = result.hideProducts
      ? '<div class="bcat-note">Products are hidden on categories that have subcategories.</div>'
      : '<div class="bcat-products">' + '<div>product</div>'.repeat(8) + '</div>';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      + '<style>' + BASE_CSS + '</style></head><body>'
      + (html || '<div class="bcat-note">This category has no subcategories to list.</div>') + products + '</body></html>';
  }

  function setup(panel) {
    const frame = panel.querySelector('iframe');
    const select = panel.querySelector('[data-bcat-category]');
    const status = panel.querySelector('.bcat-preview-status');
    const box = panel.querySelector('.bcat-preview-frame');
    let timer = null;
    let seq = 0;
    let device = 'desktop';
    let controller = null;
    let dirty = true;

    const resize = () => {
      const width = WIDTHS[device];
      const scale = Math.min(1, (box.clientWidth || width) / width);
      let height = 240;
      try {
        height = Math.max(240, frame.contentDocument.documentElement.scrollHeight + 4);
      } catch (e) { /* not loaded yet */ }
      frame.style.width = width + 'px';
      frame.style.height = height + 'px';
      frame.style.transform = 'scale(' + scale + ')';
      frame.style.marginLeft = Math.max(0, (box.clientWidth - width * scale) / 2) + 'px';
      box.style.height = Math.ceil(height * scale) + 'px';
    };
    window.addEventListener('resize', resize);
    frame.addEventListener('load', resize);

    async function refresh() {
      if (!visible(panel)) {
        // hidden tab: render when it is shown, one request instead of three per keystroke
        dirty = true;
        return;
      }
      dirty = false;
      const mine = ++seq;
      if (controller) controller.abort();
      controller = new AbortController();
      status.textContent = 'Updating…';
      try {
        const response = await fetch(panel.dataset.bcatUrl, { method: 'POST', body: collect(select.value), credentials: 'same-origin', signal: controller.signal });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const json = await response.json();
        let result = json.data;
        while (Array.isArray(result)) result = result[0];
        if (mine !== seq) return;
        if (!result || result.error) {
          status.textContent = (result && result.error) || json.message || 'Preview unavailable.';
          return;
        }
        if (Array.isArray(result.categories)) {
          const current = String(result.category ?? select.value);
          select.replaceChildren(...result.categories.map((c) => new Option(String(c.title), String(c.id))));
          select.value = current;
        }
        frame.srcdoc = document_(result.html, result);
        status.textContent = '';
      } catch (e) {
        if (e.name === 'AbortError') return;
        if (mine === seq) status.textContent = 'Preview unavailable: ' + e.message;
      }
    }

    const schedule = () => { clearTimeout(timer); timer = setTimeout(refresh, 350); };
    panel.bcatRefresh = schedule;
    panel.bcatShown = () => { if (dirty) schedule(); else resize(); };

    panel.querySelectorAll('[data-bcat-device]').forEach((button) => {
      button.addEventListener('click', () => {
        panel.querySelectorAll('[data-bcat-device]').forEach((b) => b.classList.replace('btn-primary', 'btn-outline-secondary'));
        button.classList.replace('btn-outline-secondary', 'btn-primary');
        device = button.dataset.bcatDevice;
        resize();
      });
    });
    select.addEventListener('change', (e) => { e.stopPropagation(); refresh(); });
    refresh();
  }

  function rangeValues(root) {
    root.querySelectorAll('.form-range').forEach((range) => {
      if (range.nextElementSibling && range.nextElementSibling.classList.contains('bcat-range-value')) return;
      const out = document.createElement('output');
      out.className = 'bcat-range-value';
      const unit = /opacity/.test(range.name) ? '%' : /angle/.test(range.name) ? '°' : '';
      const show = () => { out.textContent = range.value + unit; };
      range.insertAdjacentElement('afterend', out);
      range.addEventListener('input', show);
      show();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const panels = [...document.querySelectorAll('.bcat-preview')];
    const root = form();
    if (!panels.length || !root) return;
    panels.forEach(setup);
    rangeValues(root);
    const refreshAll = (e) => {
      if (e && e.target && e.target.closest && e.target.closest('.bcat-preview')) return;
      panels.forEach((p) => p.bcatRefresh());
    };
    root.addEventListener('input', refreshAll);
    root.addEventListener('change', refreshAll);
    document.addEventListener('subform-row-add', refreshAll);
    document.addEventListener('subform-row-remove', refreshAll);
    document.addEventListener('joomla:updated', refreshAll);
    // Tabs: the panel that becomes visible renders now (it was skipped while hidden).
    const shown = () => setTimeout(() => panels.forEach((p) => p.bcatShown()), 50);
    document.addEventListener('joomla.tab.shown', shown);
    document.querySelectorAll('button[role="tab"]').forEach((t) => t.addEventListener('click', shown));
  });
})();
