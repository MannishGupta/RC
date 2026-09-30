<?php
/** Ctrl+K command palette — tabs + people jump */
$isSa = !empty($isSuperAdmin);
?>
<div id="rc-cmdk" class="fixed inset-0 z-[2147483000] hidden items-start justify-center pt-[12vh] px-4" style="background:rgba(15,23,42,0.45)" x-data="rcCmdk()" @keydown.window.prevent.ctrl.k="open()" @keydown.window.prevent.meta.k="open()" x-show="visible" x-cloak>
  <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 shadow-2xl overflow-hidden" @click.outside="close()" role="dialog" aria-modal="true" aria-label="Command palette">
    <div class="flex items-center gap-2 px-3 border-b border-slate-100">
      <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm"></i>
      <input type="search" x-model="q" @input="filter()" @keydown.escape.prevent="close()" @keydown.enter.prevent="go()"
        placeholder="Jump to tab or person… (Ctrl+K)"
        class="w-full py-3 text-sm bg-transparent outline-none text-slate-900" x-ref="input">
    </div>
    <ul class="max-h-72 overflow-y-auto py-1" role="listbox">
      <template x-for="(item, idx) in filtered" :key="item.id">
        <li>
          <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center gap-2"
            :class="idx===active ? 'bg-blue-50 text-blue-900' : 'text-slate-800'"
            @mouseenter="active=idx" @click="select(item)">
            <i class="fa-solid text-xs w-4 text-slate-400" :class="item.icon"></i>
            <span x-text="item.label"></span>
            <span class="ml-auto text-[10px] text-slate-400" x-text="item.kind"></span>
          </button>
        </li>
      </template>
      <li x-show="filtered.length===0" class="px-3 py-4 text-xs text-slate-500">No matches</li>
    </ul>
  </div>
</div>
<script>
function rcCmdk(){
  return {
    visible:false, q:'', active:0, items:[], filtered:[],
    init(){
      var tabs = [
        {id:'team', label:'Human Capital Index', kind:'tab', icon:'fa-users', href:'?tab=team'},
        {id:'docs', label:'Document Vault', kind:'tab', icon:'fa-folder-open', href:'?tab=docs'},
        {id:'bank', label:'Treasury', kind:'tab', icon:'fa-building-columns', href:'?tab=bank'},
        {id:'events', label:'Calendar', kind:'tab', icon:'fa-calendar', href:'?tab=events'},
        {id:'numero', label:'Vedic Numero', kind:'tab', icon:'fa-wand-magic-sparkles', href:'?tab=numero'},
        {id:'blood', label:'Blood Report', kind:'link', icon:'fa-droplet', href:'blood_report.php'},
        {id:'locations', label:'Locations', kind:'tab', icon:'fa-map-location-dot', href:'?tab=locations'},
      ];
      <?php if (!empty($isSuperAdmin)): ?>
      tabs.push({id:'tenants', label:'Tenant Setup', kind:'tab', icon:'fa-building-user', href:'?tab=tenants'});
      tabs.push({id:'monitor', label:'Monitor / Optimisation', kind:'tab', icon:'fa-heart-pulse', href:'?tab=monitor'});
      <?php endif; ?>
      this.items = tabs.slice();
      var self = this;
      fetch('index.php?action=team_public_json', {credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
        var list = Array.isArray(j) ? j : [];
        list.forEach(function(m){
          self.items.push({
            id: 'p-'+(m.slug||m.id),
            label: m.name || 'Member',
            kind: 'person',
            icon: 'fa-id-card',
            href: '?card=business&slug=' + encodeURIComponent(m.slug || '')
          });
        });
        self.filter();
      }).catch(function(){});
      // Bind palette open without Alpine on wrapper if x-show needs Alpine parent
      document.addEventListener('keydown', function(e){
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
          e.preventDefault();
          var el = document.getElementById('rc-cmdk');
          if (!el) return;
          el.classList.remove('hidden');
          el.classList.add('flex');
          var inp = el.querySelector('input');
          if (inp) { inp.focus(); inp.select(); }
        }
        if (e.key === 'Escape') {
          var el = document.getElementById('rc-cmdk');
          if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
        }
      });
    },
    open(){ this.visible = true; this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
      var el = document.getElementById('rc-cmdk'); if(el){ el.classList.remove('hidden'); el.classList.add('flex'); }
    },
    close(){ this.visible = false;
      var el = document.getElementById('rc-cmdk'); if(el){ el.classList.add('hidden'); el.classList.remove('flex'); }
    },
    filter(){
      var q = (this.q||'').toLowerCase().trim();
      this.filtered = !q ? this.items.slice(0, 12) : this.items.filter(function(i){
        return (i.label+i.kind).toLowerCase().indexOf(q) !== -1;
      }).slice(0, 12);
      this.active = 0;
    },
    select(item){ if(item && item.href) location.href = item.href; },
    go(){ if(this.filtered[this.active]) this.select(this.filtered[this.active]); }
  };
}
// Ensure palette mounts even if Alpine init order varies
document.addEventListener('DOMContentLoaded', function(){
  var el = document.getElementById('rc-cmdk');
  if (el && !el.classList.contains('hidden') === false) { /* keep hidden */ }
});
</script>
