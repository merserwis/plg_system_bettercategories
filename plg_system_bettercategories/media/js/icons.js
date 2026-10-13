/**
 * Better Categories for Gridbox — the icon picker of the settings: a window with the Font Awesome
 * Free icons shipped with the plugin (search by name and words, style), a preview beside each icon
 * field. Works in rows of lists added later (one window for the page, events on the document).
 */
(() => {
  'use strict';

  const T = (key, fallback) => {
    const k = 'PLG_SYSTEM_BETTERCATEGORIES_ICON_' + key;
    const text = window.Joomla && Joomla.Text ? Joomla.Text._(k) : '';
    return text && text !== k ? text : fallback;
  };
  const STYLE = { s: 'solid', r: 'regular', b: 'brands' };
  const PAGE = 240;
  let index = null;
  let dialog = null;
  let target = null;
  let shown = PAGE;

  const sprite = (base, style, name) => base + '/' + style + '.svg#' + name;
  const parse = (value) => {
    let style = null;
    let name = null;
    for (const t of String(value || '').toLowerCase().trim().split(/\s+/)) {
      if (/^(fa-solid|fas)$/.test(t)) style = 'solid';
      else if (/^(fa-regular|far)$/.test(t)) style = 'regular';
      else if (/^(fa-brands|fab)$/.test(t)) style = 'brands';
      else if (t === 'fa' || /^fa-(\d*x|xs|sm|lg|xl|2xs|2xl|fw|spin|pulse|beat|fade|bounce|flip|shake|border|inverse)$/.test(t)) continue;
      else if (t.startsWith('fa-')) name = name || t.slice(3);
      else if (t) return null;
    }
    return name ? { style: style || 'solid', name } : null;
  };

  function svgUse(base, style, name) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', 'bcicon-svg');
    const use = document.createElementNS(ns, 'use');
    use.setAttribute('href', sprite(base, style, name));
    svg.appendChild(use);
    return svg;
  }

  function paint(box) {
    const input = box.querySelector('[data-bcicon-input]');
    const preview = box.querySelector('[data-bcicon-preview]');
    if (!input || !preview) return;
    preview.textContent = '';
    const fa = parse(input.value);
    if (fa) {
      preview.appendChild(svgUse(box.dataset.bciconBase, fa.style, fa.name));
    } else if (input.value.trim() !== '') {
      const i = document.createElement('i');
      i.className = input.value.replace(/[^A-Za-z0-9_\- ]/g, '');
      preview.appendChild(i);
    }
  }

  async function loadIndex(base) {
    if (index) return index;
    const r = await fetch(base + '/index.json', { credentials: 'same-origin' });
    index = await r.json();
    return index;
  }

  function buildDialog() {
    dialog = document.createElement('dialog');
    dialog.className = 'bcicon-dialog';
    dialog.innerHTML = '<form method="dialog" class="bcicon-dialog-in">'
      + '<div class="bcicon-dialog-head"><strong></strong><button type="submit" class="btn-close" value="close"></button></div>'
      + '<div class="bcicon-dialog-tools"><input type="search" class="form-control" data-q>'
      + '<div class="btn-group" role="group" data-styles></div></div>'
      + '<div class="bcicon-grid" data-grid></div>'
      + '<div class="bcicon-dialog-foot"><span class="small text-muted" data-count></span><button type="button" class="btn btn-sm btn-outline-secondary" data-more></button>'
      + '<span class="small text-muted ms-auto">Font Awesome Free 6.7.2 · CC BY 4.0</span></div></form>';
    dialog.querySelector('strong').textContent = T('TITLE', 'Choose an icon');
    dialog.querySelector('.btn-close').setAttribute('aria-label', T('CLOSE', 'Close'));
    const q = dialog.querySelector('[data-q]');
    q.placeholder = T('SEARCH', 'Search: fire, gas, phone…');
    const styles = dialog.querySelector('[data-styles]');
    [['', 'ALL', 'All'], ['s', 'SOLID', 'Solid'], ['r', 'REGULAR', 'Regular'], ['b', 'BRANDS', 'Brands']].forEach(([v, k, f], n) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-sm btn-outline-secondary' + (n === 0 ? ' active' : '');
      b.dataset.style = v;
      b.textContent = T(k, f);
      styles.appendChild(b);
    });
    styles.addEventListener('click', (e) => {
      const b = e.target.closest('button');
      if (!b) return;
      styles.querySelectorAll('button').forEach((x) => x.classList.toggle('active', x === b));
      shown = PAGE;
      render();
    });
    q.addEventListener('input', () => { shown = PAGE; render(); });
    const more = dialog.querySelector('[data-more]');
    more.textContent = T('MORE', 'Show more');
    more.addEventListener('click', () => { shown += PAGE; render(); });
    dialog.querySelector('[data-grid]').addEventListener('click', (e) => {
      const b = e.target.closest('[data-icon]');
      if (!b || !target) return;
      const input = target.querySelector('[data-bcicon-input]');
      input.value = b.dataset.icon;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      paint(target);
      dialog.close();
    });
    document.body.appendChild(dialog);
  }

  function render() {
    const base = target.dataset.bciconBase;
    const grid = dialog.querySelector('[data-grid]');
    const words = dialog.querySelector('[data-q]').value.toLowerCase().split(/\s+/).filter(Boolean);
    const style = (dialog.querySelector('[data-styles] .active') || {}).dataset?.style || '';
    const hits = [];
    const first = words[0] || '';
    for (const [name, label, styles, terms] of index) {
      const hay = (name + ' ' + label + ' ' + terms).toLowerCase();
      if (!words.every((w) => hay.includes(w))) continue;
      // the name itself first, names starting with the words next, then names containing them, then search words
      const rank = !first ? 0 : name === first ? 0 : name.startsWith(first) ? 1 : name.includes(first) ? 2 : 3;
      for (const s of styles) {
        if (!style || s === style) hits.push([name, label, STYLE[s], rank]);
      }
    }
    hits.sort((a, b) => a[3] - b[3]);
    grid.textContent = '';
    const frag = document.createDocumentFragment();
    hits.slice(0, shown).forEach(([name, label, s]) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'bcicon-cell';
      b.dataset.icon = 'fa-' + s + ' fa-' + name;
      b.title = label + ' (' + s + ')';
      b.appendChild(svgUse(base, s, name));
      const cap = document.createElement('span');
      cap.textContent = name;
      b.appendChild(cap);
      frag.appendChild(b);
    });
    grid.appendChild(frag);
    if (!hits.length) {
      const p = document.createElement('p');
      p.className = 'text-muted';
      p.textContent = T('NONE_FOUND', 'No icon found.');
      grid.appendChild(p);
    }
    dialog.querySelector('[data-count]').textContent = T('COUNT', '%shown% of %all%').replace('%shown%', Math.min(shown, hits.length)).replace('%all%', hits.length);
    dialog.querySelector('[data-more]').hidden = hits.length <= shown;
  }

  async function open(box) {
    target = box;
    if (!dialog) buildDialog();
    try {
      await loadIndex(box.dataset.bciconBase);
    } catch (e) {
      window.alert(e.message);
      return;
    }
    const current = parse(box.querySelector('[data-bcicon-input]').value);
    const q = dialog.querySelector('[data-q]');
    q.value = current ? current.name.replace(/-/g, ' ') : '';
    shown = PAGE;
    render();
    dialog.showModal();
    q.focus();
    q.select();
  }

  document.addEventListener('click', (e) => {
    const openBtn = e.target.closest('[data-bcicon-open]');
    if (openBtn) { open(openBtn.closest('[data-bcicon]')); return; }
    const clear = e.target.closest('[data-bcicon-clear]');
    if (clear) {
      const box = clear.closest('[data-bcicon]');
      const input = box.querySelector('[data-bcicon-input]');
      input.value = '';
      input.dispatchEvent(new Event('change', { bubbles: true }));
      paint(box);
    }
  });
  document.addEventListener('input', (e) => {
    const box = e.target.closest && e.target.closest('[data-bcicon]');
    if (box && e.target.matches('[data-bcicon-input]')) paint(box);
  });
  const paintAll = (scope) => (scope || document).querySelectorAll('[data-bcicon]').forEach(paint);
  document.addEventListener('DOMContentLoaded', () => paintAll());
  document.addEventListener('subform-row-add', (e) => paintAll((e.detail && e.detail.row) || e.target));
})();
