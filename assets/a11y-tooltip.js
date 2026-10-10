/**
 * Resource Centre — WAI-ARIA Tooltip pattern
 * APG: https://www.w3.org/WAI/ARIA/apg/patterns/tooltip/
 * Version: 260921.29
 *
 * Rules:
 *  - Tooltip never receives focus
 *  - Trigger uses aria-describedby → tooltip id
 *  - Shown on focus and hover; dismissed on blur, mouseleave, Escape
 *  - Does not replace accessible name (use aria-label / visible text for name)
 *
 * Markup:
 *   <button aria-label="Share" data-rc-tooltip="Share via WhatsApp">...</button>
 * Or progressive enhancement of title= on interactive controls.
 */
(function (global) {
  'use strict';

  var SEQ = 0;
  var OPEN = null; // { trigger, tip, hideTimer, showTimer }
  var SHOW_DELAY = 400;
  var HIDE_DELAY = 100;

  function uid() {
    SEQ += 1;
    return 'rc-tip-' + SEQ + '-' + Date.now().toString(36);
  }

  function clearTimers(state) {
    if (!state) return;
    if (state.showTimer) clearTimeout(state.showTimer);
    if (state.hideTimer) clearTimeout(state.hideTimer);
    state.showTimer = null;
    state.hideTimer = null;
  }

  function hide() {
    if (!OPEN) return;
    clearTimers(OPEN);
    var tip = OPEN.tip;
    var trigger = OPEN.trigger;
    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
    if (trigger) {
      var ids = (trigger.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
      ids = ids.filter(function (id) { return tip && id !== tip.id; });
      if (ids.length) trigger.setAttribute('aria-describedby', ids.join(' '));
      else trigger.removeAttribute('aria-describedby');
      trigger.removeAttribute('data-rc-tooltip-open');
    }
    OPEN = null;
  }

  function position(tip, trigger) {
    var r = trigger.getBoundingClientRect();
    tip.style.position = 'fixed';
    tip.style.zIndex = '10050';
    tip.style.left = '0';
    tip.style.top = '0';
    // Measure after in DOM
    var tw = tip.offsetWidth;
    var th = tip.offsetHeight;
    var left = r.left + r.width / 2 - tw / 2;
    var top = r.bottom + 8;
    if (top + th > window.innerHeight - 8) top = r.top - th - 8;
    if (left < 8) left = 8;
    if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
    tip.style.left = Math.round(left) + 'px';
    tip.style.top = Math.round(top) + 'px';
  }

  function show(trigger, text) {
    if (!trigger || !text) return;
    hide();
    var id = uid();
    var tip = document.createElement('div');
    tip.id = id;
    tip.setAttribute('role', 'tooltip');
    tip.className = 'rc-tooltip';
    tip.textContent = text;

    var existing = (trigger.getAttribute('aria-describedby') || '').trim();
    trigger.setAttribute('aria-describedby', existing ? existing + ' ' + id : id);
    trigger.setAttribute('data-rc-tooltip-open', 'true');

    document.body.appendChild(tip);
    position(tip, trigger);

    OPEN = { trigger: trigger, tip: tip, showTimer: null, hideTimer: null };
  }

  function scheduleShow(trigger, text) {
    if (OPEN && OPEN.trigger === trigger) return;
    hide();
    var state = { trigger: trigger, tip: null, showTimer: null, hideTimer: null };
    OPEN = state;
    state.showTimer = setTimeout(function () {
      if (OPEN !== state) return;
      OPEN = null;
      show(trigger, text);
    }, SHOW_DELAY);
  }

  function scheduleHide(trigger) {
    if (!OPEN) return;
    if (OPEN.trigger !== trigger && OPEN.tip) {
      // allow moving to tip
    }
    clearTimers(OPEN);
    OPEN.hideTimer = setTimeout(function () {
      hide();
    }, HIDE_DELAY);
  }

  function tipText(el) {
    return (
      el.getAttribute('data-rc-tooltip') ||
      el.getAttribute('data-tooltip') ||
      el.getAttribute('title') ||
      ''
    ).trim();
  }

  function enhance(el) {
    if (!el || el.getAttribute('data-rc-tooltip-bound') === '1') return;
    var text = tipText(el);
    if (!text) return;

    // Prefer aria-label as name; move title into tooltip description only
    if (el.hasAttribute('title')) {
      el.setAttribute('data-rc-tooltip', text);
      el.removeAttribute('title'); // avoid browser + AT double tooltip
    }

    el.setAttribute('data-rc-tooltip-bound', '1');

    el.addEventListener('focus', function () {
      scheduleShow(el, tipText(el) || text);
    });
    el.addEventListener('blur', function () {
      scheduleHide(el);
    });
    el.addEventListener('mouseenter', function () {
      scheduleShow(el, tipText(el) || text);
    });
    el.addEventListener('mouseleave', function () {
      scheduleHide(el);
    });
  }

  function onKeyDown(e) {
    if (e.key === 'Escape' && OPEN) {
      hide();
    }
  }

  function scan(root) {
    root = root || document;
    var nodes = root.querySelectorAll(
      '[data-rc-tooltip], [data-tooltip], button[title], a[title], [role="button"][title], .ctrl-btn[title]'
    );
    Array.prototype.forEach.call(nodes, enhance);
  }

  function init() {
    document.addEventListener('keydown', onKeyDown, true);
    scan(document);
    // Alpine / dynamic DOM
    if (typeof MutationObserver !== 'undefined') {
      var obs = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
          m.addedNodes &&
            Array.prototype.forEach.call(m.addedNodes, function (n) {
              if (n.nodeType !== 1) return;
              if (n.matches && n.matches('[data-rc-tooltip], [data-tooltip], button[title], a[title]')) {
                enhance(n);
              }
              if (n.querySelectorAll) scan(n);
            });
        });
      });
      obs.observe(document.documentElement, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  global.RcTooltip = {
    enhance: enhance,
    scan: scan,
    hide: hide,
    show: show,
  };
})(typeof window !== 'undefined' ? window : this);
