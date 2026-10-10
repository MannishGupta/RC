/**
 * Resource Centre — WAI-ARIA Dialog (Modal) focus management
 * APG: https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/
 * Version: 260921.29
 *
 * Usage:
 *   const handle = window.RcFocusTrap.activate(dialogEl, { returnFocus: triggerEl });
 *   handle.deactivate();
 */
(function (global) {
  'use strict';

  var FOCUSABLE =
    'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), ' +
    'select:not([disabled]), textarea:not([disabled]), iframe, object, embed, ' +
    '[contenteditable="true"], [tabindex]:not([tabindex="-1"])';

  function isVisible(el) {
    if (!el || el.disabled) return false;
    if (el.getAttribute('aria-hidden') === 'true') return false;
    if (el.closest('[aria-hidden="true"]') && !el.closest('[role="dialog"], [role="alertdialog"], dialog')) {
      return false;
    }
    var style = window.getComputedStyle(el);
    if (style.visibility === 'hidden' || style.display === 'none') return false;
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  }

  function getFocusable(container) {
    if (!container) return [];
    return Array.prototype.slice.call(container.querySelectorAll(FOCUSABLE)).filter(isVisible);
  }

  function ensureDialogSemantics(container) {
    if (!container) return;
    var role = container.getAttribute('role');
    if (role !== 'dialog' && role !== 'alertdialog') {
      container.setAttribute('role', 'dialog');
    }
    if (container.getAttribute('aria-modal') !== 'true') {
      container.setAttribute('aria-modal', 'true');
    }
    if (!container.hasAttribute('aria-label') && !container.hasAttribute('aria-labelledby')) {
      container.setAttribute('aria-label', 'Dialog');
    }
    if (!container.hasAttribute('tabindex')) {
      container.setAttribute('tabindex', '-1');
    }
  }

  function activate(container, options) {
    options = options || {};
    if (!container) {
      return { deactivate: function () {} };
    }

    ensureDialogSemantics(container);

    var returnFocus =
      options.returnFocus ||
      (document.activeElement && document.activeElement !== document.body
        ? document.activeElement
        : null);

    var previouslyFocused = returnFocus;
    var active = true;
    var main = document.getElementById('rc-main-content');
    var aside = document.getElementById('rc-sidebar');

    function onKeyDown(e) {
      if (!active) return;
      if (e.key === 'Escape' && options.closeOnEscape !== false) {
        if (typeof options.onEscape === 'function') {
          options.onEscape(e);
        }
        return;
      }
      if (e.key !== 'Tab') return;
      var list = getFocusable(container);
      if (!list.length) {
        e.preventDefault();
        container.focus();
        return;
      }
      var first = list[0];
      var last = list[list.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first || !container.contains(document.activeElement)) {
          e.preventDefault();
          last.focus();
        }
      } else if (document.activeElement === last || !container.contains(document.activeElement)) {
        e.preventDefault();
        first.focus();
      }
    }

    document.addEventListener('keydown', onKeyDown, true);

    window.requestAnimationFrame(function () {
      if (!active) return;
      var preferred = container.querySelector('[data-autofocus]');
      if (preferred && isVisible(preferred)) {
        preferred.focus();
        return;
      }
      var list = getFocusable(container);
      if (list.length) {
        // Prefer first text input over close button when present
        var input = list.filter(function (el) {
          return el.matches('input:not([type="hidden"]):not([type="button"]):not([type="submit"]), select, textarea');
        })[0];
        (input || list[0]).focus();
      } else {
        container.focus();
      }
    });

    if (main && !container.contains(main)) main.setAttribute('aria-hidden', 'true');
    if (aside && !container.contains(aside)) aside.setAttribute('aria-hidden', 'true');
    document.body.classList.add('rc-modal-open');
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';

    return {
      deactivate: function () {
        if (!active) return;
        active = false;
        document.removeEventListener('keydown', onKeyDown, true);
        if (main) main.removeAttribute('aria-hidden');
        if (aside) aside.removeAttribute('aria-hidden');
        document.body.classList.remove('rc-modal-open');
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
          try {
            previouslyFocused.focus();
          } catch (err) {}
        }
      },
    };
  }

  global.RcFocusTrap = {
    activate: activate,
    getFocusable: getFocusable,
    ensureDialogSemantics: ensureDialogSemantics,
  };

  // Auto-activate when elements marked data-rc-focus-trap become visible
  function autoBind() {
    if (!global.MutationObserver) return;
    var handles = new WeakMap();
    function sync(el) {
      if (!el || el.nodeType !== 1) return;
      var isTrap = el.hasAttribute('data-rc-focus-trap') ||
        (el.getAttribute('role') === 'dialog' && el.getAttribute('aria-modal') === 'true' && el.hasAttribute('data-rc-dialog'));
      if (!isTrap) return;
      var visible = isVisible(el);
      var h = handles.get(el);
      if (visible && !h) {
        handles.set(el, activate(el, { closeOnEscape: true }));
      } else if (!visible && h) {
        h.deactivate();
        handles.delete(el);
      }
    }
    function scan() {
      document.querySelectorAll('[data-rc-focus-trap], [data-rc-dialog][role="dialog"]').forEach(sync);
    }
    var obs = new MutationObserver(function () { scan(); });
    if (document.body) {
      obs.observe(document.body, { attributes: true, childList: true, subtree: true, attributeFilter: ['style', 'class', 'hidden', 'aria-hidden'] });
      scan();
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        obs.observe(document.body, { attributes: true, childList: true, subtree: true, attributeFilter: ['style', 'class', 'hidden', 'aria-hidden'] });
        scan();
      });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoBind);
  } else {
    autoBind();
  }

})(typeof window !== 'undefined' ? window : this);
