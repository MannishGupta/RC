<?php
if (!defined('BASE_PATH')) { exit; }
$jpTheme = 'light';
// Prefer active RC theme so Janam does not force dark
if (!empty($_COOKIE['rc_lang'])) { /* keep */ }
$src = 'janam_patri.php?embed=1';
if (!empty($_GET['slug'])) {
    $src .= '&slug=' . rawurlencode((string)$_GET['slug']);
}
foreach (['name','dob','tob','gender','gotra','place','view','id'] as $qk) {
    if (!empty($_GET[$qk])) {
        $src .= '&' . $qk . '=' . rawurlencode((string)$_GET[$qk]);
    }
}
?>
<div class="w-full h-full flex flex-col min-h-0" style="min-height:calc(100vh - 8rem)">
  <div class="flex items-center justify-between gap-2 mb-2 shrink-0">
    <div>
      <h2 class="text-lg font-bold text-slate-900 m-0">Janam Patri</h2>
      <p class="text-xs text-slate-500 m-0">Opens inside Resource Centre — same theme as the shell</p>
    </div>
    <a href="janam_patri.php" target="_blank" rel="noopener" class="text-xs font-bold text-blue-600 hover:underline">Open full page ↗</a>
  </div>
  <iframe
    id="rc-janam-frame"
    title="Janam Patri"
    src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"
    class="w-full flex-1 rounded-xl border border-slate-200 bg-white"
    style="min-height:70vh;border:1px solid #e2e8f0"
  ></iframe>
</div>
<script>
(function(){
  try {
    var th = document.documentElement.getAttribute('data-theme') || 'light';
    var f = document.getElementById('rc-janam-frame');
    if (f && f.src.indexOf('theme=') < 0) {
      f.src = f.src + (f.src.indexOf('?') >= 0 ? '&' : '?') + 'theme=' + encodeURIComponent(th);
    }
  } catch(e){}
})();
</script>
