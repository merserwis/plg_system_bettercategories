/**
 * Better Categories for Gridbox — the scripts of the site (1.10.0; before, a script in the page after each
 * block). One deferred file for the page, added where the page holds one of these:
 *
 * - the subcategory panels ("what is inside"): toggle buttons, one panel open at a time, Escape and a click
 *   outside close it; a tooltip kept inside the window, a drawer under a narrow tile widened to the row, in
 *   the "columns" layout floating over the tiles below. The mode is the one of the width of the screen
 *   (data-bc-sub: wider screens, data-bc-sub-m: phones), as the styles of the block choose it — the same
 *   HTML serves every device, so a page kept by a page cache fits whoever sees it;
 * - the tile names: a word wider than its tile makes the names of the list one size smaller (down to 75 %);
 * - the sibling bar of a last-level category: on phones it scrolls sideways, the current category in view;
 * - Gridbox's Categories element set to fold: a click on a whole row opens and closes its subcategories.
 *
 * window.BetterCategories.init(root) runs them on what was added to the page later.
 */
(function () {
  'use strict';

  var PHONE = '(max-width:768px)';
  var MODES = ['below', 'drawer', 'side', 'flip', 'tooltip', 'none'];

  function each(root, selector, fn) {
    Array.prototype.forEach.call((root || document).querySelectorAll(selector), fn);
  }

  function once(el, key) {
    if (el['bcDone' + key]) { return false; }
    el['bcDone' + key] = true;
    return true;
  }

  // ---------------------------------------------------------------- subcategory panels

  function panels(n) {
    var wide = n.getAttribute('data-bc-sub') || 'none';
    var phone = n.getAttribute('data-bc-sub-m') || wide;
    var mq = window.matchMedia ? window.matchMedia(PHONE) : null;
    var ul = n.querySelector('.bettercategories-list');
    var hv = n.getAttribute('data-bc-hover') === '1' && !!window.matchMedia && window.matchMedia('(hover:hover)').matches;
    var tip = false, dr = false, fl = false, last = null;

    function mode() { return mq && mq.matches ? phone : wide; }

    function shown(li) {
      return li.classList.contains('is-open') || (hv && li.matches(':hover'))
        || !!li.querySelector('.bettercategories-link:focus-visible,.bettercategories-sub:focus-within');
    }

    // the block grows below the list (::after) by what a shown floating drawer reaches past it
    function room() {
      if (!fl || !ul) { n.style.removeProperty('--bc-room'); return; }
      var m = 0, a = ul.getBoundingClientRect().bottom;
      each(n, '.bettercategories-has-sub', function (li) {
        var i = li.querySelector('.bettercategories-sub-inner');
        if (i && shown(li)) { m = Math.max(m, li.getBoundingClientRect().bottom + i.scrollHeight + 16 - a); }
      });
      n.style.setProperty('--bc-room', Math.ceil(m) + 'px');
    }

    function reset(s) {
      s.classList.remove('bettercategories-sub--down');
      s.style.removeProperty('--bc-dx');
      s.style.left = s.style.top = s.style.width = s.style.marginLeft = s.style.marginRight = '';
    }

    function fit(li) {
      var s = li.querySelector('.bettercategories-sub');
      if (!s) { return; }
      if (tip) {
        // inside the window: below the category when there is no room above, moved sideways at the edges
        s.classList.remove('bettercategories-sub--down');
        s.style.setProperty('--bc-dx', '0px');
        var r = s.getBoundingClientRect();
        if (r.top < 8) { s.classList.add('bettercategories-sub--down'); r = s.getBoundingClientRect(); }
        var w = document.documentElement.clientWidth, d = 0;
        if (r.left < 8) { d = 8 - r.left; } else if (r.right > w - 8) { d = w - 8 - r.right; }
        s.style.setProperty('--bc-dx', Math.round(d) + 'px');
      } else if (fl) {
        // "columns" layout: the drawer floats (fixed) right under the tile, measured, across the row under a narrow tile
        var a = ul.getBoundingClientRect(), b = li.getBoundingClientRect(), wd = b.width < 200 && a.width > b.width + 1;
        s.style.left = '0px';
        s.style.top = '0px';
        var o = s.getBoundingClientRect();
        s.style.width = (wd ? a.width : b.width) + 'px';
        s.style.left = Math.round((wd ? a.left : b.left) - o.left) + 'px';
        s.style.top = Math.round(b.bottom - o.top) + 'px';
        room();
      } else if (dr && ul) {
        // a drawer under a narrow tile (< 200 px) opens across the whole row of the list
        var la = ul.getBoundingClientRect(), lb = li.getBoundingClientRect();
        var rtl = getComputedStyle(li).direction === 'rtl';
        s.style.marginLeft = '';
        s.style.marginRight = '';
        if (lb.width < 200 && la.width > lb.width + 1) {
          s.style.width = la.width + 'px';
          if (rtl) { s.style.marginRight = Math.round(lb.right - la.right) + 'px'; } else { s.style.marginLeft = Math.round(la.left - lb.left) + 'px'; }
        } else {
          s.style.width = '';
        }
      }
    }

    function set(li, open) {
      if (open) { fit(li); }
      li.classList.toggle('is-open', open);
      var b = li.querySelector('.bettercategories-toggle');
      if (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); }
      if (fl) { room(); }
    }

    function closeAll(keep) {
      each(n, '.bettercategories-item.is-open', function (li) { if (li !== keep) { set(li, false); } });
    }

    // the mode of the width shown: its class, and what fit() does
    function apply() {
      var m = mode();
      closeAll(null);
      each(n, '.bettercategories-sub', reset);
      MODES.forEach(function (x) { n.classList.toggle('bettercategories--sub-' + x, x === m); });
      tip = m === 'tooltip';
      dr = m === 'drawer';
      fl = dr && !!ul && n.classList.contains('bettercategories--columns');
      n.classList.toggle('bettercategories--float', fl);
      last = null;
      room();
    }

    n.addEventListener('pointerover', function (e) {
      if (!tip && !dr) { return; }
      var li = e.target.closest('.bettercategories-has-sub');
      if (li && li !== last) { last = li; fit(li); }
    });
    n.addEventListener('focusin', function (e) {
      if (!tip && !dr) { return; }
      var li = e.target.closest('.bettercategories-has-sub');
      if (li) { fit(li); }
    });
    window.addEventListener('resize', function () {
      last = null;
      if (tip || dr) { each(n, '.bettercategories-has-sub.is-open', fit); }
    });
    window.addEventListener('scroll', function () {
      if (fl) { each(n, '.bettercategories-has-sub.is-open,.bettercategories-has-sub:hover,.bettercategories-has-sub:focus-within', fit); }
    }, { passive: true });
    n.addEventListener('pointerleave', function () { last = null; if (fl) { room(); } });
    n.addEventListener('focusout', function () { if (fl) { setTimeout(room, 0); } });
    n.addEventListener('click', function (e) {
      var b = e.target.closest('.bettercategories-toggle');
      if (!b || !n.contains(b)) { return; }
      e.preventDefault();
      var li = b.closest('.bettercategories-item');
      var open = !li.classList.contains('is-open');
      closeAll(li);
      set(li, open);
    });
    document.addEventListener('click', function (e) { if (!n.contains(e.target)) { closeAll(null); } });
    n.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') { return; }
      var li = e.target.closest('.bettercategories-item.is-open');
      closeAll(null);
      if (li) {
        var b = li.querySelector('.bettercategories-toggle');
        if (b) { b.focus(); }
      }
    });
    if (mq) {
      if (mq.addEventListener) { mq.addEventListener('change', apply); } else if (mq.addListener) { mq.addListener(apply); }
    }
    apply();
  }

  // ---------------------------------------------------------------- tile names

  function captions(n) {
    if (!window.ResizeObserver) { return; }
    var w = -1;
    function go() {
      var cw = n.clientWidth;
      if (!cw || cw === w) { return; }
      w = cw;
      var k = 1, cs = [];
      each(n, '.bettercategories-tile>.bettercategories-link .bettercategories-caption,.bettercategories-card>.bettercategories-link .bettercategories-caption', function (c) {
        var m = c.querySelector('.bettercategories-name');
        if (!m) { return; }
        c.style.fontSize = '';
        cs.push(c);
        var s = getComputedStyle(c), a = c.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight), ws = m.textContent.match(/\S+/g);
        if (a <= 0 || !ws) { return; }
        var t = document.createElement('span'), x = 0;
        t.style.cssText = 'position:absolute;left:0;top:0;visibility:hidden;white-space:nowrap;';
        m.appendChild(t);
        ws.forEach(function (v) { t.textContent = v; x = Math.max(x, t.offsetWidth); });
        t.remove();
        if (x > a) { k = Math.min(k, (a - 1) / x); }
      });
      if (k < 1) {
        k = Math.max(k, 0.75);
        cs.forEach(function (c) { c.style.fontSize = Math.floor(parseFloat(getComputedStyle(c).fontSize) * k * 2) / 2 + 'px'; });
      }
    }
    new ResizeObserver(go).observe(n);
    if (document.fonts) { document.fonts.ready.then(function () { w = -1; go(); }); }
  }

  // ---------------------------------------------------------------- sibling bar

  function siblings(n) {
    var c = n.querySelector('.is-current'), l = n.querySelector('.bettercategories-list');
    if (!c || !l) { return; }
    function f() {
      if (l.scrollWidth <= l.clientWidth) { return; }
      var a = l.getBoundingClientRect(), b = c.getBoundingClientRect();
      l.scrollLeft += b.left - a.left - (a.width - b.width) / 2;
    }
    f();
    window.addEventListener('load', f);
  }

  // ---------------------------------------------------------------- Gridbox's Categories element

  // only in a Categories element set to fold (Gridbox keeps the arrow in the others, hidden): elsewhere the rows keep their links
  function rows() {
    each(document, '.ba-collapsible-categories .ba-app-sub-category a', function (a) {
      var c = a.nextElementSibling;
      if (c && c.tagName === 'I' && once(a, 'Row')) {
        a.removeAttribute('href');
        c.classList.remove('collapse-categories-list');
        a.parentNode.classList.add('collapse-categories-list');
      }
    });
  }

  function init(root) {
    each(root, 'nav.bettercategories[data-bc-sub]', function (n) { if (once(n, 'Sub')) { panels(n); } });
    each(root, 'nav.bettercategories--tiles', function (n) { if (once(n, 'Cap')) { captions(n); } });
    each(root, 'nav.bettercategories--siblings', function (n) { if (once(n, 'Sib')) { siblings(n); } });
    if (document.getElementById('bettercategories-rows')) { rows(); }
  }

  window.BetterCategories = { init: init };
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { init(document); }); } else { init(document); }
})();
