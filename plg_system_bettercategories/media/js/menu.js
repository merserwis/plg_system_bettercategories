/**
 * Better Categories for Gridbox — the store menu (1.11.0). The HTML is the same for every device: from 1024 px
 * a panel (or a bar) where pointing at a top category shows its subcategories; below it a drawer from the side
 * where the arrow of a top category shows its subcategories, with a way back. Without this script the panel
 * opens on hover and keyboard focus (CSS) and every link works.
 */
(() => {
  'use strict';

  document.documentElement.classList.add('bcm-js');
  const wide = window.matchMedia('(min-width: 1024px)');

  function init(nav) {
    if (nav.dataset.bcmReady) return;
    nav.dataset.bcmReady = '1';
    const style = nav.classList.contains('bcm--bar') ? 'bar' : nav.classList.contains('bcm--panel') ? 'panel' : 'button';
    const hover = nav.classList.contains('bcm--hover');
    const toggle = nav.querySelector('.bcm-toggle');
    const items = Array.from(nav.querySelectorAll('.bcm-top-item'));
    const back = nav.querySelector('.bcm-back');
    const title = nav.querySelector('.bcm-head-title');
    const label = title ? title.textContent : '';
    let backdrop = null;
    let timer = 0;

    const setActive = (item) => {
      items.forEach((i) => {
        const on = i === item;
        i.classList.toggle('is-active', on);
        const b = i.querySelector(':scope > .bcm-open');
        if (b) b.setAttribute('aria-expanded', on ? 'true' : 'false');
      });
    };
    // the button and the panel show the first top category at once; the bar nothing until pointed at
    const firstActive = () => { if (style !== 'bar' && !items.some((i) => i.classList.contains('is-active'))) setActive(items.find((i) => i.classList.contains('bcm-has-sub')) || null); };

    const open = () => {
      if (nav.classList.contains('is-open')) return;
      nav.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      if (wide.matches) {
        firstActive();
        // a button near the right edge: the panel moves left to stay on the screen
        const panel = nav.querySelector('.bcm-panel');
        if (panel && style === 'button') {
          panel.style.left = '';
          const r = panel.getBoundingClientRect();
          const over = r.right - (document.documentElement.clientWidth - 8);
          if (over > 0) panel.style.left = Math.max(-r.left + 8, -over) + 'px';
        }
        return;
      }
      // the drawer: no shift of the wide panel, the page behind it out of reach of the keyboard
      const drawer = nav.querySelector('.bcm-panel');
      if (drawer) drawer.style.left = '';
      backdrop = document.createElement('div');
      backdrop.className = 'bcm-backdrop';
      backdrop.addEventListener('click', close);
      nav.appendChild(backdrop);
      document.documentElement.style.overflow = 'hidden';
      const first = nav.querySelector('.bcm-panel a, .bcm-panel button:not([hidden])');
      if (first) first.focus({ preventScroll: true });
    };
    const close = () => {
      clearTimeout(timer);
      clearTimeout(nav.bcmLeave);
      const wasDrawer = !!backdrop;
      const panel = nav.querySelector('.bcm-panel');
      if (panel) panel.style.left = '';
      nav.classList.remove('is-open', 'is-drilled');
      toggle.setAttribute('aria-expanded', 'false');
      if (back) back.hidden = true;
      if (title) title.textContent = label;
      if (backdrop) { backdrop.remove(); backdrop = null; }
      document.documentElement.style.overflow = '';
      if (style === 'bar') setActive(null);
      // the focus back to the button when it was in the closed drawer
      if (wasDrawer && nav.querySelector('.bcm-panel').contains(document.activeElement)) toggle.focus({ preventScroll: true });
    };

    // the drawer of narrow screens: the subcategories of one top category, and back
    const drill = (item) => {
      setActive(item);
      nav.classList.add('is-drilled');
      if (back) back.hidden = false;
      if (title) title.textContent = (item.querySelector('.bcm-name') || {}).textContent || label;
      const first = item.querySelector('.bcm-sub a');
      if (first) first.focus({ preventScroll: true });
      const panel = nav.querySelector('.bcm-panel');
      if (panel) panel.scrollTop = 0;
    };
    if (back) {
      back.addEventListener('click', () => {
        const item = items.find((i) => i.classList.contains('is-active'));
        nav.classList.remove('is-drilled');
        back.hidden = true;
        if (title) title.textContent = label;
        const b = item && item.querySelector(':scope > .bcm-open');
        if (b) b.focus({ preventScroll: true });
      });
    }

    toggle.addEventListener('click', () => (nav.classList.contains('is-open') ? close() : open()));
    const closeButton = nav.querySelector('.bcm-close');
    if (closeButton) closeButton.addEventListener('click', () => { close(); toggle.focus(); });

    items.forEach((item) => {
      const button = item.querySelector(':scope > .bcm-open');
      // pointing at a top category shows its subcategories after a moment (a pointer crossing the list on its
      // way to the subcategories does not switch them)
      item.addEventListener('mouseenter', () => {
        if (!wide.matches || (style === 'bar' && !hover)) return;
        clearTimeout(timer);
        timer = setTimeout(() => setActive(item), style === 'bar' || !items.some((i) => i.classList.contains('is-active')) ? 60 : 110);
      });
      item.querySelector(':scope > .bcm-top-link').addEventListener('focus', () => { if (wide.matches && (style !== 'bar' || item.classList.contains('is-active'))) setActive(item); });
      if (button) {
        button.addEventListener('click', () => {
          if (!wide.matches) { drill(item); return; }
          if (item.classList.contains('is-active') && style === 'bar') { setActive(null); return; }
          setActive(item);
          const first = item.querySelector('.bcm-sub a');
          if (first) first.focus({ preventScroll: true });
        });
      }
    });

    // the panel of the button and the subcategories of the bar open on hover (or only on click) and close a moment
    // after the pointer leaves
    nav.addEventListener('mouseenter', () => {
      clearTimeout(nav.bcmLeave);
      if (wide.matches && hover && style === 'button') open();
    });
    nav.addEventListener('mouseleave', () => {
      clearTimeout(timer);
      if (!wide.matches) return;
      nav.bcmLeave = setTimeout(() => {
        if (style === 'button' && hover) close();
        if (style === 'bar') setActive(null);
      }, 280);
    });

    document.addEventListener('keydown', (e) => {
      // Tab stays in the open drawer (the page behind it is covered)
      if (e.key === 'Tab' && backdrop) {
        const all = Array.from(nav.querySelectorAll('.bcm-panel a, .bcm-panel button')).filter((el) => !el.hidden && el.offsetParent !== null);
        if (!all.length) return;
        const first = all[0];
        const last = all[all.length - 1];
        if (!nav.querySelector('.bcm-panel').contains(document.activeElement)) { e.preventDefault(); first.focus(); }
        else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        return;
      }
      if (e.key !== 'Escape' || !(nav.classList.contains('is-open') || nav.contains(document.activeElement) || items.some((i) => i.classList.contains('is-active')))) return;
      if (nav.classList.contains('is-drilled') && back) { back.click(); return; }
      if (style === 'bar' && wide.matches) {
        // back to the arrow of the category that was open (the button of the bar is hidden on wide screens)
        const item = items.find((i) => i.classList.contains('is-active'));
        const opener = item && (item.querySelector(':scope > .bcm-open') || item.querySelector(':scope > .bcm-top-link'));
        close();
        if (opener && nav.contains(document.activeElement)) opener.focus({ preventScroll: true });
        return;
      }
      if (nav.classList.contains('is-open')) { close(); toggle.focus({ preventScroll: true }); }
    });
    // a click outside closes the panel of the button
    document.addEventListener('click', (e) => {
      if (!nav.contains(e.target) && wide.matches && (nav.classList.contains('is-open') || style === 'bar')) close();
    });
    // from a phone to a wide screen and back: what was open is closed
    const change = () => { close(); if (style === 'panel' && wide.matches) firstActive(); };
    if (wide.addEventListener) wide.addEventListener('change', change); else if (wide.addListener) wide.addListener(change);
    if (style === 'panel' && wide.matches) firstActive();
  }

  const start = () => document.querySelectorAll('nav.bcm').forEach(init);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
