<?php
// app/views/partials/optimizer_panel.php — Version: 260916.14
//
// SINGLE SOURCE for the System Optimizer UI. Included by app/views/tabs/tab_opt.php.
//
// WHY THIS EXISTS
// Both tabs already called the SAME dashboardApp.runOptimise() and shared the
// same optLogs / optLoading state — the JavaScript was never duplicated. What
// WAS duplicated was the UI: two buttons with different labels and two
// separately-styled output consoles that could drift apart.
//
// MORE IMPORTANTLY, the two labels disagreed about what the button does:
//   - Optimise tab  : "Run Diagnostics"
//   - Monitor tab   : "Team Slug Optimizer" — "Normalises slugs, phone
//                     numbers, and names for all team members."
// Both fire the identical full SystemDataOptimizer::run(true), which is FIVE
// phases, not a slug pass:
//   1. Schema enforcement + field normalisation across every namespace
//   2. Slug / phone / photo migration for team
//   3. Rebuild of the active-media index
//   4. ORPHAN FILE CLEANUP — permanently unlink()s images and documents
//      not referenced by any record
//   5. Log + session housekeeping
// Phase 4 deletes files. Labelling that "Team Slug Optimizer" understates it
// badly. This panel names the operation honestly and states up front that
// unreferenced media will be removed, so the confirm dialog is an informed one.
//
// USAGE — set an optional variant before including:
//     $optPanelCompact = true;                       // Monitor tab (denser)
//     require __DIR__ . '/../partials/optimizer_panel.php';
// Requires dashboardApp Alpine scope (runOptimise / optLoading / optLogs / data).
if (!defined('BASE_PATH')) exit;

$optPanelCompact = $optPanelCompact ?? false;
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 <?= $optPanelCompact ? 'p-6' : 'p-6 md:p-8' ?>">

    <!-- Header + primary action -->
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 <?= $optPanelCompact ? 'mb-5' : 'mb-6 pb-6 border-b border-slate-100' ?>">
        <div class="min-w-0">
            <div class="flex items-center gap-3 mb-1">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shadow-sm border border-blue-100 shrink-0">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <h2 class="<?= $optPanelCompact ? 'text-base' : 'text-xl' ?> font-black text-slate-800">System Optimizer</h2>
            </div>
            <p class="text-slate-500 text-sm ml-[52px] max-w-lg">
                Enforces the data schema, normalises team slugs, phone numbers and names,
                then clears expired sessions and trims logs.
            </p>
        </div>

        <button @click="runOptimise()"
                :disabled="optLoading"
                class="shrink-0 px-6 py-3 bg-slate-900 hover:bg-blue-600 active:bg-blue-700 text-white font-bold rounded-xl shadow-lg transition-all focus:ring-4 focus:ring-blue-200 flex items-center justify-center gap-3 min-w-[200px] outline-none disabled:opacity-70 disabled:cursor-not-allowed">
            <i class="fa-solid fa-play" x-show="!optLoading"></i>
            <i class="fa-solid fa-circle-notch fa-spin" x-show="optLoading" x-cloak></i>
            <span x-text="optLoading ? 'Running…' : 'Run Optimizer'"></span>
        </button>
    </div>

    <!-- Backup. Placed immediately above the destructive-action warning
         because that warning tells the admin to have a recent backup — and
         until 260906.21 the application gave them no way to make one. -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl mb-4">
        <div class="w-9 h-9 bg-white text-slate-500 rounded-lg flex items-center justify-center border border-slate-200 shrink-0">
            <i class="fa-solid fa-box-archive"></i>
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-sm font-bold text-slate-800">Download a backup first</div>
            <p class="text-xs text-slate-500 mt-0.5">
                One ZIP containing <code class="font-mono">data</code>, <code class="font-mono">images</code>
                and <code class="font-mono">docs</code>. Live sessions are excluded.
            </p>
        </div>
        <a href="tools/backup.php"
           class="shrink-0 px-4 py-2 bg-white hover:bg-slate-900 hover:text-white border border-slate-300 text-slate-700 text-xs font-bold rounded-lg transition flex items-center gap-2">
            <i class="fa-solid fa-download text-[10px]"></i> Download backup
        </a>
    </div>

    <!-- Destructive-action notice. Phase 4 unlink()s unreferenced media, so
         this is stated plainly rather than buried in the run output. -->
    <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl mb-5">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5 shrink-0"></i>
        <p class="text-xs text-amber-800 leading-relaxed">
            <span class="font-bold">This also deletes unreferenced files.</span>
            Any image in <code class="font-mono bg-amber-100 px-1 rounded">/images</code> or document in
            <code class="font-mono bg-amber-100 px-1 rounded">/docs</code> that no record points to is
            permanently removed. If a record's data is temporarily unreadable, the optimizer detects that
            and skips cleanup for the run rather than deleting everything — but it's still worth having a
            recent backup before running this.
        </p>
    </div>

    <!-- Slug-lock legend + live count -->
    <div class="flex flex-wrap gap-3 mb-4 text-xs">
        <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg font-semibold text-amber-700">
            <i class="fa-solid fa-lock text-amber-500 text-[11px]"></i>
            <span>Locked slug &rarr; skipped (slug, phone &amp; photo untouched)</span>
        </div>
        <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg font-semibold text-slate-600">
            <i class="fa-solid fa-lock-open text-slate-400 text-[11px]"></i>
            <span>Unlocked slug &rarr; normalized &amp; regenerated as needed</span>
        </div>
    </div>

    <div class="mb-5 flex items-center gap-3 text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
        <i class="fa-solid fa-shield-halved text-amber-400"></i>
        <span>
            <span class="font-bold text-slate-700" x-text="(data.team || []).filter(m => m.slug_locked).length"></span>
            of
            <span class="font-bold text-slate-700" x-text="(data.team || []).length"></span>
            team members have locked slugs
        </span>
        <a href="?tab=team" class="ml-auto text-indigo-600 hover:underline font-semibold flex items-center gap-1 whitespace-nowrap">
            Manage <i class="fa-solid fa-arrow-right text-[10px]"></i>
        </a>
    </div>

    <!-- Output console -->
    <div class="bg-[#0F172A] rounded-xl shadow-inner border border-slate-800 relative overflow-hidden <?= $optPanelCompact ? 'min-h-[160px]' : 'min-h-[280px]' ?>">
        <div class="bg-slate-950 px-5 py-3 border-b border-slate-800 flex items-center gap-3">
            <div class="flex gap-1.5">
                <div class="w-2.5 h-2.5 rounded-full bg-red-500/60"></div>
                <div class="w-2.5 h-2.5 rounded-full bg-yellow-400/60"></div>
                <div class="w-2.5 h-2.5 rounded-full bg-emerald-400/60"></div>
            </div>
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Optimizer Console</span>
            <button x-show="optLogs.length > 0" @click="optLogs = []"
                    class="ml-auto text-[10px] font-bold text-slate-500 hover:text-slate-300 transition uppercase tracking-widest">Clear</button>
        </div>

        <i class="fa-solid fa-server absolute -bottom-10 -right-10 text-[11rem] text-slate-800/20 pointer-events-none"></i>

        <div class="p-6 relative z-10">
            <div x-show="!optLoading && optLogs.length === 0"
                 class="flex flex-col items-center justify-center <?= $optPanelCompact ? 'py-6' : 'py-12' ?> gap-3 text-center">
                <i class="fa-solid fa-terminal text-3xl text-slate-700 mb-1"></i>
                <span class="text-slate-400 font-mono text-sm">Ready to execute.</span>
                <span class="text-slate-600 text-xs font-mono">Press "Run Optimizer" to begin.</span>
            </div>

            <div x-show="optLoading && optLogs.length === 0" x-cloak
                 class="flex items-center justify-center gap-3 <?= $optPanelCompact ? 'py-6' : 'py-12' ?> text-slate-400 font-mono text-sm">
                <i class="fa-solid fa-circle-notch fa-spin"></i> Running optimizer…
            </div>

            <div class="space-y-2.5 max-h-72 overflow-y-auto hide-scrollbar">
                <template x-for="(log, idx) in optLogs" :key="idx">
                    <div class="flex gap-3 items-start font-mono text-sm text-slate-300">
                        <span class="shrink-0 mt-0.5 w-4 text-center">
                            <i x-show="String(log).includes('\u2705')" class="fa-solid fa-circle-check text-emerald-400 text-xs"></i>
                            <i x-show="String(log).includes('\uD83D\uDDD1')" class="fa-solid fa-trash text-blue-400 text-xs"></i>
                            <i x-show="String(log).includes('\uD83E\uDDF9')" class="fa-solid fa-broom text-amber-400 text-xs"></i>
                            <i x-show="String(log).includes('\uD83D\uDCDE')" class="fa-solid fa-phone text-indigo-400 text-xs"></i>
                            <i x-show="String(log).includes('\uD83D\uDDBC')" class="fa-solid fa-image text-violet-400 text-xs"></i>
                            <i x-show="String(log).includes('\u26A0') || String(log).toLowerCase().includes('error')" class="fa-solid fa-circle-exclamation text-red-400 text-xs"></i>
                            <i x-show="String(log).includes('\u2139')" class="fa-solid fa-circle-info text-slate-500 text-xs"></i>
                        </span>
                        <span x-text="log" class="leading-relaxed tracking-wide break-words"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     CLEANUP SCANNER
     Two-phase by design: scan reports, nothing is deleted until the admin
     ticks items and confirms. The server re-validates every path against a
     fresh scan before unlinking, so a stale browser tab cannot delete a file
     that has since been attached to a record.
═══════════════════════════════════════════════════════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 <?= $optPanelCompact ? 'p-6' : 'p-6 md:p-8' ?> mt-6"
     x-data="cleanupScanner()">

    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-5">
        <div class="min-w-0">
            <div class="flex items-center gap-3 mb-1">
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center shadow-sm border border-emerald-100 shrink-0">
                    <i class="fa-solid fa-broom"></i>
                </div>
                <h2 class="<?= $optPanelCompact ? 'text-base' : 'text-xl' ?> font-black text-slate-800">Cleanup Scanner</h2>
            </div>
            <p class="text-slate-500 text-sm ml-[52px] max-w-lg">
                Finds files that are safe to remove — expired sessions, regenerable caches,
                old logs, temp artefacts and unreferenced media. Scanning deletes nothing.
            </p>
        </div>
        <button @click="scan()" :disabled="scanning"
                class="shrink-0 px-6 py-3 bg-slate-900 hover:bg-emerald-600 text-white font-bold rounded-xl shadow-lg transition-all flex items-center justify-center gap-3 min-w-[180px] disabled:opacity-70 disabled:cursor-not-allowed">
            <i class="fa-solid fa-magnifying-glass" x-show="!scanning"></i>
            <i class="fa-solid fa-circle-notch fa-spin" x-show="scanning" x-cloak></i>
            <span x-text="scanning ? 'Scanning…' : (result ? 'Rescan' : 'Scan Server')"></span>
        </button>
    </div>

    <div x-show="!result && !scanning" class="text-center py-10 text-slate-400 border-2 border-dashed border-slate-200 rounded-xl">
        <i class="fa-solid fa-broom text-3xl mb-3 opacity-40 block"></i>
        <p class="text-sm font-semibold text-slate-500">Run a scan to see what can be cleaned.</p>
    </div>

    <template x-if="result">
        <div>
            <!-- Summary -->
            <div class="flex flex-wrap items-center gap-3 mb-5 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                <i class="fa-solid fa-hard-drive text-slate-400"></i>
                <span>
                    <span class="font-bold text-slate-700" x-text="result.total_files"></span> file(s) found,
                    <span class="font-bold text-slate-700" x-text="result.total_human"></span> recoverable
                </span>
                <span class="ml-auto font-bold" :class="selectedCount ? 'text-emerald-600' : 'text-slate-400'">
                    <span x-text="selectedCount"></span> selected (<span x-text="selectedHuman"></span>)
                </span>
            </div>

            <template x-if="result.total_files === 0">
                <div class="text-center py-10 text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-xl">
                    <i class="fa-solid fa-circle-check text-3xl mb-2 block"></i>
                    <p class="text-sm font-bold">Nothing to clean — the server is tidy.</p>
                </div>
            </template>

            <!-- Groups -->
            <div class="space-y-4">
                <template x-for="g in result.groups" :key="g.key">
                    <div x-show="g.items.length > 0 || g.warning"
                         class="border rounded-xl overflow-hidden"
                         :class="g.safety === 'safe' ? 'border-slate-200' : 'border-amber-200'">

                        <div class="px-4 py-3 flex items-center gap-3"
                             :class="g.safety === 'safe' ? 'bg-slate-50' : 'bg-amber-50'">
                            <input type="checkbox" :id="'g_'+g.key"
                                   :checked="allChecked(g)" @change="toggleGroup(g, $event.target.checked)"
                                   class="w-4 h-4 rounded border-slate-300 cursor-pointer">
                            <label :for="'g_'+g.key" class="flex-1 min-w-0 cursor-pointer">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-slate-800 text-sm" x-text="g.label"></span>
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded border"
                                          :class="g.safety === 'safe'
                                              ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                                              : 'text-amber-700 bg-amber-100 border-amber-300'"
                                          x-text="g.safety === 'safe' ? 'Safe' : 'Review first'"></span>
                                    <span class="text-xs text-slate-400" x-text="g.items.length + ' file(s) · ' + human(groupBytes(g))"></span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5" x-text="g.desc"></p>
                            </label>
                            <button @click="open[g.key] = !open[g.key]"
                                    class="shrink-0 text-xs font-bold text-slate-500 hover:text-slate-800 px-2 py-1">
                                <span x-text="open[g.key] ? 'Hide' : 'Show'"></span>
                                <i class="fa-solid ml-1 text-[9px]" :class="open[g.key] ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            </button>
                        </div>

                        <div x-show="g.warning" class="px-4 py-3 bg-rose-50 border-t border-rose-200 text-xs text-rose-700 font-semibold" x-text="g.warning"></div>

                        <div x-show="open[g.key]" x-cloak class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                            <template x-for="it in g.items" :key="it.path">
                                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 cursor-pointer">
                                    <input type="checkbox" :value="it.path" x-model="selected"
                                           class="w-4 h-4 rounded border-slate-300 cursor-pointer">
                                    <span class="font-mono text-xs text-slate-700 truncate flex-1" x-text="it.name"></span>
                                    <span class="text-[11px] text-slate-400 truncate hidden sm:block" x-text="it.note"></span>
                                    <span class="text-[11px] text-slate-500 font-mono shrink-0" x-text="human(it.bytes)"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Delete action -->
            <div x-show="result.total_files > 0" class="mt-5 flex flex-col sm:flex-row items-center gap-3">
                <button @click="applyCleanup()" :disabled="!selectedCount || applying"
                        class="w-full sm:w-auto px-6 py-3 bg-rose-600 hover:bg-rose-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-bold rounded-xl shadow transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-trash" x-show="!applying"></i>
                    <i class="fa-solid fa-circle-notch fa-spin" x-show="applying" x-cloak></i>
                    <span x-text="applying ? 'Deleting…' : ('Delete ' + selectedCount + ' selected file(s)')"></span>
                </button>
                <p class="text-xs text-slate-500">Every path is re-checked server-side before deletion. Protected files are always refused.</p>
            </div>

            <!-- Result log -->
            <template x-if="applyLogs.length">
                <div class="mt-4 bg-slate-900 rounded-xl p-4 text-xs font-mono text-emerald-400 leading-relaxed max-h-48 overflow-y-auto border border-slate-700">
                    <template x-for="(l, i) in applyLogs" :key="i"><div x-text="l" class="mb-0.5"></div></template>
                </div>
            </template>
        </div>
    </template>
</div>

<script>
function cleanupScanner() {
    return {
        scanning: false, applying: false,
        result: null, selected: [], open: {}, applyLogs: [],

        human(b) {
            if (!b) return '0 B';
            const u = ['B','KB','MB','GB']; let i = 0; b = Number(b);
            while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
            return (Math.round(b * 10) / 10) + ' ' + u[i];
        },
        groupBytes(g) { return g.items.reduce((s, i) => s + (i.bytes || 0), 0); },
        get selectedCount() { return this.selected.length; },
        get selectedHuman() {
            if (!this.result) return '0 B';
            let t = 0;
            for (const g of this.result.groups)
                for (const i of g.items)
                    if (this.selected.includes(i.path)) t += (i.bytes || 0);
            return this.human(t);
        },
        allChecked(g) {
            return g.items.length > 0 && g.items.every(i => this.selected.includes(i.path));
        },
        toggleGroup(g, on) {
            const paths = g.items.map(i => i.path);
            this.selected = on
                ? [...new Set([...this.selected, ...paths])]
                : this.selected.filter(p => !paths.includes(p));
        },

        async scan() {
            this.scanning = true; this.applyLogs = []; this.selected = [];
            try {
                const r = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                    body: JSON.stringify({ action: 'cleanup_scan' })
                });
                const d = await r.json();
                if (d.status !== 'success') throw new Error(d.message || 'Scan failed');
                this.result = d;
                // Pre-tick only the groups classed 'safe'. Anything that could
                // mean real data loss stays unticked until the admin looks.
                for (const g of d.groups) {
                    this.open[g.key] = false;
                    if (g.safety === 'safe') this.toggleGroup(g, true);
                }
            } catch (e) {
                alert('Scan failed: ' + e.message);
            }
            this.scanning = false;
        },

        async applyCleanup() {
            if (!this.selected.length) return;
            if (!confirm(
                'Permanently delete ' + this.selected.length + ' file(s) (' + this.selectedHuman + ')?\n\n' +
                'This cannot be undone. Files still in use are refused automatically, ' +
                'but please make sure you have a recent backup.'
            )) return;

            this.applying = true;
            try {
                const r = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                    body: JSON.stringify({ action: 'cleanup_apply', paths: this.selected })
                });
                const d = await r.json();
                this.applyLogs = d.logs || ['Done.'];
                await this.scan();          // refresh so the list reflects reality
            } catch (e) {
                this.applyLogs = ['⚠️ Error: ' + e.message];
            }
            this.applying = false;
        }
    };
}
</script>
