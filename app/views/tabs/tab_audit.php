<?php
/** Audit trail viewer. Version: 260921.40 */
if (!defined('BASE_PATH')) exit;
?>
<div class="space-y-4" x-data="valueAudit()" x-init="load()">
  <div class="flex justify-between gap-2">
    <div>
      <h2 class="text-base font-bold text-slate-800">Audit Ledger</h2>
      <p class="text-xs text-slate-500">Append-only log of value-pack and related administrative events.</p>
    </div>
    <button type="button" @click="load()" class="h-9 px-3 rounded-lg border text-xs font-bold">Refresh</button>
  </div>
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-auto max-h-[70vh]">
    <table class="w-full text-xs">
      <thead class="bg-slate-50 sticky top-0">
        <tr class="text-left text-[10px] uppercase text-slate-500">
          <th class="px-3 py-2">When</th>
          <th class="px-3 py-2">Action</th>
          <th class="px-3 py-2">Entity</th>
          <th class="px-3 py-2">IP</th>
        </tr>
      </thead>
      <tbody>
        <template x-for="r in rows.slice().reverse()" :key="r.id">
          <tr class="border-t border-slate-50">
            <td class="px-3 py-1.5 tabular-nums text-slate-500" x-text="(r.at||'').replace('T',' ').slice(0,19)"></td>
            <td class="px-3 py-1.5 font-semibold" x-text="r.action"></td>
            <td class="px-3 py-1.5" x-text="r.entity"></td>
            <td class="px-3 py-1.5 text-slate-400" x-text="r.ip"></td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</div>
<script>
function valueAudit() {
  return {
    rows: [],
    async load() {
      const fd = new FormData(); fd.append('action','vp_list'); fd.append('type','audit_log');
      const r = await (await fetch('api_value.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
      if (r.status==='ok') this.rows = r.data || [];
    }
  };
}
</script>
