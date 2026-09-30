<?php
// app/views/tabs/tab_leads.php — Version: 260916.14
//
// Views submissions from the card's optional "Share your contact" form
// (cards/business.php, gated per-person by team.capture_leads).
if (!defined('BASE_PATH')) exit;

$leads = class_exists('AppDB') ? (AppDB::read('leads') ?: []) : [];

// ── Card view analytics ─────────────────────────────────────────────────
// Read directly rather than through AppDB — this is a simple counter file
// (index.php increments it on every card render), not a record collection,
// so it does not fit AppDB's array-of-records model and does not need it.
$viewsFile = DATA_PATH . '/card_views.json';
$viewsData = is_file($viewsFile) ? (json_decode((string)@file_get_contents($viewsFile), true) ?: []) : [];

// Aggregate per slug across card types (business/visiting/qr/id), and
// resolve to a name — a raw slug means nothing on a leaderboard.
$viewsBySlug = [];
foreach ($viewsData as $_key => $_v) {
    [$_slug] = explode(':', $_key, 2);
    $viewsBySlug[$_slug] = ($viewsBySlug[$_slug] ?? 0) + (int)($_v['total'] ?? 0);
}
$totalViews = array_sum($viewsBySlug);

$_nameBySlug = [];
foreach (AppDB::read('team') ?: [] as $_m) {
    if (!empty($_m['slug'])) $_nameBySlug[strtolower((string)$_m['slug'])] = (string)($_m['name'] ?? '');
}
arsort($viewsBySlug);
$topViewed = [];
foreach (array_slice($viewsBySlug, 0, 8, true) as $_slug => $_n) {
    $topViewed[] = ['name' => $_nameBySlug[strtolower($_slug)] ?? $_slug, 'slug' => $_slug, 'views' => $_n];
}

// Resolve each lead's card owner name, so "who was this person trying to
// reach" is visible without cross-referencing the directory by hand.
$teamById = [];
foreach (AppDB::read('team') ?: [] as $_m) {
    if (!empty($_m['id'])) $teamById[(string)$_m['id']] = (string)($_m['name'] ?? '');
}
foreach ($leads as &$_l) {
    $_l['_owner'] = $teamById[(string)($_l['card_owner_id'] ?? '')] ?? '';
}
unset($_l);

// Newest first.
usort($leads, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));

$totalLeads = count($leads);
$newLeads   = count(array_filter($leads, fn($l) => ($l['status'] ?? 'new') === 'new'));
$withEmail  = count(array_filter($leads, fn($l) => trim((string)($l['email'] ?? '')) !== ''));
?>

<div class="w-full flex flex-col space-y-4" x-data="leadsTab()">

    <?php if ($totalViews > 0): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                <i class="fa-solid fa-chart-simple mr-1.5 text-slate-400"></i>Card Views
            </h3>
            <span class="text-[11px] font-bold text-slate-500"><?= number_format($totalViews) ?> total &middot; all cards, all time</span>
        </div>
        <div class="p-5">
            <?php $_max = max(1, $topViewed[0]['views'] ?? 1); ?>
            <div class="space-y-2.5">
                <?php foreach ($topViewed as $_row): ?>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-slate-600 w-32 truncate shrink-0"><?= htmlspecialchars($_row['name']) ?></span>
                    <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full" style="width:<?= (int)round($_row['views'] / $_max * 100) ?>%"></div>
                    </div>
                    <span class="text-xs font-bold text-slate-700 w-10 text-right shrink-0"><?= number_format($_row['views']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Leads</div>
            <div class="text-2xl font-black text-slate-800"><?= (int)$totalLeads ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">New</div>
            <div class="text-2xl font-black text-blue-600"><?= (int)$newLeads ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">With Email</div>
            <div class="text-2xl font-black text-slate-800"><?= (int)$withEmail ?></div>
        </div>
    </div>

    <template x-if="leads.length === 0">
        <div class="flex flex-col items-center justify-center h-64 text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl w-full bg-slate-50/50">
            <i class="fa-solid fa-address-card text-4xl mb-4 text-slate-300"></i>
            <p class="text-sm font-semibold text-slate-600">No leads captured yet.</p>
            <p class="text-xs text-slate-400 mt-1 text-center max-w-sm">
                Enable "Collect contact details" on a team member's card in the Directory tab
                to start receiving submissions here.
            </p>
        </div>
    </template>

    <template x-if="leads.length > 0">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left" style="min-width:760px">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase text-slate-500 tracking-wider">
                        <tr>
                            <th class="px-4 py-3 font-bold w-32">Date</th>
                            <th class="px-4 py-3 font-bold">Contact</th>
                            <th class="px-4 py-3 font-bold w-36">Company</th>
                            <th class="px-4 py-3 font-bold w-36">Card Owner</th>
                            <th class="px-4 py-3 font-bold w-28">Status</th>
                            <th class="px-4 py-3 font-bold w-20 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <template x-for="l in leads" :key="l.id">
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-4 py-3 text-xs text-slate-500" x-text="fmtDate(l.created_at)"></td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-700" x-text="l.name"></div>
                                    <div class="text-[11px] text-slate-400" x-text="[l.email, l.phone].filter(Boolean).join(' · ')"></div>
                                    <div class="text-[11px] text-slate-400 mt-0.5" x-show="l.note" x-text="l.note"></div>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600" x-text="l.company || '—'"></td>
                                <td class="px-4 py-3 text-xs text-slate-600" x-text="l._owner || '—'"></td>
                                <td class="px-4 py-3">
                                    <select :value="l.status" @change="setStatus(l, $event.target.value)"
                                            class="text-[11px] font-bold rounded-lg border px-2 py-1 outline-none"
                                            :class="{
                                                'bg-blue-50 border-blue-200 text-blue-700': l.status === 'new',
                                                'bg-amber-50 border-amber-200 text-amber-700': l.status === 'contacted',
                                                'bg-emerald-50 border-emerald-200 text-emerald-700': l.status === 'qualified',
                                                'bg-slate-100 border-slate-200 text-slate-500': l.status === 'closed'
                                            }">
                                        <option value="new">New</option>
                                        <option value="contacted">Contacted</option>
                                        <option value="qualified">Qualified</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="remove(l)" class="w-7 h-7 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 transition" title="Delete">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <?php if ($totalLeads > 0): ?>
    <div class="flex justify-end">
        <a href="tools/export_leads_csv.php" class="px-4 py-2 bg-white hover:bg-slate-100 border border-slate-300 text-slate-700 text-xs font-bold rounded-lg transition flex items-center gap-2">
            <i class="fa-solid fa-file-csv text-[10px]"></i> Export CSV
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
function leadsTab() {
    return {
        leads: <?= json_encode(array_values($leads), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,

        fmtDate(iso) {
            const d = new Date(iso);
            if (isNaN(d)) return iso || '—';
            return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
                 + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
        },

        async setStatus(lead, status) {
            const prev = lead.status;
            lead.status = status;   // optimistic
            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                body: JSON.stringify({ action: 'save', ns: 'leads', id: lead.id, __edit_mode: true, payload: { status } })
            }).then(r => r.json()).catch(() => ({ status: 'error' }));
            if (res.status !== 'success') { lead.status = prev; alert('Could not update status.'); }
        },

        async remove(lead) {
            if (!confirm('Delete this lead?')) return;
            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                body: JSON.stringify({ action: 'delete', ns: 'leads', id: lead.id })
            }).then(r => r.json()).catch(() => ({ status: 'error' }));
            if (res.status === 'success') this.leads = this.leads.filter(l => l.id !== lead.id);
            else alert('Delete failed.');
        }
    };
}
</script>
