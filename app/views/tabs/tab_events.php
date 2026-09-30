<?php 
// tab_events.php — Version: 260916.14
// CHANGELOG v8.0: emoji removed from the WhatsApp share text entirely.
// Root cause confirmed elsewhere in the app: WhatsApp's own wa.me →
// api.whatsapp.com redirect corrupts emoji-range characters server-side —
// this is not something fixable from our code. Plain rule/separator
// characters (─, ·) are unaffected and now carry the visual hierarchy
// instead. window.WA_EMOJIS is no longer used anywhere in the app and can
// be removed from dashboard.php if desired.
if (!defined('BASE_PATH')) exit;
?>

<script>
window.__shareEventWA = function(ev) {
    var eSep = '\n\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n';

    var name  = (ev.name  || 'Event').trim();
    var loc   = ev.location ? String(ev.location).trim() : '';
    var desc  = ev.description ? String(ev.description).trim() : '';
    var isUrl = loc.length > 0 && (loc.indexOf('http://') === 0 || loc.indexOf('https://') === 0);

    var dt = '';
    if (ev.date) {
        var d = new Date(ev.date);
        dt = d.toLocaleDateString('en-IN', {weekday:'short', day:'numeric', month:'short', year:'numeric'})
           + ' \u00B7 '
           + d.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', hour12:true}).toUpperCase();
    }

    var msg = '*' + name + '*\n';
    if (dt)             msg += dt  + '\n';
    if (loc && !isUrl)  msg += loc + '\n';
    if (ev.is_virtual)  msg += '_Online / Virtual_\n';
    if (desc)           msg += eSep + desc + eSep.trimEnd();
    if (ev.url)         msg += '\n' + ev.url;
    if (loc && isUrl)   msg += '\n' + loc;

    window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank');
};
</script>

<div class="w-full flex flex-col h-full" x-data="{ eventFilter: 'all', density: 'compact' }">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5 bg-white p-3.5 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center gap-2">
            <button @click="eventFilter = 'all'" 
                    :class="eventFilter === 'all' ? 'bg-slate-800 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" 
                    class="px-3.5 py-2 rounded-lg text-xs font-bold transition">
                All Events
            </button>
            <button @click="eventFilter = 'upcoming'" 
                    :class="eventFilter === 'upcoming' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" 
                    class="px-3.5 py-2 rounded-lg text-xs font-bold transition">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i> Upcoming
            </button>
        </div>
        <div class="flex items-center gap-2 ml-auto">
            <button @click="density = (density === 'compact' ? 'relaxed' : 'compact')" 
                    class="px-3 py-2 text-slate-500 hover:text-blue-600 bg-slate-50 hover:bg-blue-50 border border-slate-200 rounded-lg text-xs font-bold transition flex items-center gap-1.5 outline-none">
                <i class="fa-solid" :class="density === 'compact' ? 'fa-list' : 'fa-bars'"></i> 
                <span class="hidden md:inline" x-text="density === 'compact' ? 'Relaxed View' : 'Compact View'"></span>
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col flex-1">
        <div class="overflow-y-auto flex-1 hide-scrollbar">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase text-slate-500 tracking-wider sticky top-0 z-10">
                    <tr>
                        <th class="p-4 font-bold w-36">Date & Time</th>
                        <th class="p-4 font-bold">Event Details</th>
                        <th class="p-4 font-bold hidden md:table-cell w-44">Location</th>
                        <th class="p-4 text-right font-bold w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <template x-for="e in filteredList.filter(ev => {
                        // Compare date-only so today's events remain 'upcoming' all day (not just until event time)
                        if(eventFilter === 'upcoming') return new Date(ev.date).toDateString() >= new Date().toDateString();
                        return true;
                    })" :key="e.id">
                        <tr class="hover:bg-slate-50/60 transition group" 
                            :class="new Date(e.date).toDateString() < new Date().toDateString() ? 'opacity-50 hover:opacity-80' : ''">
                            
                            <td class="p-4 align-top" :class="density === 'compact' ? 'py-3' : 'py-5'">
                                <div class="font-bold text-slate-800 text-sm tabular-nums" 
                                     x-text="new Date(e.date).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})"></div>
                                <div class="text-[11px] text-blue-600 font-bold mt-0.5 uppercase tracking-wide" 
                                     x-text="new Date(e.date).toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'})"></div>
                            </td>
                            
                            <td class="p-4 align-top" :class="density === 'compact' ? 'py-3' : 'py-5'">
                                <div class="font-bold text-slate-800" x-text="e.name"></div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                    <span x-show="e.type" class="text-[10px] bg-slate-100 border border-slate-200 text-slate-600 px-2 py-0.5 rounded font-bold uppercase tracking-wider" x-text="e.type"></span>
                                    <span x-show="e.is_virtual" class="text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded font-bold uppercase tracking-wider flex items-center gap-1">
                                        <i class="fa-solid fa-video text-[9px]"></i> Virtual
                                    </span>
                                    <span x-show="new Date(e.date).toDateString() < new Date().toDateString()" class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded font-bold uppercase tracking-wider">Past</span>
                                </div>
                                <div x-show="e.description && density === 'relaxed'" class="text-xs text-slate-500 mt-2 max-w-2xl leading-relaxed" x-text="e.description"></div>
                            </td>
                            
                            <td class="p-4 align-top hidden md:table-cell text-xs text-slate-500 font-medium" :class="density === 'compact' ? 'py-3' : 'py-5'">
                                <div class="flex items-start gap-1.5">
                                    <i class="fa-solid mt-0.5 shrink-0 text-slate-400 text-[10px]" :class="e.is_virtual ? 'fa-video' : 'fa-location-dot'"></i>
                                    <span x-text="e.location || (e.is_virtual ? 'Online' : 'TBD')"></span>
                                </div>
                                <a x-show="e.url" :href="e.url" target="_blank" class="inline-flex items-center gap-1 mt-1.5 text-[11px] font-bold text-blue-600 hover:text-blue-800 transition">
                                    Join Link <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                </a>
                            </td>
                            
                            <td class="p-4 align-top text-right" :class="density === 'compact' ? 'py-3' : 'py-5'">
                                <div class="flex justify-end gap-1.5 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition">
                                    <button @click.stop="window.__shareEventWA(e)" class="ctrl-btn hover:text-green-600" title="Share on WhatsApp">
                                        <i class="fa-brands fa-whatsapp text-xs"></i>
                                    </button>
                                    <?php if($isAdmin): ?>
                                    <button @click.stop="window.openModalEditor(true, 'events', e)" class="ctrl-btn hover:text-blue-600" title="Edit">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button @click.stop="deleteItem(e.id, 'events')" class="ctrl-btn hover:text-red-600" title="Delete">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </template>
                    
                    <template x-if="filteredList.filter(ev => eventFilter === 'upcoming' ? new Date(ev.date).toDateString() >= new Date().toDateString() : true).length === 0">
                        <tr>
                            <td colspan="4" class="p-12 text-center text-slate-400">
                                <i class="fa-regular fa-calendar-xmark text-3xl mb-3 opacity-40 block"></i>
                                <span class="text-sm font-semibold">No matching events found.</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
