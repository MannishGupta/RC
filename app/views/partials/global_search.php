<?php
/** Instant search over team_public.json */
?>
<div class="mb-4 no-print" x-data="rcGlobalSearch()" x-cloak>
  <label class="sr-only" for="rc-gsearch">Search directory</label>
  <div class="relative max-w-xl">
    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
    <input id="rc-gsearch" type="search" autocomplete="off"
      x-model="q" @input.debounce.200ms="run()"
      placeholder="Search people (name, phone, role)…"
      class="w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm text-slate-900 shadow-sm focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
  </div>
  <ul x-show="q.length > 1 && results.length" class="mt-2 max-w-xl rounded-xl border border-slate-200 bg-white shadow-lg divide-y divide-slate-100 max-h-64 overflow-y-auto z-20 relative" role="listbox">
    <template x-for="r in results" :key="r.slug || r.id">
      <li>
        <a :href="'?card=business&slug=' + encodeURIComponent(r.slug || '')" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 text-sm text-slate-800">
          <span class="font-semibold" x-text="r.name"></span>
          <span class="text-xs text-slate-500" x-text="r.designation_name || r.designation || ''"></span>
        </a>
      </li>
    </template>
  </ul>
  <p class="text-[10px] text-slate-400 mt-1" x-show="ready" x-text="'Indexed ' + total + ' public profiles'"></p>
</div>
<script>
function rcGlobalSearch(){
  return {
    q:'', results:[], total:0, data:[], ready:false,
    async init(){
      try {
        var r2 = await fetch('index.php?action=team_public_json', { credentials:'same-origin' });
        var j = r2.ok ? await r2.json() : [];
        this.data = Array.isArray(j) ? j : (j && j.data ? j.data : []);
        this.total = this.data.length;
      } catch(e) {}
      this.ready = true;
    },
    run(){
      var q = (this.q||'').toLowerCase().trim();
      if (q.length < 2) { this.results = []; return; }
      this.results = this.data.filter(function(m){
        return [m.name,m.phone,m.email,m.designation,m.designation_name,m.department_name,m.slug]
          .map(function(x){return String(x||'').toLowerCase();}).join(' ').indexOf(q) !== -1;
      }).slice(0,12);
    }
  };
}
</script>
