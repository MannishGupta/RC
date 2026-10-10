<?php
/** Super Admin — master brand logo upload. Version: 20261003.39 */
if (!defined('BASE_PATH')) exit;
if (empty($isSuperAdmin)) {
    echo '<p class="text-sm text-rose-600 p-4">Super Admin only.</p>';
    return;
}
if (!class_exists('MasterDirectory')) {
    require_once BASE_PATH . '/app/MasterDirectory.php';
}
$banks = MasterDirectory::read('banks');
$oems = MasterDirectory::read('vehicle_oems');
?>
<div class="max-w-3xl mx-auto space-y-4" x-data="brandLogosTool(<?= htmlspecialchars(json_encode(['banks' => array_map(static fn($r) => ['slug' => (string)($r['slug'] ?? ''), 'name' => (string)($r['display_name'] ?? $r['name'] ?? '')], $banks), 'oems' => array_map(static fn($r) => ['slug' => (string)($r['slug'] ?? ''), 'name' => (string)($r['display_name'] ?? $r['name'] ?? '')], $oems)], JSON_UNESCAPED_UNICODE) ?: '{}') ?>)">
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h1 class="text-lg font-black text-slate-800">Brand Logos</h1>
    <p class="text-xs text-slate-500 mt-1">Upload SVG/PNG/WebP into <code class="text-[10px]">data/masters/logos/</code> (survives deploys). SVG is sanitised.</p>
    <div class="flex gap-2 mt-4">
      <button type="button" class="h-9 px-3 rounded-lg text-xs font-bold border" :class="kind==='banks' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600'" @click="kind='banks'; slug=''; preview=''">Banks</button>
      <button type="button" class="h-9 px-3 rounded-lg text-xs font-bold border" :class="kind==='oems' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600'" @click="kind='oems'; slug=''; preview=''">Vehicle OEMs</button>
    </div>
    <label class="block mt-4 text-xs font-bold text-slate-600">Brand
      <input type="search" class="mt-1 w-full h-9 px-2 border rounded-lg text-sm" placeholder="Search…" x-model="q">
    </label>
    <select class="mt-2 w-full h-10 px-2 border rounded-lg text-sm" x-model="slug" @change="loadPreview()">
      <option value="">— select —</option>
      <template x-for="row in filtered" :key="row.slug">
        <option :value="row.slug" x-text="row.name + ' (' + row.slug + ')'"></option>
      </template>
    </select>
    <div class="mt-4 flex items-center gap-4" x-show="slug">
        <?php
          $iuField = 'brandlogo';
          $iuCurrent = '';
          $iuLabel = 'Brand logo';
          $iuId = 'brandlogo_upload';
          $iuInputName = 'file';
          require BASE_PATH . '/app/views/partials/image_upload.php';
        ?>

      <div class="w-24 h-24 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden">
        <template x-if="preview"><img :src="preview" alt="" class="max-w-full max-h-full object-contain"></template>
        <span x-show="!preview" class="text-[10px] text-slate-400">No logo</span>
      </div>
      <div class="flex-1 space-y-2">
        <?php $__bs = class_exists('AppMedia') ? AppMedia::imageSpec('brandlogo') : ['hint'=>'SVG, PNG, WebP · ≤450 KB','accept'=>'.svg,.png,.webp,image/svg+xml,image/png,image/webp','maxKB'=>450]; ?>
        <p class="text-[11px] text-slate-500 mb-1"><?= htmlspecialchars($__bs['hint'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <input type="file" accept="<?= htmlspecialchars($__bs['accept'] ?? '.svg,.png,.webp', ENT_QUOTES, 'UTF-8') ?>" @change="
          file=$event.target.files[0];
          if (file && file.size > <?= (int)($__bs['maxKB']??450) ?> * 1024) { msg='File too large (max <?= (int)($__bs['maxKB']??450) ?> KB)'; file=null; $event.target.value=''; return; }
          if (file) { if (previewUrl) URL.revokeObjectURL(previewUrl); previewUrl = URL.createObjectURL(file); preview = previewUrl; }
        ">
        <button type="button" class="h-9 px-4 rounded-lg bg-blue-600 text-white text-xs font-bold disabled:opacity-50" :disabled="!slug || !file || uploading" @click="upload()">Upload</button>
        <p class="text-[11px] text-slate-500" x-text="msg"></p>
      </div>
    </div>
  </div>
</div>
<script>
function brandLogosTool(lists) {
  return {
    kind: 'banks',
    q: '',
    slug: '',
    file: null,
    preview: '', previewUrl: '',
    msg: '',
    uploading: false,
    lists: lists || { banks: [], oems: [] },
    get filtered() {
      const rows = this.lists[this.kind] || [];
      const q = (this.q || '').toLowerCase();
      if (!q) return rows;
      return rows.filter(r => (r.name + ' ' + r.slug).toLowerCase().includes(q));
    },
    loadPreview() {
      if (!this.slug) { this.preview = ''; return; }
      const exts = ['svg','png','webp'];
      let i = 0;
      const tryNext = () => {
        if (i >= exts.length) { this.preview = ''; return; }
        const url = '/media_serve.php?m=' + encodeURIComponent(this.kind + '/' + this.slug + '.' + exts[i]) + '&_=' + Date.now();
        const img = new Image();
        img.onload = () => { this.preview = url; };
        img.onerror = () => { i++; tryNext(); };
        img.src = url;
      };
      tryNext();
    },
    init() {
      this.$watch('slug', () => this.loadPreview());
    },
    async upload() {
      // Prefer file from shared image_upload input
      const shared = document.querySelector('#brandlogo_upload_file, input[name=file]');
      if (shared && shared.files && shared.files[0]) this.file = shared.files[0];

      if (!this.slug || !this.file) return;
      this.uploading = true; this.msg = 'Uploading…';
      try {
        const fd = new FormData();
        fd.append('action', 'master_logo_upload');
        fd.append('kind', this.kind);
        fd.append('slug', this.slug);
        fd.append('file', this.file);
        const csrf = (window.APP && window.APP.csrf) || '';
        if (csrf) fd.append('csrf_token', csrf);
        const r = await fetch('index.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd, credentials: 'same-origin' });
        const j = await r.json();
        if (j.status === 'success') {
          this.preview = j.url + '&_=' + Date.now();
          this.msg = 'Saved.';
          this.file = null;
        } else {
          this.msg = j.message || 'Failed';
        }
      } catch (e) {
        this.msg = String(e.message || e);
      } finally {
        this.uploading = false;
      }
    }
  };
}
</script>
