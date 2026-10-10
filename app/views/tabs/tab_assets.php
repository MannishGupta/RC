<?php
/** Asset checkout registry. Version: 260921.40 */
if (!defined('BASE_PATH')) exit;
?>
<div class="space-y-4" x-data="valueAssets()" x-init="load()">

  <div x-show="!assets.length" x-cloak class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
    <i class="fa-solid fa-box-open text-3xl text-slate-300 mb-3"></i>
    <p class="text-sm font-bold text-slate-700">No assets yet</p>
    <p class="text-xs text-slate-500 mt-1">Provision your first asset to track checkouts.</p>
  </div>

  <div>
    <h2 class="text-base font-bold text-slate-800">Asset Checkout Registry</h2>
    <p class="text-xs text-slate-500">Phones, keys, and equipment assigned to staff with due dates.</p>
  </div>
  <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-bold">
        <tr>
          <th class="text-left px-3 py-2">Asset</th>
          <th class="text-left px-3 py-2">Status</th>
          <th class="text-left px-3 py-2">Assigned</th>
          <th class="text-left px-3 py-2">Due</th>
          <th class="text-right px-3 py-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <template x-for="a in assets" :key="a.id">
          <tr class="border-t border-slate-100">
            <td class="px-3 py-2">
              <div class="font-semibold text-slate-800" x-text="a.name"></div>
              <div class="text-[11px] text-slate-400" x-text="a.category + (a.serial ? ' · '+a.serial : '')"></div>
            </td>
            <td class="px-3 py-2"><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-100" x-text="a.status"></span></td>
            <td class="px-3 py-2 text-xs" x-text="a.assigned_name || '—'"></td>
            <td class="px-3 py-2 text-xs tabular-nums" x-text="a.due_date || '—'"></td>
            <td class="px-3 py-2 text-right">
              <button type="button" class="text-xs font-bold text-blue-600" @click="edit(a)">Edit</button>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
  <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm" x-show="form">
    <h3 class="text-sm font-bold mb-2" x-text="form.id ? 'Modify asset' : 'Provision asset'"></h3>
    <div class="grid sm:grid-cols-2 gap-2">
      <input class="h-9 px-2 border rounded-lg text-sm" x-model="form.name" placeholder="Name">
      <input class="h-9 px-2 border rounded-lg text-sm" x-model="form.category" placeholder="Category">
      <input class="h-9 px-2 border rounded-lg text-sm" x-model="form.serial" placeholder="Serial">
      <select class="h-9 px-2 border rounded-lg text-sm" x-model="form.status">
        <option value="available">Available</option>
        <option value="checked_out">Checked out</option>
        <option value="maintenance">Maintenance</option>
        <option value="retired">Retired</option>
      </select>
      <input class="h-9 px-2 border rounded-lg text-sm" x-model="form.assigned_name" placeholder="Assigned to (name)">
      <input class="h-9 px-2 border rounded-lg text-sm" x-model="form.due_date" type="date">
    </div>
    <div class="flex gap-2 mt-3">
      <button type="button" class="h-9 px-4 rounded-lg bg-blue-600 text-white text-xs font-bold" @click="save()">Commit</button>
      <button type="button" class="h-9 px-4 rounded-lg border text-xs font-bold" @click="form=null">Cancel</button>
      <button type="button" class="h-9 px-4 rounded-lg border text-xs font-bold ml-auto" @click="form={id:'',name:'',category:'General',serial:'',status:'available',assigned_name:'',due_date:'',notes:''}">+ New</button>
    </div>
  </div>
  <button type="button" class="text-xs font-bold text-blue-600" x-show="!form" @click="form={id:'',name:'',category:'General',serial:'',status:'available',assigned_name:'',due_date:'',notes:''}">+ Provision asset</button>
</div>
<script>
function valueAssets() {
  return {
    assets: [], form: null, loading: true,
    async api(body) {
      const fd = new FormData();
      Object.entries(body).forEach(([k,v]) => fd.append(k, v ?? ''));
      return (await fetch('api_value.php', { method:'POST', body: fd, credentials:'same-origin' })).json();
    },
    async load() {
      const r = await this.api({ action: 'vp_list', type: 'assets' });
      if (r.status === 'ok') this.assets = r.data || []; this.loading = false;
    },
    edit(a) { this.form = { ...a }; },
    async save() {
      await this.api({ action: 'asset_save', ...this.form });
      this.form = null;
      await this.load();
    }
  };
}
</script>
