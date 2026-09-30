<?php
/** Document expiry radar. Version: 260921.40 */
if (!defined('BASE_PATH')) exit;
?>
<div class="space-y-4" x-data="valueExpiry()" x-init="scan()">
  <div class="flex flex-wrap justify-between gap-2">
    <div>
      <h2 class="text-base font-bold text-slate-800">Document Expiry Radar</h2>
      <p class="text-xs text-slate-500">Scans vault records for expiry / valid-till fields (30 / 7 / overdue).</p>
    </div>
    <button type="button" @click="scan()" class="h-9 px-3 rounded-lg bg-slate-800 text-white text-xs font-bold">Rescan</button>
  </div>
  <div class="grid grid-cols-3 gap-2">
    <div class="rounded-xl bg-red-50 border border-red-100 p-3 text-center">
      <div class="text-2xl font-black text-red-700" x-text="items.filter(i=>i.urgency==='expired').length"></div>
      <div class="text-[10px] font-bold uppercase text-red-600">Expired</div>
    </div>
    <div class="rounded-xl bg-amber-50 border border-amber-100 p-3 text-center">
      <div class="text-2xl font-black text-amber-700" x-text="items.filter(i=>i.urgency==='critical').length"></div>
      <div class="text-[10px] font-bold uppercase text-amber-600">≤ 7 days</div>
    </div>
    <div class="rounded-xl bg-blue-50 border border-blue-100 p-3 text-center">
      <div class="text-2xl font-black text-blue-700" x-text="items.filter(i=>i.urgency==='warning').length"></div>
      <div class="text-[10px] font-bold uppercase text-blue-600">≤ 30 days</div>
    </div>
  </div>
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <template x-if="items.length===0">
      <p class="p-6 text-sm text-slate-500 text-center">No expiry dates found on documents. Add <code>expiry</code> or <code>valid_till</code> on vault records.</p>
    </template>
    <ul>
      <template x-for="i in items" :key="i.id + i.expiry">
        <li class="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-50 text-sm">
          <div>
            <div class="font-semibold text-slate-800" x-text="i.title"></div>
            <div class="text-[11px] text-slate-400">Expires <span x-text="i.expiry"></span></div>
          </div>
          <span class="text-[10px] font-black uppercase px-2 py-1 rounded-full"
                :class="{
                  'bg-red-100 text-red-700': i.urgency==='expired',
                  'bg-amber-100 text-amber-800': i.urgency==='critical',
                  'bg-blue-100 text-blue-700': i.urgency==='warning',
                  'bg-slate-100 text-slate-600': i.urgency==='ok'
                }"
                x-text="i.urgency==='expired' ? ('Overdue '+Math.abs(i.days_left)+'d') : (i.days_left+'d left')"></span>
        </li>
      </template>
    </ul>
  </div>
</div>
<script>
function valueExpiry() {
  return {
    items: [],
    async scan() {
      const fd = new FormData(); fd.append('action','expiry_scan');
      const r = await (await fetch('api_value.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
      if (r.status==='ok') this.items = r.items || [];
    }
  };
}
</script>
