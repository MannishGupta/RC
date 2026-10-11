<?php
/** Early theme boot for standalone reports (numero / janam / blood). */
$__rcv = rawurlencode(defined('APP_VERSION') ? (string)APP_VERSION : '1');
?>
<script>
(function(){
  try {
    var t = localStorage.getItem('rc-theme') || 'light';
    if (t !== 'light' && t !== 'dark' && t !== 'reserve') t = 'light';
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.classList.toggle('dark', t === 'dark');
  } catch (e) {}
})();
</script>
<link rel="stylesheet" href="/assets/contrast-lock.css?v=<?= $__rcv ?>">
<link rel="stylesheet" href="/assets/rc-compat.css?v=<?= $__rcv ?>">
