<?php
/** Org chart from team rank / manager_id. Version: 260921.40 */
if (!defined('BASE_PATH')) exit;
?>
<div class="space-y-4" x-data="valueOrg()" x-init="load()">
  <div>
    <h2 class="text-base font-bold text-slate-800">Organizational Chart</h2>
    <p class="text-xs text-slate-500">Derived from Human Capital Index rank and optional <code>manager_id</code> / <code>reports_to</code>.</p>
  </div>
  <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
    <template x-if="people.length===0">
      <p class="text-sm text-slate-500 text-center py-8">No people loaded. Ensure team data is available.</p>
    </template>
    <ol class="space-y-2">
      <template x-for="p in people" :key="p.id||p.name">
        <li class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-100">
          <span class="w-8 h-8 rounded-lg bg-slate-800 text-amber-200 text-xs font-black flex items-center justify-center tabular-nums"
                x-text="p.rank < 9999 ? p.rank : '—'"></span>
          <div class="min-w-0 flex-1">
            <div class="font-bold text-slate-800 text-sm truncate" x-text="p.name"></div>
            <div class="text-[11px] text-slate-500 truncate" x-text="[p.designation, p.department].filter(Boolean).join(' · ')"></div>
          </div>
          <span class="text-[10px] text-slate-400" x-show="p.manager_id" x-text="'→ '+p.manager_id"></span>
        </li>
      </template>
    </ol>
  </div>
</div>
<script>
function valueOrg() {
  return {
    people: [],
    async load() {
      const fd = new FormData(); fd.append('action','org_tree');
      const r = await (await fetch('api_value.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
      if (r.status==='ok') this.people = r.people || [];
    }
  };
}
</script>
