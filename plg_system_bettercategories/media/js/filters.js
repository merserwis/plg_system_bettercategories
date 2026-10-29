/**
 * Better Categories for Gridbox — product filters. The panel is a plain GET form (it works without
 * this script); here a change loads the filtered page in the background and swaps the panel, the bar
 * and the product list, keeps the address in the history and opens / closes the drawer on phones.
 */
(() => {
  'use strict';

  document.documentElement.classList.add('bcf-js');
  // this script runs: the filters apply themselves, so the Apply button of automatic filtering can go (without it, it stays)
  document.documentElement.classList.add('bcf-ready');

  const panel = () => document.querySelector('[data-bcf-panel]');
  const form = () => document.querySelector('[data-bcf-form]');
  const root = () => document.querySelector('.bcf-root');
  const isAuto = () => !!(root() && root().classList.contains('bcf-auto'));
  let timer = 0;
  let controller = null;

  /** The address of the form state: "f-a=x,y&f-b=10..500" (readable, the same the server builds). */
  function formUrl(f) {
    const groups = new Map();
    const ranges = new Map();
    let sort = '';
    for (const [name, value] of new FormData(f).entries()) {
      if (name === 'sort-by') { sort = value; continue; }
      const v = String(value).trim();
      if (name.endsWith('[]')) {
        const key = name.slice(0, -2);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(v);
      } else if (/-(min|max)$/.test(name)) {
        const key = name.replace(/-(min|max)$/, '');
        const r = ranges.get(key) || { min: '', max: '' };
        r[name.endsWith('-min') ? 'min' : 'max'] = v.replace(/\s+/g, '').replace(',', '.');
        ranges.set(key, r);
      }
    }
    const parts = [];
    f.querySelectorAll('[data-bcf-key]').forEach((g) => {
      const key = g.dataset.bcfKey;
      if (groups.has(key)) parts.push(key + '=' + groups.get(key).map(encodeURIComponent).join(','));
      const r = ranges.get(key);
      if (r && (r.min !== '' || r.max !== '')) parts.push(key + '=' + encodeURIComponent(r.min) + '..' + encodeURIComponent(r.max));
    });
    if (sort) parts.push('sort-by=' + encodeURIComponent(sort));
    // "show all" stays while filtering
    if (root() && root().classList.contains('bcf-all')) parts.push('bc-all=1');
    // without SEF the action keeps the parameters that route the page (index.php?option=…&id=…)
    const action = f.getAttribute('action');
    return action + (parts.length ? (action.indexOf('?') < 0 ? '?' : '&') + parts.join('&') : '');
  }

  function swap(doc) {
    // the panel, the bar and the parts of Gridbox's list; Gridbox's own handlers stay on the element
    const pairs = [['[data-bcf-panel]', 'outer'], ['[data-bcf-bar]', 'outer']];
    for (const [sel] of pairs) {
      const now = document.querySelector(sel);
      const next = doc.querySelector(sel);
      if (!now || !next) return false;
    }
    const list = document.querySelector('.bcf-root .ba-item-blog-posts');
    const nextList = list && doc.getElementById(list.id);
    if (!list || !nextList) return false;
    const wasOpen = panel().classList.contains('is-open');
    const openMenu = (document.querySelector('.bcf-pos-top:not(.bcf-is-drawer) .bcf-group[open]') || {}).dataset?.bcfKey;
    for (const [sel] of pairs) document.querySelector(sel).replaceWith(document.importNode(doc.querySelector(sel), true));
    ['.ba-blog-posts-header', '.ba-blog-posts-wrapper', '.ba-blog-posts-pagination-wrapper'].forEach((sel) => {
      const a = list.querySelector(sel);
      const b = nextList.querySelector(sel);
      if (a && b) a.innerHTML = b.innerHTML;
    });
    // Gridbox's adaptive images of the new cards
    const style = doc.querySelector('style[data-id="adaptive-images"]');
    const own = document.querySelector('style[data-id="adaptive-images"]');
    if (style && own) own.innerHTML = style.innerHTML;
    else if (style) document.head.appendChild(document.importNode(style, true));
    // what the server decides for the new address: "show all" on or off, and whether search engines may index it
    const nextRoot = doc.querySelector('.bcf-root');
    if (root() && nextRoot) root().classList.toggle('bcf-all', nextRoot.classList.contains('bcf-all'));
    const robots = document.querySelector('meta[name="robots"]');
    const nextRobots = doc.querySelector('meta[name="robots"]');
    if (nextRobots && robots) robots.setAttribute('content', nextRobots.getAttribute('content') || '');
    else if (nextRobots) document.head.appendChild(document.importNode(nextRobots, true));
    else if (robots) robots.remove();
    if (wasOpen) open(false);
    if (openMenu) {
      const g = document.querySelector('.bcf-group[data-bcf-key="' + CSS.escape(openMenu) + '"]');
      if (g) g.open = true;
    }
    bind();
    syncView();
    if (window.app && app.lazyLoad && typeof app.lazyLoad.check === 'function') app.lazyLoad.check();
    window.dispatchEvent(new Event('resize'));
    return true;
  }

  async function load(url, push) {
    if (controller) controller.abort();
    controller = new AbortController();
    const r = root();
    r && r.classList.add('is-loading');
    try {
      const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (!swap(doc)) throw new Error('swap');
      if (push) history.pushState({ bcf: 1 }, '', url);
      document.title = doc.title || document.title;
    } catch (e) {
      if (e.name === 'AbortError') return;
      window.location.href = url;
    } finally {
      const now = root();
      now && now.classList.remove('is-loading');
    }
  }

  // an open side panel is moved to <body>: inside the page it would stay under a sticky header
  // (Gridbox sections and our list create their own stacking layers)
  let home = null;
  function lift(p) {
    if (getComputedStyle(p).position !== 'fixed' || p.parentNode === document.body) return;
    home = document.createComment('bcf-panel');
    p.parentNode.insertBefore(home, p);
    document.body.appendChild(p);
  }
  function putBack() {
    const p = panel();
    if (home && home.parentNode && p && p.parentNode === document.body) {
      home.parentNode.insertBefore(p, home);
    }
    if (home && home.parentNode) home.remove();
    home = null;
  }

  function open(focus) {
    const p = panel();
    if (!p) return;
    lift(p);
    p.classList.add('is-open');
    document.documentElement.classList.add('bcf-lock');
    document.querySelectorAll('[data-bcf-open]').forEach((b) => b.setAttribute('aria-expanded', 'true'));
    if (focus) {
      const first = p.querySelector('.bcf-close');
      first && first.focus();
    }
  }

  function close() {
    const p = panel();
    if (!p || !p.classList.contains('is-open')) return;
    p.classList.remove('is-open');
    document.documentElement.classList.remove('bcf-lock');
    // back into the page once the slide-out is over
    setTimeout(() => { if (!p.classList.contains('is-open')) putBack(); }, 300);
    document.querySelectorAll('[data-bcf-open]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    const toggle = document.querySelector('[data-bcf-open]');
    toggle && toggle.focus();
  }

  function bind() {
    const f = form();
    if (!f || f.dataset.bcfBound) return;
    f.dataset.bcfBound = '1';
    f.addEventListener('change', (e) => {
      if (!isAuto()) return;
      clearTimeout(timer);
      const delay = e.target.type === 'checkbox' ? 0 : 350;
      timer = setTimeout(() => load(formUrl(f), true), delay);
    });
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      load(formUrl(f), true);
    });
    f.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && e.target.matches('input[type="text"]')) {
        e.preventDefault();
        clearTimeout(timer);
        load(formUrl(f), true);
      }
    });
  }

  // capture phase: before Gridbox's own click handlers of links and items
  document.addEventListener('click', (e) => {
    const t = e.target;
    if (!(t instanceof Element)) return;
    const more = t.closest('.bcf-more');
    if (more) {
      const g = more.closest('.bcf-group');
      const on = !g.classList.contains('is-expanded');
      g.classList.toggle('is-expanded', on);
      more.setAttribute('aria-expanded', on ? 'true' : 'false');
      more.textContent = on ? more.dataset.less : more.dataset.more;
      return;
    }
    const viewBtn = t.closest('[data-bcf-view]');
    if (viewBtn) { e.preventDefault(); setView(viewBtn.dataset.bcfView); return; }
    if (t.closest('[data-bcf-open]')) { open(true); return; }
    if (t.closest('[data-bcf-close]')) { e.preventDefault(); close(); return; }
    const link = t.closest('a[data-bcf-link]');
    if (link && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.button === 0) {
      e.preventDefault();
      load(link.href, true);
    }
  }, true);

  // "above the products": the filters are menus — one open at a time, kept inside the window
  const menus = () => document.querySelectorAll('.bcf-pos-top:not(.bcf-is-drawer) .bcf-group[open]');
  const isMenu = (g) => !!g.closest('.bcf-pos-top') && !g.closest('.bcf-is-drawer') && getComputedStyle(g.querySelector('.bcf-group-body')).position === 'absolute';
  document.addEventListener('toggle', (e) => {
    const g = e.target;
    if (!(g instanceof Element) || !g.matches('.bcf-group') || !g.open || !isMenu(g)) return;
    menus().forEach((other) => { if (other !== g) other.open = false; });
    const body = g.querySelector('.bcf-group-body');
    body.style.insetInlineStart = '';
    body.style.insetInlineEnd = '';
    const r = body.getBoundingClientRect();
    if (r.right > document.documentElement.clientWidth - 8 || r.left < 8) {
      body.style.insetInlineStart = 'auto';
      body.style.insetInlineEnd = '0';
    }
  }, true);
  document.addEventListener('click', (e) => {
    if (!(e.target instanceof Element) || e.target.closest('.bcf-group')) return;
    menus().forEach((g) => { if (isMenu(g)) g.open = false; });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = [...menus()].filter(isMenu);
    if (open.length) {
      open.forEach((g) => { g.open = false; });
      open[0].querySelector('summary').focus();
      return;
    }
    close();
  });

  window.addEventListener('popstate', () => {
    if (form()) load(window.location.href, false);
  });

  // the buttons next to Gridbox's sorting menu take its text size, weight, font and colour
  function matchSorting() {
    document.querySelectorAll('.bcf-tools--sort').forEach((tools) => {
      const sel = tools.parentElement && tools.parentElement.querySelector('.blog-posts-sorting');
      if (!sel) return;
      const c = getComputedStyle(sel);
      tools.style.setProperty('--bcf-sort-size', c.fontSize);
      tools.style.setProperty('--bcf-sort-weight', c.fontWeight);
      tools.style.setProperty('--bcf-sort-font', c.fontFamily);
      tools.style.setProperty('--bcf-sort-color', c.color);
      // the same space below as the menu, so both stand on one line
      if (sel.parentElement === tools.parentElement) tools.style.setProperty('--bcf-sort-mb', c.marginBottom);
    });
  }

  // grid / list view of the products: a class on the root, remembered in this browser
  function syncView() {
    matchSorting();
    const r = root();
    if (!r) return;
    const list = r.classList.contains('bcf-view-list');
    document.querySelectorAll('[data-bcf-view]').forEach((b) => b.setAttribute('aria-pressed', String((b.dataset.bcfView === 'list') === list)));
  }
  function setView(view) {
    const r = root();
    if (!r || (view !== 'grid' && view !== 'list')) return;
    r.classList.remove('bcf-view-grid', 'bcf-view-list');
    r.classList.add('bcf-view-' + view);
    try { localStorage.setItem('bcf-view', view); } catch (e) { /* private mode */ }
    syncView();
    // Gridbox's masonry and lazy images measure again
    window.dispatchEvent(new Event('resize'));
    if (window.app && app.lazyLoad && typeof app.lazyLoad.check === 'function') app.lazyLoad.check();
  }

  const start = () => {
    bind();
    // the remembered view, also when the early script of the page did not run
    try {
      const v = localStorage.getItem('bcf-view');
      const r = root();
      if (r && (v === 'grid' || v === 'list') && (r.classList.contains('bcf-view-grid') || r.classList.contains('bcf-view-list'))) {
        r.classList.remove('bcf-view-grid', 'bcf-view-list');
        r.classList.add('bcf-view-' + v);
      }
    } catch (e) { /* private mode */ }
    syncView();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
