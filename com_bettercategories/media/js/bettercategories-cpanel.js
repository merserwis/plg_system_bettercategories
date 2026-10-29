/**
 * Better Categories for Gridbox — control panel: the button "Clear the cache" asks the system plugin
 * (com_ajax, bc_action=clearcache, with the token of the page) to empty its cache of the lists and the
 * kept pages, as a save of the settings does.
 */
(function () {
  'use strict';

  var opts = (window.Joomla && Joomla.getOptions) ? Joomla.getOptions('com_bettercategories.cpanel') : null;

  function start() {
    var clear = document.querySelector('[data-bcc-clear-cache]');
    if (!opts || !clear) { return; }
    clear.addEventListener('click', function () {
      var body = new FormData();
      body.append('bc_action', 'clearcache');
      body.append(Joomla.getOptions('csrf.token'), '1');
      clear.disabled = true;
      fetch(opts.ajax, { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          var data = json && json.data;
          while (Array.isArray(data)) { data = data[0]; }
          if (!data || data.error) { throw new Error((data && data.error) || (json && json.message) || opts.texts.FAILED); }
          Joomla.renderMessages({ message: [opts.texts.CACHE_CLEARED] });
        })
        .catch(function (e) { Joomla.renderMessages({ error: [e.message || opts.texts.FAILED] }); })
        .then(function () { clear.disabled = false; });
    });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
