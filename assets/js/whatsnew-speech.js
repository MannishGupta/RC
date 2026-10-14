/* Version: 3.1.0 — Web Speech readout for What's New (en-IN / hi-IN) */
(function () {
  'use strict';
  if (!('speechSynthesis' in window)) return;

  function speak(text, lang, rate) {
    window.speechSynthesis.cancel();
    var u = new SpeechSynthesisUtterance(text);
    u.lang = lang || 'en-IN';
    u.rate = rate || 1;
    window.speechSynthesis.speak(u);
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-wn-speak]');
    if (!btn) return;
    var panel = document.getElementById('rc-whatsnew-panel');
    if (!panel) return;
    var text = panel.innerText || panel.textContent || '';
    var lang = btn.getAttribute('data-wn-lang') || 'en-IN';
    var rate = parseFloat(btn.getAttribute('data-wn-rate') || '1') || 1;
    if (btn.getAttribute('data-wn-speak') === 'stop') {
      window.speechSynthesis.cancel();
      return;
    }
    speak(text.slice(0, 12000), lang, rate);
  });
})();
