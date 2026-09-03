/* Lightweight compatibility shim for recovered profile pages.
   Profile content no longer depends on the historic Slimbox overlay, but old
   templates still reference the plugin. Preserve the jQuery API as a no-op. */
(function (w) {
  'use strict';
  if (!w.jQuery) return;
  if (!w.jQuery.fn.slimbox) {
    w.jQuery.fn.slimbox = function () { return this; };
  }
})(window);
