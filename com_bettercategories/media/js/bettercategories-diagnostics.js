/**
 * Better Categories for Gridbox — Diagnostics: the button copies the report (the text of the field beside it)
 * to the clipboard; where the clipboard is not available (an address without https) the text is selected for
 * Ctrl+C.
 */
(function () {
  'use strict';

  function start() {
    var opts = (window.Joomla && Joomla.getOptions) ? Joomla.getOptions('com_bettercategories.diagnostics') : null;
    document.querySelectorAll('[data-bcc-copy]').forEach(function (button) {
      var field = document.getElementById(button.getAttribute('data-bcc-copy'));
      if (!field) { return; }
      button.addEventListener('click', function () {
        field.focus();
        field.select();
        var done = function () { if (opts && Joomla.renderMessages) { Joomla.renderMessages({ message: [opts.texts.COPIED] }); } };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(field.value).then(done, function () { document.execCommand('copy') && done(); });
        } else if (document.execCommand('copy')) {
          done();
        }
      });
    });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
