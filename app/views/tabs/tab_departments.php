<?php
// Version: 260918.16 — identical card UI to designations
if (!defined('BASE_PATH')) exit;
?>
<div class="w-full flex flex-col space-y-4" x-data="{ openId: null }">

    <template x-if="paginatedList.length === 0">
        <div class="flex flex-col items-center justify-center h-56 text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50/50">
            <i class="fa-solid fa-sitemap text-3xl text-slate-300 mb-3"></i>
            <p class="text-sm font-semibold text-slate-600">No departments yet</p>
            <p class="text-xs text-slate-400 mt-1">Press + to create one</p>
        </div>
    </template>

    <template x-if="paginatedList.length > 0">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <template x-for="i in paginatedList" :key="i.id || i.code">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="bg-gradient-to-r from-slate-800 to-slate-700 px-4 py-3 flex items-center justify-between">
                        <div class="min-w-0">
                            <div class="text-white font-bold truncate" x-text="i.name || 'Unnamed'"></div>
                            <div class="text-[10px] font-mono text-slate-300 uppercase tracking-widest" x-text="i.code || i.id"></div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0 no-print">
                            <button type="button"
                                    class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center"
                                    :title="openId === (i.id || i.code) ? 'Hide members' : 'Show members'"
                                    @click="openId = openId === (i.id || i.code) ? null : (i.id || i.code)">
                                <i class="fa-solid" :class="openId === (i.id || i.code) ? 'fa-minus' : 'fa-plus'"></i>
                            </button>
                            <?php if (!empty($isAdmin)): ?>
                            <button type="button" @click="window.openModalEditor(true, 'departments', i)"
                                    class="w-8 h-8 rounded-lg bg-white/10 hover:bg-blue-500/80 text-white flex items-center justify-center" title="Edit">
                                <i class="fa-solid fa-pen text-xs"></i>
                            </button>
                            <button type="button" @click="deleteItem(i.id, 'departments')"
                                    class="w-8 h-8 rounded-lg bg-white/10 hover:bg-red-500/80 text-white flex items-center justify-center" title="Delete">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-4 flex-1 flex flex-col gap-2">
                        <div class="text-[11px] font-semibold text-slate-500">
                            <i class="fa-solid fa-users mr-1 text-slate-400"></i>
                            <span x-text="(data.team || []).filter(m => String(m.department_id || m.department_code || '') === String(i.id || i.code || '')).length"></span>
                            member(s)
                        </div>
                        <div x-show="openId === (i.id || i.code)" x-cloak class="mt-1 pt-2 border-t border-slate-100 space-y-1.5">
                            <template x-for="m in (data.team || []).filter(m => String(m.department_id || m.department_code || '') === String(i.id || i.code || ''))" :key="m.id || m.slug">
                                <div class="flex items-center gap-2 text-sm text-slate-700">
                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-[10px] font-bold text-slate-600 flex items-center justify-center shrink-0"
                                          x-text="(m.name || '?').split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase()"></span>
                                    <span class="font-semibold truncate" x-text="m.name || 'Member'"></span>
                                    <span class="text-[10px] text-slate-400 truncate ml-auto" x-text="m.designation_name || ''"></span>
                                </div>
                            </template>
                            <div class="text-[11px] text-slate-400 italic"
                                 x-show="(data.team || []).filter(m => String(m.department_id || m.department_code || '') === String(i.id || i.code || '')).length === 0">
                                No members in this department
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
