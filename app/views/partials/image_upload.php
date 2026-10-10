<?php
/**
 * Shared image upload control — hint + accept + live preview.
 * Alpine logic lives in window.rcImageUpload() so x-data never embeds JS strings.
 * Brand fields (logo/favicon/cover) also stash the File for reliable FormData submit.
 */
if (!defined('BASE_PATH')) {
    return;
}
$iuField = (string)($iuField ?? 'photo');
$iuSpec = class_exists('AppMedia') ? AppMedia::imageSpec($iuField) : [
    'label' => 'Image', 'hint' => 'PNG, JPG, WebP', 'accept' => 'image/*', 'maxKB' => 450, 'allowSvg' => false,
];
$iuLabel = (string)($iuLabel ?? ($iuSpec['label'] ?? 'Image'));
$iuCurrent = (string)($iuCurrent ?? '');
$iuInputName = (string)($iuInputName ?? $iuField);
$iuId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($iuId ?? ('iu_' . $iuField . '_' . substr(md5($iuInputName . mt_rand()), 0, 6)))) ?: 'iu_img';
$iuCurrentUrl = '';
if ($iuCurrent !== '') {
    if (str_starts_with($iuCurrent, 'http') || str_starts_with($iuCurrent, '/') || str_starts_with($iuCurrent, 'media_serve')) {
        $iuCurrentUrl = $iuCurrent;
    } else {
        $iuCurrentUrl = 'media_serve.php?f=' . rawurlencode(basename($iuCurrent));
    }
}
$h = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

if (empty($GLOBALS['__rc_image_upload_js'])) {
    $GLOBALS['__rc_image_upload_js'] = true;
    ?>
<script>
window.__rcBrandFiles = window.__rcBrandFiles || { logo: null, favicon: null, cover: null };
window.rcBrandFilePick = function (field, fileOrInput) {
  try {
    var file = null;
    if (fileOrInput && fileOrInput.files) {
      file = fileOrInput.files[0] || null;
    } else if (fileOrInput && typeof fileOrInput.size === 'number') {
      file = fileOrInput;
    }
    if (!field) return;
    window.__rcBrandFiles[field] = file;
  } catch (e) {}
};
window.rcImageUpload = window.rcImageUpload || function () {
  return {
    preview: '',
    err: '',
    pickedName: '',
    _url: null,
    init: function () {
      var cur = (this.$el && this.$el.getAttribute('data-current')) || '';
      this.preview = cur;
    },
    onPick: function (ev) {
      this.err = '';
      this.pickedName = '';
      var input = ev.target;
      var file = input.files && input.files[0];
      if (!file) {
        var field0 = (this.$el && this.$el.getAttribute('data-field')) || '';
        if (field0) window.rcBrandFilePick(field0, null);
        return;
      }
      // Source ceiling 12 MB — server optimises at save
      var maxSrcKb = parseInt((this.$el && this.$el.getAttribute('data-max-src-kb')) || '12288', 10);
      if (file.size > maxSrcKb * 1024) {
        this.err = 'Image is too large (max ' + Math.round(maxSrcKb / 1024) + ' MB).';
        input.value = '';
        var fReject = (this.$el && this.$el.getAttribute('data-field')) || '';
        if (fReject) window.rcBrandFilePick(fReject, null);
        return;
      }
      var accept = (input.getAttribute('accept') || '').toLowerCase();
      var name = (file.name || '').toLowerCase();
      var type = (file.type || '').toLowerCase();
      if (accept) {
        var ok = accept.split(',').some(function (a) {
          a = a.trim();
          if (!a) return false;
          if (a.charAt(0) === '.') return name.slice(-a.length) === a;
          if (a.slice(-2) === '/*') return type.indexOf(a.slice(0, -1)) === 0;
          return type === a;
        });
        // Extension fallback (browsers often leave type empty for ico/svg)
        if (!ok) {
          var ext = (name.split('.').pop() || '');
          if (ext && accept.indexOf('.' + ext) !== -1) ok = true;
          if (!ok && type.indexOf('image/') === 0 && accept.indexOf('image/') !== -1) ok = true;
          if (!ok && /\.ico$/i.test(name) && accept.indexOf('.ico') !== -1) ok = true;
          if (!ok && /\.svg$/i.test(name) && accept.indexOf('.svg') !== -1) ok = true;
        }
        if (!ok) {
          this.err = 'Unsupported file type. Use: ' + accept.replace(/image\//g, '').replace(/\./g, '');
          input.value = '';
          var fBad = (this.$el && this.$el.getAttribute('data-field')) || '';
          if (fBad) window.rcBrandFilePick(fBad, null);
          return;
        }
      }
      // Stash brand files for Organisation Setup submit
      var field = (this.$el && this.$el.getAttribute('data-field')) || (input.getAttribute('name') || '');
      if (field === 'logo' || field === 'favicon' || field === 'cover') {
        window.rcBrandFilePick(field, file);
      }
      this.pickedName = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
      if (this._url) {
        try { URL.revokeObjectURL(this._url); } catch (e) {}
      }
      this._url = URL.createObjectURL(file);
      this.preview = this._url;
    }
  };
};
</script>
    <?php
}
?>
<div class="rc-image-upload"
     data-field="<?= $h($iuField) ?>"
     data-max-kb="<?= (int)($iuSpec['maxKB'] ?? 450) ?>"
     data-max-src-kb="12288"
     data-current="<?= $h($iuCurrentUrl) ?>"
     x-data="rcImageUpload()">
  <label class="block text-xs font-bold text-slate-600 mb-1" for="<?= $h($iuId) ?>_file"><?= $h($iuLabel) ?></label>
  <div class="flex items-start gap-3">
    <div class="w-20 h-20 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden shrink-0">
      <template x-if="preview">
        <img :src="preview" alt="" class="max-w-full max-h-full object-contain">
      </template>
      <span x-show="!preview" class="text-[10px] text-slate-400">No image</span>
    </div>
    <div class="min-w-0 flex-1">
      <label class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:border-blue-300 cursor-pointer">
        <i class="fa-solid fa-upload text-[11px]" aria-hidden="true"></i> Browse / Upload
        <input id="<?= $h($iuId) ?>_file" type="file" name="<?= $h($iuInputName) ?>"
               data-brand="<?= $h($iuField) ?>"
               accept="<?= $h((string)($iuSpec['accept'] ?? 'image/*')) ?>"
               class="hidden" @change="onPick($event)">
      </label>
      <p class="text-[11px] text-emerald-700 mt-1 font-medium" x-show="pickedName" x-text="'Selected: ' + pickedName"></p>
      <p class="text-[11px] text-slate-500 mt-1.5 leading-snug"><?= $h((string)($iuSpec['hint'] ?? '')) ?></p>
      <?php if (!empty($iuSpec['used_for'])): ?>
      <p class="text-[11px] text-slate-600 mt-1 leading-snug"><span class="font-semibold text-slate-700">Used for:</span> <?= $h((string)$iuSpec['used_for']) ?></p>
      <?php endif; ?>
      <p class="text-[11px] text-rose-600 mt-1" x-show="err" x-text="err"></p>
    </div>
  </div>
</div>
