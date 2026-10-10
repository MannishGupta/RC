<?php
// Version: 1.1
// Human Capital Index — rank-first sort, header chips (no duplicate Sort toolbar), selection-driven Edit/Delete
if (!defined('BASE_PATH')) exit;
$isAdminTeam = !empty($isAdmin);
?>
<script>
window.openTeamEditor = function(isEdit, item) {
  if (typeof window.openModalEditor === 'function') {
    window.openModalEditor(!!isEdit, 'team', item || null);
    return;
  }
  try {
    window.dispatchEvent(new CustomEvent('open-editor', {
      detail: { isEdit: !!isEdit, type: 'team', item: item || null }
    }));
  } catch (e) {
    alert('Editor failed to open. Hard-refresh (Ctrl+Shift+R) and try again.');
  }
};

window.__teamApi = {
  get() {
    try {
      const el = document.querySelector('[x-data="teamDirectory"]');
      if (el && window.Alpine) return Alpine.$data(el);
    } catch (e) {}
    return null;
  },
  toggleSort(mode) { const t = this.get(); if (t) t.toggleSort(mode); },
  share() { const t = this.get(); if (t) t.shareSelected(); },
  card(type) { const t = this.get(); if (t) t.cardUrl(type); },
  edit() { const t = this.get(); if (t) t.editSelected(); },
  del() { const t = this.get(); if (t) t.deleteSelected(); },
  add() { window.openTeamEditor(false, null); },
  selectedName() {
    const t = this.get();
    return (t && t.selectedUser && t.selectedUser.name) ? t.selectedUser.name : '';
  },
  hasSelection() {
    const t = this.get();
    return !!(t && t.selectedUser);
  }
};

function __syncTeamActionButtons() {
  const on = window.__teamApi && window.__teamApi.hasSelection();
  document.querySelectorAll('[data-team-act]').forEach(function (btn) {
    btn.disabled = !on;
    if (on) {
      btn.classList.remove('opacity-40', 'cursor-not-allowed', 'text-slate-400', 'bg-slate-50', 'border-slate-200');
      if (btn.getAttribute('data-team-act') === 'edit') {
        btn.classList.add('text-blue-700', 'bg-blue-50', 'border-blue-100');
      } else {
        btn.classList.add('text-rose-700', 'bg-rose-50', 'border-rose-100');
      }
    } else {
      btn.classList.add('opacity-40', 'cursor-not-allowed', 'text-slate-400', 'bg-slate-50', 'border-slate-200');
      btn.classList.remove('text-blue-700', 'bg-blue-50', 'border-blue-100', 'text-rose-700', 'bg-rose-50', 'border-rose-100');
    }
  });
}
</script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('teamDirectory', () => ({
        selectedUser: null,
        favIds: [],
        sortMode: 'rank',
        gotraFilter: '',
        gotraOptions: [],
        sortDir: 'asc',
        rankMap: {},

        async importTeamCsv(ev) {
            const input = ev && ev.target;
            const file = input && input.files && input.files[0];
            if (!file) return;
            try {
                const fd = new FormData();
                fd.append('action', 'team_import');
                fd.append('file', file);
                const csrf = (window.APP && window.APP.csrf) || window.CSRF_TOKEN || '';
                if (csrf) fd.append('csrf_token', csrf);
                const r = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                    body: fd,
                    credentials: 'same-origin'
                });
                const j = await r.json().catch(() => ({}));
                if (j.status === 'success') {
                    alert('Import complete: ' + (j.imported || 0) + ' added, ' + (j.skipped || 0) + ' skipped.');
                    location.reload();
                } else {
                    alert(j.message || 'Import failed.');
                }
            } catch (e) {
                alert('Import failed: ' + (e.message || e));
            } finally {
                if (input) input.value = '';
            }
        },

        favKey() {
            const t = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.auth && window.__DASHBOARD_STATE__.auth.tenantId)
                || (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.tenantId)
                || 'default';
            return 'rc_favs_' + t;
        },
        loadFavs() {
            try {
                const raw = localStorage.getItem(this.favKey());
                this.favIds = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(this.favIds)) this.favIds = [];
            } catch (e) { this.favIds = []; }
        },
        isFav(u) {
            if (!u) return false;
            const id = String(u.slug || u.id || '');
            return id && this.favIds.indexOf(id) >= 0;
        },
        toggleFav(u, ev) {
            if (ev) { ev.preventDefault(); ev.stopPropagation(); }
            if (!u) return;
            const id = String(u.slug || u.id || '');
            if (!id) return;
            const i = this.favIds.indexOf(id);
            if (i >= 0) this.favIds.splice(i, 1);
            else this.favIds.unshift(id);
            this.favIds = this.favIds.slice(0, 40);
            try { localStorage.setItem(this.favKey(), JSON.stringify(this.favIds)); } catch (e) {}
        },
        init() {
            this.loadFavs();
            this.rebuildMaps();
            const d = this.dash();
            if (d) {
                d.page = 1;
                d.cur = 'team';
                if (!d.sortCol || d.sortCol === 'name') {
                    d.sortCol = 'rank';
                    d.sortAsc = true;
                }
                const map = { rank: 'rank', name: 'name', location: 'location', department: 'department', designation: 'name' };
                this.sortMode = map[d.sortCol] || 'rank';
                this.sortDir = d.sortAsc === false ? 'desc' : 'asc';
                d.teamSortMode = this.sortMode;
                d.teamSortDir = this.sortDir;
            }
            this.$watch(() => this.designationRows().length, () => this.rebuildMaps());
            this.$watch('selectedUser', () => { queueMicrotask(__syncTeamActionButtons); });
            queueMicrotask(__syncTeamActionButtons);
        },

        dash() {
            try {
                const el = document.querySelector('[x-data="dashboardApp"]');
                if (el && window.Alpine) return Alpine.$data(el);
            } catch (e) {}
            return null;
        },

        stateData() {
            const d = this.dash();
            if (d && d.data) return d.data;
            return (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.data) || {};
        },

        /** True for empty labels or unresolved master keys (desig_2, dept_1, loc_hq, …). */
        isMasterCode(v) {
            const s = String(v == null ? '' : v).trim();
            if (s === '' || s === '-' || s === '—') return true;
            return /^(desig|dept|loc|location|designation|department)[_-]?\w*$/i.test(s);
        },
        isPendingField(v) {
            const s = String(v == null ? '' : v).trim();
            if (this.isMasterCode(s)) return true;
            return /pending|unassigned/i.test(s);
        },
        labelDesig(i) {
            const s = String((i && (i.designation_name || i.designation)) || '').trim();
            if (this.isMasterCode(s)) return 'Designation Pending';
            return s || 'Designation Pending';
        },
        labelDept(i) {
            const s = String((i && (i.department_name || i.department)) || '').trim();
            if (this.isMasterCode(s)) return 'Department Pending';
            return s || 'Department Pending';
        },
        labelLoc(i) {
            const s = String((i && (i.location_name || i.location)) || '').trim();
            if (this.isMasterCode(s)) return 'Location Unassigned';
            return s || 'Location Unassigned';
        },
        formPending(person, field) {
            try {
                const d = this.dash();
                if (d && typeof d.openEditor === 'function') {
                    d.openEditor('team', person);
                    return;
                }
                if (d && typeof d.editRecord === 'function') {
                    d.editRecord('team', person);
                    return;
                }
            } catch (e) {}
        },

        designationRows() {
            const rows = this.stateData().designations;
            return Array.isArray(rows) ? rows : [];
        },

        desigHierarchyRank(d, fallbackIndex) {
            if (!d) return fallbackIndex + 1;
            const r = Number(d.rank);
            if (!isNaN(r) && r > 0 && r < 900) return r;
            const code = String(d.code || '').trim();
            if (/^\d+$/.test(code)) return Number(code);
            const h = Number(d.hierarchy_rank);
            if (!isNaN(h) && h > 0 && h < 900) return h;
            return fallbackIndex + 1;
        },

        rebuildMaps() {
            const rows = this.designationRows().slice();
            rows.sort((a, b) => {
                const ra = this.desigHierarchyRank(a, 0);
                const rb = this.desigHierarchyRank(b, 0);
                if (ra !== rb) return ra - rb;
                return String(a.code || a.name || '').localeCompare(String(b.code || b.name || ''), 'en-IN', { sensitivity: 'base' });
            });
            const map = {};
            rows.forEach((d, idx) => {
                const rank = this.desigHierarchyRank(d, idx);
                [d.id, d.code, d.slug, d.name].forEach(k => {
                    if (k !== undefined && k !== null && String(k).trim() !== '') map[String(k).trim().toLowerCase()] = rank;
                });
            });
            this.rankMap = map;
            const gset = new Set();
            (raw || []).forEach(p => {
                const g = (p.gotra || '').trim();
                if (g) gset.add(g);
            });
            this.gotraOptions = Array.from(gset).sort((a,b)=>a.localeCompare(b));
        },

        getRank(person) {
            if (!person) return 9999;
            const pr = Number(person.rank);
            // 999 / 9999 are legacy placeholders — prefer designation rank
            if (!isNaN(pr) && pr > 0 && pr < 900) return pr;
            for (const k of [person.designation_id, person.designation_code, person.designation, person.labels, person.designation_name]) {
                if (k === undefined || k === null || String(k).trim() === '') continue;
                const r = this.rankMap[String(k).trim().toLowerCase()];
                if (r !== undefined && Number(r) > 0 && Number(r) < 900) return Number(r);
            }
            const h = Number(person.hierarchy_rank);
            if (!isNaN(h) && h > 0 && h < 900) return h;
            return 9999;
        },

        get sourceList() {
            var list = [];
            try {
                var d = this.dash();
                var raw = null;
                if (d && d.data && d.data.team != null) raw = d.data.team;
                if (raw == null && window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.data)
                    raw = window.__DASHBOARD_STATE__.data.team;
                if (Array.isArray(raw)) list = raw.slice();
                else if (raw && typeof raw === 'object') {
                    list = Object.keys(raw).map(function(k){ return raw[k]; });
                }
                list = list.filter(function(row){
                    return row && typeof row === 'object';
                });
                if (d && d.search && String(d.search).trim() !== '') {
                    var s = String(d.search).toLowerCase();
                    list = list.filter(function(i){
                        try {
                            return JSON.stringify(i).toLowerCase().indexOf(s) >= 0;
                        } catch (e) { return true; }
                    });
                }
                if (this.gotraFilter) {
                    var g = String(this.gotraFilter).toLowerCase();
                    list = list.filter(function(i){ return String(i.gotra || '').toLowerCase() === g; });
                }
            } catch (e) { console.error('sourceList', e); }
            return list;
        },

        get totalCount() {
            return (this.sourceList || []).length;
        },

        toggleSort(mode) {
            if (this.sortMode === mode) this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            else { this.sortMode = mode; this.sortDir = 'asc'; }
            const d = this.dash();
            if (d) {
                d.teamSortMode = this.sortMode;
                d.teamSortDir = this.sortDir;
                d.sortCol = this.sortMode === 'location' ? 'location' : (this.sortMode === 'department' ? 'department' : this.sortMode);
                d.sortAsc = this.sortDir === 'asc';
                d.page = 1;
            }
        },

        caret(mode) {
            if (this.sortMode !== mode) return '';
            return this.sortDir === 'asc' ? ' ▲' : ' ▼';
        },

        // BUG FIX: DOB was displayed raw as i.dob, which is always
        formatDob(v) {
            if (!v) return '';
            const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v));
            return m ? (m[3] + '-' + m[2] + '-' + m[1]) : String(v);
        },

        get sortedUsers() {
            const list = (this.sourceList || []).slice();
            const dir = this.sortDir === 'desc' ? -1 : 1;
            const locOf = (u) => String(u.location_name || u.location || '').toLowerCase();
            const deptOf = (u) => String(u.department_name || u.department || '').toLowerCase();
            list.sort((a, b) => {
                const af = this.isFav(a) ? 0 : 1;
                const bf = this.isFav(b) ? 0 : 1;
                if (af !== bf) return af - bf;
                let cmp = 0;
                if (this.sortMode === 'name') {
                    cmp = String(a.name || '').localeCompare(String(b.name || ''), 'en-IN', { sensitivity: 'base' });
                } else if (this.sortMode === 'location') {
                    cmp = locOf(a).localeCompare(locOf(b), 'en-IN', { sensitivity: 'base' });
                } else if (this.sortMode === 'department') {
                    cmp = deptOf(a).localeCompare(deptOf(b), 'en-IN', { sensitivity: 'base' });
                } else {
                    // rank: null/0 last, then rank ASC, then name
                    const ra = this.getRank(a);
                    const rb = this.getRank(b);
                    const aNull = (ra >= 9999) ? 1 : 0;
                    const bNull = (rb >= 9999) ? 1 : 0;
                    if (aNull !== bNull) cmp = aNull - bNull;
                    else {
                        cmp = ra - rb;
                        if (cmp === 0) cmp = String(a.name || '').localeCompare(String(b.name || ''), 'en-IN', { sensitivity: 'base' });
                    }
                }
                return cmp * dir;
            });
            const d = this.dash();
            if (d && typeof d.page === 'number' && typeof d.perPage === 'number' && d.perPage > 0) {
                const pp = Math.max(1, d.perPage | 0);
                const maxPage = Math.max(1, Math.ceil(list.length / pp));
                let page = Math.max(1, d.page | 0);
                if (page > maxPage) { page = maxPage; d.page = maxPage; }
                const start = (page - 1) * pp;
                return list.slice(start, start + pp);
            }
            return list;
        },

        selectUser(u) {
            this.selectedUser = (this.selectedUser && ((this.selectedUser.id && this.selectedUser.id === u.id) || (this.selectedUser.slug && this.selectedUser.slug === u.slug))) ? null : u;
            queueMicrotask(__syncTeamActionButtons);
        },
        isSelected(u) {
            if (!this.selectedUser || !u) return false;
            return (this.selectedUser.id && u.id && this.selectedUser.id === u.id) || (this.selectedUser.slug && u.slug && this.selectedUser.slug === u.slug);
        },
        requireSelection() {
            if (!this.selectedUser) { alert('Select a contact first (click a card).'); return null; }
            return this.selectedUser;
        },
        cardUrl(type) {
            const u = this.requireSelection();
            if (!u) return;
            const slug = encodeURIComponent(u.slug || u.id || '');
            if (!slug) { alert('This contact has no slug.'); return; }
            let url;
            if (type === 'signature') url = window.location.origin + '/cards/signature.php?slug=' + slug;
            else if (type === 'janam') {
                const params = new URLSearchParams();
                const slug = (u.slug || u.id || '');
                if (slug) params.set('slug', slug);
                if (u.name) params.set('name', u.name);
                if (u.dob) params.set('dob', u.dob);
                const tob = u.time_of_birth || u.tob || u.birth_time || '';
                if (tob) params.set('tob', tob);
                const g = (u.gender || '').toLowerCase();
                if (g === 'male' || g === 'female') params.set('gender', g);
                const gotra = u.gotra || '';
                if (gotra) params.set('gotra', gotra);
                const pob = u.place_of_birth || u.birth_place || u.pob || '';
                if (pob) params.set('place', pob);
                const qs = params.toString();
                url = window.location.origin + '/janam_patri.php' + (qs ? ('?' + qs) : '');
            }
            else if (type === 'blood') {
                const params = new URLSearchParams();
                const slug = (u.slug || u.id || '');
                if (slug) params.set('slug', slug);
                if (u.name) params.set('name', u.name);
                const bg = u.blood_group || u.blood || '';
                if (bg) params.set('group', bg);
                const qs = params.toString();
                url = window.location.origin + '/blood_report.php' + (qs ? ('?' + qs) : '');
            }
            else url = window.location.origin + '/?card=' + type + '&slug=' + slug;
            window.open(url, '_blank', 'noopener');
        },
        shareSelected() {
            const u = this.requireSelection();
            if (!u) return;
            window.dispatchEvent(new CustomEvent('share-record', { detail: { type: 'team', item: u } }));
        },
        /** Email the signature setup page link to the contact (or blank To:). */
        shareSignatureEmail() {
            const u = this.requireSelection();
            if (!u) return;
            const slug = String(u.slug || u.id || '').trim();
            if (!slug) {
                alert('This contact needs a slug/id before sharing a signature link.');
                return;
            }
            const url = window.location.origin + '/cards/signature.php?slug=' + encodeURIComponent(slug);
            const name = String(u.name || 'team member').trim();
            const to = String(u.email || '').trim();
            const subj = encodeURIComponent('Your email signature — ' + name);
            const body = encodeURIComponent(
                'Hi' + (name ? (' ' + name.split(' ')[0]) : '') + ',\n\n' +
                'Here is your email signature setup page:\n' + url + '\n\n' +
                '1. Open the link\n' +
                '2. Click “Copy active signature”\n' +
                '3. Paste into Gmail, Outlook, or Apple Mail\n' +
                'Install steps for each mail app are on the page.\n'
            );
            window.location.href = 'mailto:' + encodeURIComponent(to) + '?subject=' + subj + '&body=' + body;
        },
        editSelected() {
            const u = this.requireSelection();
            if (!u) return;
            window.openTeamEditor(true, u);
        },
        deleteSelected() {
            const u = this.requireSelection();
            if (!u) return;
            if (!confirm('Delete ' + (u.name || 'this contact') + ' permanently?')) return;
            window.dispatchEvent(new CustomEvent('delete-record', { detail: { id: u.id, ns: 'team' } }));
        }
    }));
});
</script>

<div class="w-full space-y-2" x-data="teamDirectory">

    <div x-show="totalCount === 0" x-cloak class="rc-card rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
        <i class="fa-solid fa-users text-3xl text-slate-300 mb-3" aria-hidden="true"></i>
        <p class="text-sm font-bold text-slate-700">No team members yet</p>
        <p class="text-xs text-slate-500 mt-1">Add your first person, or use <span class="font-semibold">Import CSV</span>.</p>
    </div>

    <!-- Primary list chrome: tools only (title+count live in main header — no duplicate) -->
    <div class="rc-team-chrome sticky top-0 z-20 -mx-4 md:-mx-6 px-4 md:px-6 py-1.5 border-b border-slate-200 flex flex-nowrap items-center gap-1.5 overflow-x-auto" style="background:var(--rc-paper);border-color:var(--rc-border)">
        <div class="flex flex-nowrap items-center gap-1 shrink-0">
            <a href="/tools/export_team_csv.php" class="rc-team-chip inline-flex items-center gap-1 h-8 px-2.5 rounded-lg border text-[11px] font-bold whitespace-nowrap" title="Export team CSV">
              <i class="fa-solid fa-file-csv text-xs" aria-hidden="true"></i> Export CSV
            </a>
            <?php if (!empty($isAdmin)): ?>
            <label class="rc-team-chip inline-flex items-center gap-1 h-8 px-2.5 rounded-lg border text-[11px] font-bold cursor-pointer whitespace-nowrap" title="Import team CSV">
              <i class="fa-solid fa-file-import text-xs" aria-hidden="true"></i> Import CSV
              <input type="file" accept=".csv,text/csv" class="hidden" @change="importTeamCsv($event)">
            </label>
            <?php endif; ?>
        </div>
        <span class="w-px h-5 shrink-0 opacity-40" style="background:var(--rc-border)" aria-hidden="true"></span>
        <div class="flex flex-nowrap items-center gap-1 shrink-0" role="group" aria-label="Sort by">
            <button type="button" @click="toggleSort('rank')"
                    class="rc-team-chip h-8 px-2.5 rounded-md text-[11px] font-bold border transition whitespace-nowrap"
                    :class="sortMode==='rank' ? 'rc-team-chip--on' : ''">
                Rank<span x-text="caret('rank')"></span>
            </button>
            <button type="button" @click="toggleSort('name')"
                    class="rc-team-chip h-8 px-2.5 rounded-md text-[11px] font-bold border transition whitespace-nowrap"
                    :class="sortMode==='name' ? 'rc-team-chip--on' : ''">
                Name<span x-text="caret('name')"></span>
            </button>
            <button type="button" @click="toggleSort('location')"
                    class="rc-team-chip h-8 px-2.5 rounded-md text-[11px] font-bold border transition whitespace-nowrap"
                    :class="sortMode==='location' ? 'rc-team-chip--on' : ''">
                Loc<span x-text="caret('location')"></span>
            </button>
            <button type="button" @click="toggleSort('department')"
                    class="rc-team-chip h-8 px-2.5 rounded-md text-[11px] font-bold border transition whitespace-nowrap"
                    :class="sortMode==='department' ? 'rc-team-chip--on' : ''">
                Dept<span x-text="caret('department')"></span>
            </button>
        </div>
        <span class="w-px h-5 bg-slate-200 hidden sm:block"></span>
        <div class="flex flex-nowrap items-center gap-1 shrink-0" role="group" aria-label="Contact tools">
            <!-- Share -->
            <button type="button" @click="shareSelected()" class="rc-team-chip rc-team-chip--wa rc-hit-lg h-8 px-2.5 rounded-md text-[11px] font-bold border" data-rc-tooltip="Share selected contact via WhatsApp" aria-label="Share via WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></button>
            <span class="w-px h-5 bg-slate-200 mx-0.5 hidden sm:inline-block" aria-hidden="true"></span>
            <!-- Digital cards (person identity) -->
            <button type="button" @click="cardUrl('business')" class="rc-team-chip rc-hit-lg h-8 w-8 rounded-md border" data-rc-tooltip="Digital business card" aria-label="Digital business card"><i class="fa-solid fa-id-badge text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('visiting')" class="rc-team-chip rc-hit-lg h-8 w-8 rounded-md border" data-rc-tooltip="Visiting card" aria-label="Visiting card"><i class="fa-solid fa-address-card text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('id')" class="rc-team-chip rc-hit-lg h-8 w-8 rounded-md border" data-rc-tooltip="Employee ID card" aria-label="Employee ID card"><i class="fa-solid fa-id-card text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('qr')" class="rc-team-chip rc-hit-lg h-8 w-8 rounded-md border" data-rc-tooltip="QR contact card" aria-label="QR contact card"><i class="fa-solid fa-qrcode text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('signature')" class="rc-team-chip rc-hit-lg h-8 w-8 rounded-md border" data-rc-tooltip="Open email signature" aria-label="Open email signature"><i class="fa-solid fa-signature text-xs" aria-hidden="true"></i></button>
            <span class="w-px h-5 bg-slate-200 mx-0.5 hidden sm:inline-block" aria-hidden="true"></span>
            <!-- Reports (selection-based) -->
            <button type="button" @click="cardUrl('numero')" class="rc-team-chip rc-hit-lg h-8 w-8 rounded-md border" data-rc-tooltip="Numerology report" aria-label="Numerology report"><i class="fa-solid fa-hashtag text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('janam')" class="rc-team-chip rc-hit-lg h-8 w-8 rounded-md border" data-rc-tooltip="Janam Patri / Kundli Milan" aria-label="Janam Patri"><i class="fa-solid fa-om text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('blood')" class="rc-team-chip rc-hit-lg h-8 w-8 rounded-md border" data-rc-tooltip="Blood group report" aria-label="Blood group report"><i class="fa-solid fa-droplet text-xs" aria-hidden="true"></i></button>
        </div>
    </div>

    <template x-if="totalCount > 0 && sortedUsers.length === 0">
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Page is empty — <button type="button" class="font-bold underline" @click="const d=dash(); if(d) d.page=1">go to page 1</button>
            or clear search.
        </div>
    </template>
    <template x-if="totalCount === 0">
        <div class="flex flex-col items-center justify-center h-48 text-slate-400 border-2 border-dashed border-slate-200 rounded-xl bg-white/50">
            <i class="fa-solid fa-users text-3xl mb-3 opacity-40" aria-hidden="true"></i>
            <p class="text-sm font-semibold">No people match this view.</p>
        </div>
    </template>

    <style>
      .rc-vcard {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        cursor: pointer;
        transition: box-shadow .15s, border-color .15s;
        display: flex;
        flex-direction: column;
        min-height: 0;
      }
      .rc-vcard:hover { border-color: #cbd5e1; box-shadow: 0 8px 24px rgba(15,23,42,.08); }
      .rc-vcard.is-selected {
        border-color: #2563eb !important;
        box-shadow: 0 0 0 3px rgba(37,99,235,.45), 0 8px 24px rgba(37,99,235,.18) !important;
        background: linear-gradient(180deg, #eff6ff 0%, #fff 48%) !important;
        transform: translateY(-1px);
        z-index: 2;
      }
      .rc-vcard.is-selected .rc-vcard-accent {
        width: 6px !important;
        background: #2563eb !important;
      }
      .rc-vcard.is-selected .rc-vcard-name { color: #1e3a8a !important; }
      html[data-theme="dark"] .rc-vcard.is-selected,
      [data-theme="dark"] .rc-vcard.is-selected {
        background: linear-gradient(180deg, #1e3a5f 0%, #0f172a 55%) !important;
        border-color: #60a5fa !important;
        box-shadow: 0 0 0 3px rgba(96,165,250,.35), 0 8px 24px rgba(0,0,0,.35) !important;
      }
      html[data-theme="dark"] .rc-vcard.is-selected .rc-vcard-name,
      [data-theme="dark"] .rc-vcard.is-selected .rc-vcard-name { color: #dbeafe !important; }
      html[data-theme="reserve"] .rc-vcard.is-selected,
      [data-theme="reserve"] .rc-vcard.is-selected {
        background: linear-gradient(180deg, #f5efe6 0%, #faf8f5 50%) !important;
        border-color: #0078d4 !important;
      }
      .rc-vcard-accent { height: 4px; width: 100%; }
      .rc-vcard-body { position: relative; padding: 10px 12px 10px; display: flex; gap: 10px; flex: 1; }
      .rc-vcard-photo {
        width: 72px; height: 72px; border-radius: 12px; object-fit: cover;
        background: #f1f5f9; border: 1px solid #e2e8f0; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.25rem; color: #64748b;
      }
      .rc-vcard-photo img { width: 100%; height: 100%; object-fit: cover; border-radius: 11px; }
      .rc-vcard-name { font-size: 0.95rem; font-weight: 800; color: #0f172a; line-height: 1.25; margin: 0 0 2px; }
      html[data-theme="dark"] .rc-vcard-name, [data-theme="dark"] .rc-vcard-name { color: #f8fafc !important; }
      html[data-theme="dark"] .rc-vcard-role, [data-theme="dark"] .rc-vcard-role { color: #93c5fd !important; }
      html[data-theme="dark"] .rc-vcard-line, [data-theme="dark"] .rc-vcard-line { color: #cbd5e1 !important; }
      .rc-vcard-role { font-size: 0.75rem; font-weight: 600; color: #1d4ed8; margin: 0 0 2px; }
      .rc-vcard-line { font-size: 0.7rem; color: #475569; margin: 0; display: flex; gap: 6px; align-items: flex-start; line-height: 1.3; }
      .rc-vcard-line i { width: 12px; color: #94a3b8; margin-top: 2px; flex-shrink: 0; }
      .rc-vcard-line,
      .rc-vcard-line button,
      .rc-vcard-line span,
      .rc-vcard-role,
      .rc-vcard-name {
        min-height: 0 !important;
        height: auto !important;
        line-height: 1.25 !important;
      }
      .rc-vcard-line {
        margin: 0 !important;
        padding: 0 !important;
        gap: 6px !important;
      }
      .rc-vcard-line button {
        display: inline !important;
        padding: 0 !important;
        border: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        font-size: inherit !important;
        font-weight: inherit !important;
        color: inherit !important;
        vertical-align: baseline !important;
      }
      .rc-vcard-body { gap: 10px !important; padding: 10px 12px !important; }
      .rc-vcard-role { margin: 0 0 1px !important; }

      .rc-vcard-foot {
        border-top: 1px solid #f1f5f9; padding: 6px 12px;
        display: flex; flex-wrap: wrap; gap: 4px 8px;
        font-size: 0.65rem; color: #64748b; background: #f8fafc;
      }
      .rc-vcard-pill {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 8px; border-radius: 999px; background: #fff;
        border: 1px solid #e2e8f0; font-weight: 600;
      }

      /* BUG FIX: dark mode flipped the NAME text to near-white
         (color: #f8fafc, above) but never gave the card itself a matching
         dark background -- .rc-vcard stayed hardcoded #fff regardless of
         theme. Near-white text on a still-white card is exactly the "light
         grey names on light background" symptom: technically present,
         effectively invisible. The role/line text colours were already
         correctly overridden; only the surfaces (card, footer strip, pill
         badges) and their borders were missing a dark counterpart. */
      html[data-theme="dark"] .rc-vcard, [data-theme="dark"] .rc-vcard {
        background: #1e293b !important;
        border-color: #334155 !important;
      }
      html[data-theme="dark"] .rc-vcard:hover, [data-theme="dark"] .rc-vcard:hover {
        border-color: #475569 !important;
      }
      html[data-theme="dark"] .rc-vcard-photo, [data-theme="dark"] .rc-vcard-photo {
        background: #0f172a !important;
        border-color: #334155 !important;
        color: #94a3b8 !important;
      }
      html[data-theme="dark"] .rc-vcard-foot, [data-theme="dark"] .rc-vcard-foot {
        background: #0f172a !important;
        border-color: #334155 !important;
        color: #94a3b8 !important;
      }
      html[data-theme="dark"] .rc-vcard-pill, [data-theme="dark"] .rc-vcard-pill {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #cbd5e1 !important;
      }
      html[data-theme="dark"] .rc-vcard-line i, [data-theme="dark"] .rc-vcard-line i {
        color: #64748b !important;
      }
    </style>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-2 items-start">
        <template x-for="(i, idx) in sortedUsers" :key="i.id || i.slug || idx">
            <article data-rc-vcard
                @click="selectUser(i)"
                role="button"
                tabindex="0"
                :aria-label="'Contact card for ' + (i.name || 'team member')"
                @keydown.enter.prevent="selectUser(i)"
                @keydown.space.prevent="selectUser(i)"
                class="rc-vcard"
                :class="isSelected(i) ? 'is-selected' : ''">
                <div class="rc-vcard-accent" :class="['bg-slate-800','bg-blue-700','bg-indigo-700','bg-teal-700','bg-sky-700','bg-violet-700','bg-cyan-700','bg-emerald-700'][idx % 8]"></div>
                <div class="rc-vcard-body">
                    <button type="button" class="rc-fav-btn" @click="toggleFav(i, $event)"
                            :aria-pressed="isFav(i) ? 'true' : 'false'"
                            :aria-label="isFav(i) ? 'Remove from favourites' : 'Add to favourites'"
                            :title="isFav(i) ? 'Unpin' : 'Pin favourite'"
                            style="position:absolute;top:0.5rem;right:0.5rem;z-index:2;width:1.75rem;height:1.75rem;border-radius:999px;border:1px solid #e2e8f0;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer">
                        <i class="fa-star text-xs" :class="isFav(i) ? 'fa-solid text-amber-500' : 'fa-regular text-slate-400'" aria-hidden="true"></i>
                    </button>
                    <div class="rc-vcard-photo">
                        <template x-if="i.photo">
                            <img :src="(window.rcMediaUrl ? rcMediaUrl(i.photo) : ('media_serve.php?f=' + encodeURIComponent(String(i.photo).replace(/^.*[\\\/]/,''))))" alt="" loading="lazy" width="72" height="72">
                        </template>
                        <template x-if="!i.photo">
                            <span x-text="(i.name || '?').charAt(0).toUpperCase()"></span>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="rc-vcard-name" x-text="i.name || '—'"></h3>
                        <p class="rc-vcard-role">
                            <button type="button" class="text-left bg-transparent border-0 p-0 font-inherit cursor-pointer"
                                :class="isPendingField(labelDesig(i)) ? 'text-amber-700 underline decoration-amber-400 decoration-dotted font-semibold' : ''"
                                :title="isPendingField(labelDesig(i)) ? 'Click to set designation' : ''"
                                @click.stop="fixPending(i, 'designation')"
                                x-text="labelDesig(i)"></button>
                        </p>
                        <div class="rc-vcard-line">
                            <i class="fa-solid fa-sitemap" aria-hidden="true"></i>
                            <button type="button" class="text-left bg-transparent border-0 p-0 font-inherit cursor-pointer"
                                :class="isPendingField(labelDept(i)) ? 'text-amber-700 underline decoration-amber-400 decoration-dotted font-semibold' : ''"
                                :title="isPendingField(labelDept(i)) ? 'Click to set department' : ''"
                                @click.stop="fixPending(i, 'department')"
                                x-text="labelDept(i)"></button>
                        </div>
                        <div class="rc-vcard-line">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <button type="button" class="text-left bg-transparent border-0 p-0 font-inherit cursor-pointer"
                                :class="isPendingField(labelLoc(i)) ? 'text-amber-700 underline decoration-amber-400 decoration-dotted font-semibold' : ''"
                                :title="isPendingField(labelLoc(i)) ? 'Click to set location' : ''"
                                @click.stop="fixPending(i, 'location')"
                                x-text="labelLoc(i)"></button>
                        </div>
                        <template x-if="i.house_no || i.house_number">
                            <div class="rc-vcard-line"><i class="fa-solid fa-house" aria-hidden="true"></i><span x-text="'House ' + (i.house_no || i.house_number)"></span></div>
                        </template>
                        <template x-if="i.phone || i.mobile">
                            <div class="rc-vcard-line"><i class="fa-solid fa-phone" aria-hidden="true"></i><span x-text="i.phone || i.mobile"></span></div>
                        </template>
                        <template x-if="i.email">
                            <div class="rc-vcard-line"><i class="fa-solid fa-envelope" aria-hidden="true"></i><span class="truncate" x-text="i.email"></span></div>
                        </template>
                    </div>
                </div>
                <div class="rc-vcard-foot">
                    <template x-if="i.gotra"><span class="rc-vcard-pill" x-text="'Gotra · ' + i.gotra"></span></template>
                    <template x-if="i.blood_group || i.blood"><a class="rc-vcard-pill" :href="'blood_report.php?slug=' + encodeURIComponent(i.slug || i.id || '') + (i.blood_group || i.blood ? ('&group=' + encodeURIComponent(i.blood_group || i.blood)) : '')" target="_blank" rel="noopener" @click.stop x-text="'Blood · ' + (i.blood_group || i.blood)" :title="'Open blood report'"></a></template>
                    <template x-if="i.dob"><span class="rc-vcard-pill" x-text="'DOB · ' + formatDob(i.dob)"></span></template>
                    <template x-if="i.gender"><span class="rc-vcard-pill" x-text="i.gender"></span></template>
                </div>
            </article>
        </template>
    </div>
</div>
