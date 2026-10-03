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
        // YYYY-MM-DD -- the format <input type="date"> is required to use,
        // not a display choice. This project's stated convention is
        // DD-MM-YYYY everywhere. Display-only: the underlying stored value
        // and the date-picker binding both stay ISO, since the native
        // picker requires that format to function at all -- only what
        // renders on screen changes.
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

<div class="w-full space-y-3" x-data="teamDirectory">
    <!-- Primary list chrome: title + count + sort chips + card utilities (single row) -->
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex items-center gap-2 min-w-0 mr-auto">
            <h2 class="text-sm font-bold text-slate-800 truncate">Human Capital Index</h2>
            <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 tabular-nums"
                  x-text="totalCount + ' members'"></span>
            <span class="text-[10px] text-slate-400" x-show="typeof window.__RC_TEAM_COUNT__ === 'number' && window.__RC_TEAM_COUNT__ !== totalCount"
                  x-text="'· server ' + window.__RC_TEAM_COUNT__"></span>
        </div>
        <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Sort by">
            <button type="button" @click="toggleSort('rank')"
                    class="h-7 px-2.5 rounded-md text-[11px] font-bold border transition"
                    :class="sortMode==='rank' ? 'bg-blue-600 text-white border-blue-600' : 'text-slate-600 bg-white border-slate-200 hover:border-blue-300'">
                Rank<span x-text="caret('rank')"></span>
            </button>
            <button type="button" @click="toggleSort('name')"
                    class="h-7 px-2.5 rounded-md text-[11px] font-bold border transition"
                    :class="sortMode==='name' ? 'bg-blue-600 text-white border-blue-600' : 'text-slate-600 bg-white border-slate-200 hover:border-blue-300'">
                Name<span x-text="caret('name')"></span>
            </button>
            <button type="button" @click="toggleSort('location')"
                    class="h-7 px-2.5 rounded-md text-[11px] font-bold border transition"
                    :class="sortMode==='location' ? 'bg-blue-600 text-white border-blue-600' : 'text-slate-600 bg-white border-slate-200 hover:border-blue-300'">
                Loc<span x-text="caret('location')"></span>
            </button>
            <button type="button" @click="toggleSort('department')"
                    class="h-7 px-2.5 rounded-md text-[11px] font-bold border transition"
                    :class="sortMode==='department' ? 'bg-blue-600 text-white border-blue-600' : 'text-slate-600 bg-white border-slate-200 hover:border-blue-300'">
                Dept<span x-text="caret('department')"></span>
            </button>
        </div>
        <span class="w-px h-5 bg-slate-200 hidden sm:block"></span>
        <div class="flex flex-wrap items-center gap-1">
            <button type="button" @click="shareSelected()" class="rc-hit-lg h-11 px-3 rounded-md text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-100" data-rc-tooltip="Opens WhatsApp with selected contacts" aria-label="Share selected contacts via WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('business')" class="rc-hit-lg h-11 w-11 rounded-md text-slate-600 bg-white border border-slate-200" data-rc-tooltip="Open digital business card for selected person" aria-label="Open digital business card"><i class="fa-solid fa-id-badge text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('id')" class="rc-hit-lg h-11 w-11 rounded-md text-slate-600 bg-white border border-slate-200" data-rc-tooltip="Open employee ID card" aria-label="Open employee ID card"><i class="fa-solid fa-id-card text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('visiting')" class="rc-hit-lg h-11 w-11 rounded-md text-slate-600 bg-white border border-slate-200" data-rc-tooltip="Open visiting card" aria-label="Open visiting card"><i class="fa-solid fa-address-card text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('qr')" class="rc-hit-lg h-11 w-11 rounded-md text-slate-600 bg-white border border-slate-200" data-rc-tooltip="Open QR contact card" aria-label="Open QR contact card"><i class="fa-solid fa-qrcode text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('signature')" class="rc-hit-lg h-11 w-11 rounded-md text-slate-600 bg-white border border-slate-200" data-rc-tooltip="Open email signature block" aria-label="Open email signature card"><i class="fa-solid fa-signature text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('numero')" class="rc-hit-lg h-11 w-11 rounded-md text-slate-600 bg-white border border-slate-200" data-rc-tooltip="Open Vedic numerology report" aria-label="Open numerology report"><i class="fa-solid fa-hashtag text-xs" aria-hidden="true"></i></button>
            <!-- BUG FIX: Janam Patri / Kundli Milan was missing from this row
                 entirely, even though it already exists as a real page
                 (janam_patri.php) and is already linked from the sidebar
                 nav. Placed immediately after Numero, matching where it
                 already sits in that sidebar group. -->
            <button type="button" @click="cardUrl('janam')" class="rc-hit-lg h-11 w-11 rounded-md text-slate-600 bg-white border border-slate-200" data-rc-tooltip="Open Janam Patri / Kundli Milan" aria-label="Open Janam Patri and Kundli Milan"><i class="fa-solid fa-om text-xs" aria-hidden="true"></i></button>
            <button type="button" @click="cardUrl('blood')" class="rc-hit-lg h-11 w-11 rounded-md text-slate-600 bg-white border border-slate-200" data-rc-tooltip="Open blood group report (team fun — not medical)" aria-label="Open blood group report"><i class="fa-solid fa-droplet text-xs" aria-hidden="true"></i></button>
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
        min-height: 168px;
      }
      .rc-vcard:hover { border-color: #cbd5e1; box-shadow: 0 8px 24px rgba(15,23,42,.08); }
      .rc-vcard.is-selected { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.25); }
      .rc-vcard-accent { height: 4px; width: 100%; }
      .rc-vcard-body { position: relative; padding: 14px 16px 16px; display: flex; gap: 12px; flex: 1; }
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
      .rc-vcard-role { font-size: 0.75rem; font-weight: 600; color: #1d4ed8; margin: 0 0 6px; }
      .rc-vcard-line { font-size: 0.7rem; color: #475569; margin: 2px 0; display: flex; gap: 6px; align-items: flex-start; }
      .rc-vcard-line i { width: 12px; color: #94a3b8; margin-top: 2px; flex-shrink: 0; }
      .rc-vcard-foot {
        border-top: 1px solid #f1f5f9; padding: 8px 16px;
        display: flex; flex-wrap: wrap; gap: 6px 10px;
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

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3">
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
                                :class="isPendingField(i.designation_name || i.designation) ? 'text-amber-700 underline decoration-amber-400 decoration-dotted font-semibold' : ''"
                                :title="isPendingField(i.designation_name || i.designation) ? 'Click to set designation' : ''"
                                @click.stop="fixPending(i, 'designation')"
                                x-text="i.designation_name || i.designation || '—'"></button>
                        </p>
                        <div class="rc-vcard-line">
                            <i class="fa-solid fa-sitemap" aria-hidden="true"></i>
                            <button type="button" class="text-left bg-transparent border-0 p-0 font-inherit cursor-pointer"
                                :class="isPendingField(i.department_name || i.department) ? 'text-amber-700 underline decoration-amber-400 decoration-dotted font-semibold' : ''"
                                :title="isPendingField(i.department_name || i.department) ? 'Click to set department' : ''"
                                @click.stop="fixPending(i, 'department')"
                                x-text="i.department_name || i.department || 'Department Pending'"></button>
                        </div>
                        <div class="rc-vcard-line">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <button type="button" class="text-left bg-transparent border-0 p-0 font-inherit cursor-pointer"
                                :class="isPendingField(i.location_name || i.location) ? 'text-amber-700 underline decoration-amber-400 decoration-dotted font-semibold' : ''"
                                :title="isPendingField(i.location_name || i.location) ? 'Click to set location' : ''"
                                @click.stop="fixPending(i, 'location')"
                                x-text="i.location_name || i.location || 'Location Unassigned'"></button>
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
