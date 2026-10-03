<?php
/**
 * Locked searchable master dropdown (banks | oems).
 * Usage: $masterKind = 'banks'|'oems'; $masterModel = 'form.bank_name';
 * Version: 20260929.35
 */
$masterKind = $masterKind ?? 'banks';
$masterModel = $masterModel ?? 'form.bank_name';
$masterId = $masterId ?? ('md-' . preg_replace('/\\W+/', '-', $masterKind . '-' . $masterModel));
?>
<div class="relative" x-data="masterDropdown('<?= htmlspecialchars($masterKind, ENT_QUOTES) ?>')" @click.outside="open=false">
  <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1"><?= $masterKind === 'oems' ? 'Vehicle manufacturer' : 'Bank' ?> (locked list)</label>
  <button type="button" @click="open=!open; if(open) $nextTick(() => $refs.q && $refs.q.focus())"
          class="w-full flex items-center gap-2 h-11 px-3 rounded-lg border border-slate-200 bg-white text-left text-sm font-semibold">
    <img x-show="selected && selected.logo" :src="selected && selected.logo" class="w-6 h-6 object-contain" alt="" referrerpolicy="no-referrer">
    <span class="truncate flex-1" x-text="selected ? selected.label : 'Select…'"></span>
    <i class="fa-solid fa-chevron-down text-xs text-slate-400"></i>
  </button>
  <div x-show="open" x-cloak class="absolute z-40 mt-1 w-full max-h-64 overflow-auto rounded-xl border border-slate-200 bg-white shadow-xl">
    <div class="p-2 border-b border-slate-100 sticky top-0 bg-white">
      <input x-ref="q" type="search" x-model="q" placeholder="Search…" class="w-full h-9 px-3 rounded-md border border-slate-200 text-sm">
    </div>
    <template x-for="opt in filtered" :key="opt.id">
      <button type="button" class="w-full flex items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50"
              @click="pick(opt); <?= htmlspecialchars($masterModel, ENT_QUOTES) ?> = opt.label">
        <img :src="opt.logo" alt="" class="w-6 h-6 object-contain" loading="lazy" referrerpolicy="no-referrer" @error="$el.style.visibility='hidden'">
        <span class="font-semibold truncate" x-text="opt.label"></span>
      </button>
    </template>
    <p class="px-3 py-2 text-xs text-slate-400" x-show="filtered.length===0">No matches in master list.</p>
  </div>
</div>
<script>
window.masterDropdown = window.masterDropdown || function(kind) {
  return {
    open: false, q: '', items: [], selected: null,
    get filtered() {
      var q = (this.q || '').toLowerCase();
      if (!q) return this.items;
      return this.items.filter(function(i){ return (i.label||'').toLowerCase().indexOf(q) !== -1; });
    },
    init() {
      var self = this;
      fetch('api_masters.php?kind=' + encodeURIComponent(kind)).then(function(r){ return r.json(); }).then(function(j){
        self.items = (j && j.items) ? j.items : [];
      }).catch(function(){ self.items = []; });
    },
    pick(opt) { this.selected = opt; this.open = false; this.q = ''; }
  };
};
</script>
