        document.addEventListener('alpine:init', () => {
            Alpine.data('dashboardApp', () => ({
                state: window.__DASHBOARD_STATE__ || { data:{}, company:{}, auth:{}, context:{} },
                data: (function(){
                    var d = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.data) ? window.__DASHBOARD_STATE__.data : {};
                    ['team','locations','bank','docs','events','departments','designations','cartags','statutory','leads'].forEach(function(ns){
                        if (d[ns] == null) d[ns] = [];
                        else if (!Array.isArray(d[ns])) d[ns] = Object.keys(d[ns]).map(function(k){ return d[ns][k]; }).filter(Boolean);
                    });
                    return d;
                })(),
                company: (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.company) ? window.__DASHBOARD_STATE__.company : {},
                isAdmin: !!(window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.auth && window.__DASHBOARD_STATE__.auth.isAdmin),
                isSuperAdmin: !!(window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.auth && window.__DASHBOARD_STATE__.auth.isSuperAdmin),
                showChangelog: false,
                cur: (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.context && window.__DASHBOARD_STATE__.context.tab) ? window.__DASHBOARD_STATE__.context.tab : 'team',
                search: '', globalSearchOpen: false, favIds: [], ts: Date.now(), sidebarOpen: window.innerWidth >= 768,
                mobileMore: false,
                clock: '',
                optLoading: false, optLogs: [],
                sortCol: (function () {
                    const t = new URLSearchParams(window.location.search).get('tab') || 'team';
                    if (t === 'events') return 'date';
                    if (t === 'team' || t === '') return 'rank';
                    return 'name';
                })(), sortAsc: true, sortEpoch: 0,
                docPreviewUrl: null, page: 1, perPage: 12, _perPageBound: false, _resizeTimer: null,
                get primaryNav() {
                    return [
                        {id:'team',   icon:'fa-solid fa-users',       label:'Human Capital'},
                        {id:'docs',   icon:'fa-solid fa-folder-open', label:'Documents'},
                        {id:'bank',   icon:'fa-solid fa-building-columns', label:'Treasury & Banking Ledger'},
                        {id:'events', icon:'fa-solid fa-calendar',    label:'Calendar'}
                    ];
                },
                get primaryNavIds() {
                    return this.primaryNav.map(n => n.id);
                },
                get moreNavActive() {
                    return !this.primaryNavIds.includes(this.cur);
                },
                get navGroups() {
                    if (this.isSuperAdmin) {
                        return [
                            {
                                title: 'Control Plane',
                                items: [
                                    { id: 'access',  icon: 'fa-solid fa-user-shield',   label: 'Access Mode' },
                                    { id: 'tenants', icon: 'fa-solid fa-building-user', label: 'Tenant Setup' },
                                    { id: 'monitor', icon: 'fa-solid fa-heart-pulse',   label: 'Monitor / Optimisation' },
                                ]
                            }
                        ];
                    }
                    const groups = [];
                    const isVisitor = !this.isAdmin && !this.isSuperAdmin;
                    groups.push({ title: 'Human Capital', items: [
                        { id: 'team', icon: 'fa-solid fa-users', label: 'Human Capital Index' }
                    ]});
                    groups.push({ title: 'Field Operations', items: [
                        { id: 'tracking', icon: 'fa-solid fa-location-crosshairs', label: 'Live Location' },
                        { id: 'ops',      icon: 'fa-solid fa-clipboard-list',      label: 'Field Ops Hub' },
                        { id: 'cartags',  icon: 'fa-solid fa-car',                 label: 'Fleet Asset Registry' },
                        { id: 'dispatch', icon: 'fa-solid fa-route',               label: 'Dispatch & Routes' },
                        { id: 'locations',icon: 'fa-solid fa-map-location-dot',    label: 'Shared Locations' },
                        { id: 'assets',   icon: 'fa-solid fa-box-open',            label: 'Asset Checkout' },
                    ]});
                    groups.push({ title: 'Institutional Resources', items: [
                        { id: 'bank',      icon: 'fa-solid fa-building-columns', label: 'Treasury & Banking Ledger' },
                        { id: 'docs',      icon: 'fa-solid fa-folder-open',      label: 'Document Vault' },
                        { id: 'events',    icon: 'fa-solid fa-calendar-days',    label: 'Calendar' },
                        { id: 'statutory', icon: 'fa-solid fa-scale-balanced',   label: 'Compliance Register' },
                        { id: 'expiry',    icon: 'fa-solid fa-hourglass-end',    label: 'Expiry Radar' },
                        { id: 'status',    icon: 'fa-solid fa-chart-line',       label: 'Delivery Status' },
                        { id: 'org',       icon: 'fa-solid fa-diagram-project',  label: 'Org Chart' },
                    ]});
                    groups.push({ title: 'Insights', items: [
                        { id: 'numero', icon: 'fa-solid fa-wand-magic-sparkles', label: 'Vedic Numero' },
                        { id: 'janam', url: 'janam_patri.php', icon: 'fa-solid fa-om', label: 'Janam Patri' },
                        { id: 'statistics', icon: 'fa-solid fa-chart-pie', label: 'Statistics Panel' },
                    ]});
                    if (this.isAdmin) {
                        groups.push({ title: 'Enterprise Configuration', items: [
                            { id: 'company',      icon: 'fa-solid fa-sliders',     label: 'Organizational Config' },
                            { id: 'departments',  icon: 'fa-solid fa-sitemap',     label: 'Organizational Units' },
                            { id: 'designations', icon: 'fa-solid fa-list-check',  label: 'Role Taxonomy' },
                            { id: 'settings',     icon: 'fa-solid fa-plug',        label: 'Integrations' },
                            { id: 'leads',        icon: 'fa-solid fa-address-card',label: 'Opportunity Pipeline' },
                            { id: 'cctv',         icon: 'fa-solid fa-video',       label: 'Surveillance Access' },
                        ]});
                        groups.push({ title: 'Platform Operations', items: [
                            { id: 'health', icon: 'fa-solid fa-server',          label: 'Health & Backup' },
                            { id: 'audit',  icon: 'fa-solid fa-clipboard-list',  label: 'Audit Ledger' },
                        ]});
                    }
                    groups.push({ title: 'Session', items: [
                        { id: 'access', icon: 'fa-solid fa-user-shield', label: 'Access Mode' },
                        { id: 'terms',  icon: 'fa-solid fa-scroll',      label: 'Governance Policy' },
                    ]});
                    const auth = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.auth) ? window.__DASHBOARD_STATE__.auth : {};
                    const allowed = Array.isArray(auth.allowedTabs) ? auth.allowedTabs : null;
                    const modEnabled = (auth.modules && auth.modules.enabled) ? auth.modules.enabled : {};
                    const isSa = !!auth.isSuperAdmin;
                    return groups.map(g => ({
                        ...g,
                        items: g.items.filter(it => {
                            if (!it.id) return true; // external url items
                            if (['tenants','monitor','opt'].includes(it.id) && !isSa) return false;
                            if (Array.isArray(allowed) && !allowed.includes(it.id) && !it.url) return false;
                            // Module matrix: disabled modules hidden for Co Admin / Visitor
                            if (!isSa && Object.prototype.hasOwnProperty.call(modEnabled, it.id) && !modEnabled[it.id]) {
                                return false;
                            }
                            return true;
                        })
                    })).filter(g => g.items.length);
                },
                init() {
                    if (typeof this.loadFavourites === 'function') this.loadFavourites();
                    Alpine.store('appState', this);
                    this._docTrap = null;
                    this._moreTrap = null;
                    this.$watch('docPreviewUrl', (url) => {
                        if (url) {
                            this.$nextTick(() => {
                                const panel = document.querySelector('[data-rc-dialog="doc-preview"]');
                                if (window.RcFocusTrap && panel) {
                                    if (this._docTrap) this._docTrap.deactivate();
                                    this._docTrap = window.RcFocusTrap.activate(panel, {
                                        returnFocus: document.activeElement
                                    });
                                }
                            });
                        } else if (this._docTrap) {
                            this._docTrap.deactivate();
                            this._docTrap = null;
                        }
                    });
                    this.$watch('search', () => {
                        const el = document.getElementById('rc-status-live');
                        if (el) el.textContent = (this.filteredList?.length ?? 0) + ' entries shown';
                    });
                    this.$watch('filteredList', () => {
                        const el = document.getElementById('rc-status-live');
                        if (el) el.textContent = (this.filteredList?.length ?? 0) + ' entries shown';
                    });
                    this.$watch('mobileMore', (open) => {
                        if (open) {
                            this.$nextTick(() => {
                                const panel = document.querySelector('[data-rc-dialog="mobile-more"]');
                                if (window.RcFocusTrap && panel) {
                                    if (this._moreTrap) this._moreTrap.deactivate();
                                    this._moreTrap = window.RcFocusTrap.activate(panel, {
                                        returnFocus: document.activeElement
                                    });
                                }
                            });
                        } else if (this._moreTrap) {
                            this._moreTrap.deactivate();
                            this._moreTrap = null;
                        }
                    });
                    const bindSidebarScroll = () => {
                        const side = this.$refs.sidebarNav
                            || document.getElementById('rc-sidebar-nav')
                            || document.querySelector('aside.sidebar nav.scroll-area')
                            || document.querySelector('aside.sidebar nav');
                        if (!side || side.dataset.scrollBound) return;
                        side.dataset.scrollBound = '1';
                        try {
                            const y = parseInt(sessionStorage.getItem('rc-sidebar-scroll') || '0', 10);
                            if (y > 0) side.scrollTop = y;
                        } catch (e) {}
                        side.addEventListener('scroll', () => {
                            try { sessionStorage.setItem('rc-sidebar-scroll', String(side.scrollTop || 0)); } catch (e) {}
                        }, { passive: true });
                    };
                    this.$nextTick(() => { bindSidebarScroll(); setTimeout(bindSidebarScroll, 300); });
                    window.runOptimise = () => this.runOptimise();
                    this.$watch('cur', (val) => {
                        this.page = 1;
                        this.search = '';
                        this.sortCol = val === 'events' ? 'date' : (val === 'team' ? 'rank' : 'name');
                        this.sortAsc = true;
                        this.$nextTick(() => this.recalculatePerPage());
                    });
                    this.$watch('filteredList', () => {
                        this.$nextTick(() => {
                            const maxPage = Math.max(1, Math.ceil((this.filteredList.length || 0) / Math.max(1, this.perPage)));
                            if (this.page > maxPage) this.page = maxPage;
                        });
                    });
                    this.bindPerPageAuto();
                    this.tickClock();
                    setInterval(() => this.tickClock(), 30000);
                },
                resolveName(type, id) {
                    if (!id) return 'Not Set';
                    const items = this.data[type] || [];
                    const found = items.find(i =>
                        String(i.id || '').toLowerCase() === String(id).toLowerCase() ||
                        String(i.slug || '').toLowerCase() === String(id).toLowerCase()
                    );
                    return found ? (found.name || found.title || id) : id;
                },
                onSortColChange() {
                    this.page = 1;
                    this.sortEpoch = (this.sortEpoch || 0) + 1;
                    this.syncTeamSort();
                },
                toggleSortDir() {
                    this.sortAsc = !this.sortAsc;
                    this.page = 1;
                    this.sortEpoch = (this.sortEpoch || 0) + 1;
                    this.syncTeamSort();
                },
                setSortCol(col) {
                    if (!col) return;
                    const changed = this.sortCol !== col;
                    this.sortCol = col;
                    if (changed) this.sortAsc = true;
                    else this.sortAsc = !this.sortAsc;
                    this.page = 1;
                    this.syncTeamSort();
                },
                syncTeamSort() {
                    if (this.cur !== 'team' || !window.__teamApi) return;
                    const api = typeof window.__teamApi.get === 'function' ? window.__teamApi.get() : null;
                    if (!api) return;
                    const map = { rank: 'rank', name: 'name', location: 'location', department: 'department', designation: 'name' };
                    api.sortMode = map[this.sortCol] || 'name';
                    api.sortDir = this.sortAsc ? 'asc' : 'desc';
                },
                sortBy(col) {
                    this.setSortCol(col);
                },
                cardHeaderClass(idx) {
                    const tones = [
                        'bg-slate-900', 'bg-blue-800', 'bg-indigo-800', 'bg-teal-800',
                        'bg-sky-900', 'bg-violet-900', 'bg-cyan-900', 'bg-emerald-900'
                    ];
                    return tones[(Number(idx) || 0) % tones.length];
                },
                get processedData() {
                    let d = Array.isArray(this.data[this.cur]) ? this.data[this.cur] : [];
                    return d.map(i => {
                        const row = { ...i };
                        if (this.cur === 'docs') {
                            if (!row.size || String(row.size).trim() === '') {
                                const bytes = parseInt(row.file_size || row.filesize || row.bytes || 0, 10);
                                if (bytes > 0) row.size = this.formatBytes(bytes);
                                else if (row.external_url) row.size = '';
                            }
                            if (!row.file_type) {
                                const fn = String(row.doc_file || row.name || row.external_url || '');
                                const m = fn.match(/\.([a-z0-9]{2,5})(?:\?|$)/i);
                                if (m) row.file_type = m[1].toLowerCase();
                                else if (row.external_url) row.file_type = 'link';
                            }
                        }
                        if (this.cur === 'team') {
                            const map = this._designationRankMap();
                            let r = parseInt(row.rank ?? row.sort_order ?? row.order ?? 0, 10);
                            if (!r || r >= 900) {
                                for (const k of [row.designation_id, row.designation_code, row.designation, row.designation_name, row.labels]) {
                                    if (k != null && String(k).trim() !== '' && map[String(k).trim().toLowerCase()] !== undefined) {
                                        r = map[String(k).trim().toLowerCase()];
                                        break;
                                    }
                                }
                            }
                            row.rank = (r && r < 900) ? r : '';
                            row.hierarchy_rank = (r && r < 900) ? r : 9999;
                        }
                        const vals = Object.values(row).map(v => typeof v === 'string' ? v : (typeof v === 'number' ? String(v) : '')).join(' ');
                        row.__search = (row.__search || vals).toLowerCase();
                        return row;
                    });
                },
                favKey() {
                    const t = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.tenantId) ||
                              (document.documentElement.getAttribute('data-tenant') || 'default');
                    return 'rc_favs_' + t;
                },
                loadFavourites() {
                    try {
                        const raw = localStorage.getItem(this.favKey());
                        this.favIds = raw ? JSON.parse(raw) : [];
                        if (!Array.isArray(this.favIds)) this.favIds = [];
                    } catch (e) { this.favIds = []; }
                },
                saveFavourites() {
                    try { localStorage.setItem(this.favKey(), JSON.stringify(this.favIds.slice(0, 40))); } catch (e) {}
                },
                isFav(item) {
                    if (!item) return false;
                    const id = String(item.slug || item.id || '');
                    return id !== '' && this.favIds.indexOf(id) >= 0;
                },
                toggleFav(item, ev) {
                    if (ev) { ev.preventDefault(); ev.stopPropagation(); }
                    if (!item) return;
                    const id = String(item.slug || item.id || '');
                    if (!id) return;
                    const i = this.favIds.indexOf(id);
                    if (i >= 0) this.favIds.splice(i, 1);
                    else this.favIds.unshift(id);
                    this.favIds = this.favIds.slice(0, 40);
                    this.saveFavourites();
                },
                get globalHits() {
                    const q = (this.search || '').trim().toLowerCase();
                    if (q.length < 2) return [];
                    const buckets = [
                        { ns: 'team', label: 'People', tab: 'team', fields: ['name','phone','mobile','email','designation_name','department_name','gotra','slug'] },
                        { ns: 'docs', label: 'Documents', tab: 'docs', fields: ['title','name','doc_name','category','tags'] },
                        { ns: 'cartags', label: 'Vehicles', tab: 'cartags', fields: ['plate','tag_id','make_model','name','owner_name'] },
                        { ns: 'bank', label: 'Treasury & Banking Ledger', tab: 'bank', fields: ['bank_name','acc_no','upi_id','holder_name','ifsc'] },
                        { ns: 'locations', label: 'Locations', tab: 'locations', fields: ['name','city','address','pincode'] },
                    ];
                    const out = [];
                    for (const b of buckets) {
                        const rows = Array.isArray(this.data[b.ns]) ? this.data[b.ns] : [];
                        for (const r of rows) {
                            if (!r || typeof r !== 'object') continue;
                            const hay = b.fields.map(f => String(r[f] || '')).join(' ').toLowerCase();
                            if (hay.indexOf(q) === -1) continue;
                            out.push({
                                ns: b.ns,
                                tab: b.tab,
                                kind: b.label,
                                title: r.name || r.title || r.plate || r.tag_id || r.bank_name || r.holder_name || 'Record',
                                sub: r.designation_name || r.phone || r.mobile || r.upi_id || r.city || r.category || r.slug || '',
                                id: r.id || r.slug || '',
                                slug: r.slug || '',
                                raw: r
                            });
                            if (out.length >= 24) return out;
                        }
                    }
                    return out;
                },
                openGlobalHit(hit) {
                    if (!hit) return;
                    this.globalSearchOpen = false;
                    this.cur = hit.tab;
                    this.search = '';
                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('tab', hit.tab);
                        window.history.replaceState({}, '', url);
                    } catch (e) {}
                    if (hit.tab === 'team' && (hit.slug || hit.id)) {
                        this.$nextTick(() => {
                            this.search = String(hit.title || '');
                        });
                    }
                },
                get searchSuggestions() {
                    const rows = Array.isArray(this.filteredList) ? this.filteredList : [];
                    const out = new Set();
                    const take = (v) => {
                        if (v === null || v === undefined) return;
                        const s = String(v).trim();
                        if (s.length >= 2 && s.length <= 80) out.add(s);
                    };
                    rows.slice(0, 500).forEach(r => {
                        if (!r || typeof r !== 'object') return;
                        take(r.name); take(r.company_name); take(r.holder_name); take(r.owner_name);
                        take(r.slug); take(r.tag_id); take(r.phone); take(r.mobile); take(r.telephone);
                        take(r.email); take(r.acc_no); take(r.ifsc); take(r.upi_id); take(r.bank_name);
                        take(r.pan); take(r.gst); take(r.gstin); take(r.cin); take(r.tan); take(r.lei);
                        take(r.registration_number); take(r.plate); take(r.make_model);
                        take(r.designation_name); take(r.department_name);
                        take(r.location_name); take(r.city); take(r.state); take(r.pincode);
                        take(r.msme); take(r.roc_code);
                    });
                    return Array.from(out).slice(0, 80);
                },
                tickClock() {
                    try {
                        this.clock = new Intl.DateTimeFormat('en-IN', {
                            timeZone: 'Asia/Kolkata',
                            weekday: 'short',
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        }).format(new Date());
                    } catch (e) {
                        this.clock = new Date().toLocaleString('en-IN');
                    }
                },
get filteredList() {
                    void this.sortEpoch;
                    let d = this.processedData;
                    if (this.search) {
                        const s = this.search.toLowerCase();
                        d = d.filter(i => (i.__search || '').includes(s));
                    }
                    if (this.sortCol) {
                        const col = this.sortCol;
                        const asc = this.sortAsc;
                        const desigMap = this._designationRankMap();
                        d = d.slice().sort((a, b) => {
                            const pick = (row) => {
                                if (!row) return '';
                                if (col === 'rank' || col === 'sort_order' || col === 'order') {
                                    for (const k of [row.designation_id, row.designation_code, row.designation, row.designation_name, row.labels]) {
                                        if (k != null && String(k).trim() !== '' && desigMap[String(k).trim().toLowerCase()] !== undefined) {
                                            return desigMap[String(k).trim().toLowerCase()];
                                        }
                                    }
                                    const n = parseInt(row.rank ?? row.sort_order ?? row.order ?? 9999, 10);
                                    return isNaN(n) ? 9999 : n;
                                }
                                if (col === 'size') {
                                    const raw = row.file_size ?? row.size_bytes ?? row.size;
                                    if (typeof raw === 'number') return raw;
                                    const m = String(raw || '').match(/([\d.]+)\s*(B|KB|MB|GB)?/i);
                                    if (!m) return 0;
                                    const n = parseFloat(m[1]); const u = (m[2] || 'B').toUpperCase();
                                    return n * ({ B:1, KB:1024, MB:1048576, GB:1073741824 }[u] || 1);
                                }
                                if (col === 'date' || col === 'updated_at' || col === 'created_at' || col === 'dob') {
                                    return Date.parse(row[col] || row.updated_at || row.created_at || row.date || row.dob || 0) || 0;
                                }
                                const aliases = {
                                    bank: ['holder_name','bank_name','name'],
                                    branch: ['branch','bank_branch'],
                                    ifsc: ['ifsc','ifsc_code'],
                                    upi: ['upi_id','upi'],
                                    location: ['location_name','location','city','address'],
                                    department: ['department_name','department','code'],
                                    designation: ['designation_name','designation','code'],
                                    reg: ['registration_number','reg_no','plate'],
                                    vehicle: ['make_model','vehicle_class'],
                                    version: ['version','file_version'],
                                    type: ['file_type','type','mime'],
                                    title: ['title','name','company_name'],
                                    pan: ['pan'],
                                    gst: ['gst','gstin'],
                                    code: ['code','slug','id'],
                                    holder_name: ['holder_name','account_holder','name'],
                                    bank_name: ['bank_name','bank'],
                                    city: ['city'],
                                    state: ['state'],
                                    pincode: ['pincode','pin'],
                                    status: ['status'],
                                    email: ['email'],
                                    company_name: ['company_name','name'],
                                };
                                let v = row[col];
                                if (v === undefined || v === null || v === '') {
                                    for (const k of (aliases[col] || [])) {
                                        if (row[k] !== undefined && row[k] !== null && row[k] !== '') { v = row[k]; break; }
                                    }
                                }
                                if (v === undefined || v === null || v === '') {
                                    v = row.name || row.company_name || row.holder_name || row.title || row.code || row.slug || '';
                                }
                                if (typeof v === 'number') return v;
                                return String(v).toLowerCase();
                            };
                            const vA = pick(a), vB = pick(b);
                            if (typeof vA === 'number' && typeof vB === 'number') return asc ? (vA - vB) : (vB - vA);
                            const cmp = String(vA).localeCompare(String(vB), 'en-IN', { sensitivity: 'base', numeric: true });
                            return asc ? cmp : -cmp;
                        });
                    }
                    return d;
                },
                _designationRankMap() {
                    const rows = (this.data && this.data.designations) || [];
                    if (!Array.isArray(rows) || !rows.length) return {};
                    const sorted = rows.slice().sort((a, b) => {
                        const ra = parseInt(a.rank ?? a.hierarchy_rank ?? (/^\d+$/.test(String(a.code||'')) ? a.code : 9999), 10) || 9999;
                        const rb = parseInt(b.rank ?? b.hierarchy_rank ?? (/^\d+$/.test(String(b.code||'')) ? b.code : 9999), 10) || 9999;
                        if (ra !== rb) return ra - rb;
                        return String(a.code || a.name || '').localeCompare(String(b.code || b.name || ''), 'en-IN', { sensitivity: 'base' });
                    });
                    const map = {};
                    sorted.forEach((d, idx) => {
                        let r = parseInt(d.rank ?? d.hierarchy_rank ?? (/^\d+$/.test(String(d.code||'')) ? d.code : (idx + 1)), 10);
                        if (isNaN(r)) r = idx + 1;
                        [d.id, d.code, d.slug, d.name].forEach(k => {
                            if (k != null && String(k).trim() !== '') map[String(k).trim().toLowerCase()] = r;
                        });
                    });
                    return map;
                },
                get sortOptions() {
                    const tab = this.cur || 'team';
                    const map = {
                        team: [
                            { id: 'rank', label: 'Rank' }, { id: 'name', label: 'Name' },
                            { id: 'designation', label: 'Designation' }, { id: 'department', label: 'Department' },
                            { id: 'location', label: 'Location' },
                        ],
                        bank: [
                            { id: 'holder_name', label: 'Holder' }, { id: 'bank_name', label: 'Bank' },
                            { id: 'branch', label: 'Branch' }, { id: 'ifsc', label: 'IFSC' }, { id: 'upi', label: 'UPI' },
                        ],
                        docs: [
                            { id: 'name', label: 'Name' }, { id: 'version', label: 'Version' },
                            { id: 'size', label: 'Size' }, { id: 'type', label: 'Type' }, { id: 'updated_at', label: 'Updated' },
                        ],
                        events: [
                            { id: 'date', label: 'Date' }, { id: 'name', label: 'Title' }, { id: 'location', label: 'Location' },
                        ],
                        locations: [
                            { id: 'name', label: 'Name' }, { id: 'city', label: 'City' },
                            { id: 'state', label: 'State' }, { id: 'pincode', label: 'PIN' },
                        ],
                        departments: [ { id: 'code', label: 'Code' }, { id: 'name', label: 'Name' } ],
                        designations: [ { id: 'rank', label: 'Rank' }, { id: 'code', label: 'Code' }, { id: 'name', label: 'Name' } ],
                        cartags: [
                            { id: 'reg', label: 'Registration' }, { id: 'vehicle', label: 'Make/Model' },
                            { id: 'name', label: 'Owner' }, { id: 'status', label: 'Status' },
                        ],
                        statutory: [
                            { id: 'company_name', label: 'Company' }, { id: 'pan', label: 'PAN' },
                            { id: 'gst', label: 'GST' }, { id: 'name', label: 'Name' },
                        ],
                        leads: [
                            { id: 'name', label: 'Name' }, { id: 'date', label: 'Date' }, { id: 'email', label: 'Email' },
                        ],
                    };
                    return map[tab] || [ { id: 'name', label: 'Name' }, { id: 'code', label: 'Code' } ];
                },
                get paginatedList() {
                    const start = (this.page - 1) * this.perPage;
                    return this.filteredList.slice(start, start + this.perPage);
                },
                get totalPages() {
                    const n = (this.filteredList && this.filteredList.length) || 0;
                    const pp = Math.max(1, this.perPage || 1);
                    return Math.max(1, Math.ceil(n / pp));
                },
                recalculatePerPage() {
                    try {
                        const vh = window.innerHeight || document.documentElement.clientHeight || 900;
                        const vw = window.innerWidth || document.documentElement.clientWidth || 1200;
                        const tab = this.cur || 'team';
                        const content =
                            document.querySelector('[data-rc-list]') ||
                            document.querySelector('main #rc-content') ||
                            document.querySelector('main .flex-1.min-h-0') ||
                            document.querySelector('#rc-content') ||
                            document.querySelector('main') ||
                            document.querySelector('.rc-main');
                        let avail = Math.floor(vh * 0.72);
                        if (content) {
                            const r = content.getBoundingClientRect();
                            const fromTop = Math.floor(vh - r.top - 72);
                            if (fromTop > 160) avail = fromTop;
                        } else {
                            const header = document.querySelector('header');
                            const hb = header ? header.getBoundingClientRect().bottom : 120;
                            avail = Math.floor(vh - hb - 80);
                        }
                        let rowH = 0;
                        const sampleSel = {
                            team: '[data-rc-vcard], .rc-vcard',
                            designations: '.grid > div, .rounded-2xl.border',
                            departments: '.grid > div, .rounded-2xl.border',
                            locations: '.grid > div, .bg-white.rounded',
                            events: '.grid > div, .bg-white.rounded',
                        };
                        const sel = sampleSel[tab] || 'tbody tr, .bg-white.rounded-xl, .bg-white.rounded-2xl';
                        const sample = document.querySelector(sel);
                        if (sample) {
                            const h = sample.getBoundingClientRect().height;
                            if (h >= 36 && h <= 220) rowH = h + 12;
                        }
                        let per;
                        if (tab === 'team') {
                            if (!rowH) rowH = vw < 640 ? 80 : 64;
                            per = Math.floor(avail / rowH);
                            const minComfort = Math.floor((vh * 0.55) / rowH);
                            if (per < minComfort) per = minComfort;
                        } else if (['designations', 'departments', 'locations', 'events'].includes(tab)) {
                            let cols = 1;
                            if (vw >= 1280) cols = 3;
                            else if (vw >= 640) cols = 2;
                            if (!rowH) rowH = tab === 'events' ? 140 : 118;
                            const rows = Math.max(2, Math.floor(avail / rowH));
                            per = rows * cols;
                        } else {
                            if (!rowH) rowH = 48;
                            per = Math.floor(avail / rowH);
                        }
                        const minP = 8;
                        const maxP = 60;
                        per = Math.max(minP, Math.min(maxP, per || minP));
                        if (this.cur === 'team') {
                            per = Math.max(per, Math.floor(avail / 180) * Math.max(1, Math.floor((vw - 80) / 320)));
                            per = Math.min(Math.max(12, per), 60);
                        }
                        if (per !== this.perPage) {
                            this.perPage = per;
                        }
                        const maxPage = Math.max(1, Math.ceil(((this.filteredList && this.filteredList.length) || 0) / Math.max(1, this.perPage)));
                        if (this.page > maxPage) this.page = maxPage;
                        if (this.page < 1) this.page = 1;
                    } catch (e) {
                        if (!this.perPage || this.perPage < 8) this.perPage = 16;
                    }
                },
                bindPerPageAuto() {
                    if (this._perPageBound) return;
                    this._perPageBound = true;
                    const run = () => this.recalculatePerPage();
                    run();
                    requestAnimationFrame(() => {
                        run();
                        setTimeout(run, 120);
                        setTimeout(run, 400);
                    });
                    window.addEventListener('resize', () => {
                        clearTimeout(this._resizeTimer);
                        this._resizeTimer = setTimeout(run, 120);
                    });
                    window.addEventListener('orientationchange', () => setTimeout(run, 250));
                    if (this.$nextTick) this.$nextTick(() => { run(); setTimeout(run, 200); });
                },
                get counts() {
                    const c = {};
                    this.navGroups.forEach(g => g.items.forEach(n => {
                        const d = this.data[n.id];
                        c[n.id] = Array.isArray(d) ? d.length : 0;
                    }));
                    const stc = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.statusProjectCount) || 0;
                    if (stc > 0) c.status = stc;
                    return c;
                },
                getFileIcon(type) {
                    const t = String(type || '').toLowerCase();
                    if (t.includes('pdf')) return 'fa-solid fa-file-pdf';
                    if (t.includes('doc') || t.includes('word')) return 'fa-solid fa-file-word';
                    if (t.includes('xls') || t.includes('sheet') || t.includes('csv')) return 'fa-solid fa-file-excel';
                    if (t.includes('ppt') || t.includes('powerpoint')) return 'fa-solid fa-file-powerpoint';
                    if (t.includes('jpg') || t.includes('png') || t.includes('jpeg') || t.includes('svg') || t.includes('webp') || t.includes('gif')) return 'fa-solid fa-file-image';
                    if (t.includes('youtube') || t.includes('youtu.be')) return 'fa-brands fa-youtube';
                    if (t.includes('http') || t.includes('www.') || t.includes('link')) return 'fa-solid fa-link';
                    if (t.includes('zip') || t.includes('rar') || t.includes('7z')) return 'fa-solid fa-file-zipper';
                    if (t.includes('mp4') || t.includes('mov') || t.includes('video')) return 'fa-solid fa-file-video';
                    return 'fa-solid fa-file';
                },
                getFileIconColor(type) {
                    const t = String(type).toLowerCase();
                    if(t.includes('pdf')) return 'bg-red-50 text-red-500 border border-red-100';
                    if(t.includes('doc') || t.includes('word')) return 'bg-blue-50 text-blue-500 border border-blue-100';
                    if(t.includes('xls') || t.includes('sheet')) return 'bg-emerald-50 text-emerald-600 border border-emerald-100';
                    if(t.includes('ppt')) return 'bg-orange-50 text-orange-600 border border-orange-100';
                    if(t.includes('jpg') || t.includes('png') || t.includes('jpeg')) return 'bg-purple-50 text-purple-500 border border-purple-100';
                    if(t.includes('http') || t.includes('url')) return 'bg-slate-100 text-slate-600 border border-slate-200';
                    return 'bg-slate-50 text-slate-500 border border-slate-200';
                },
                get isPreviewImage() {
                    if (!this.docPreviewUrl) return false;
                    return /\.(jpg|jpeg|png|webp|gif|avif|svg)(\?.*)?$/i.test(this.docPreviewUrl);
                },
                youTubeEmbed(url) {
                    if (!url) return '';
                    const m = String(url).match(
                        /(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/
                    );
                    if (!m) return '';
                    return 'https://www.youtube-nocookie.com/embed/' + m[1];
                },
                previewDoc(doc) {
                    if (!doc) return;
                    const ext = doc.external_url ? String(doc.external_url).trim() : '';
                    if (ext) {
                        const yt = this.youTubeEmbed ? this.youTubeEmbed(ext) : null;
                        if (yt) { this.docPreviewUrl = yt; return; }
                        this.docPreviewUrl = ext;
                        return;
                    }
                    const file = doc.doc_file || doc.file || doc.filename || '';
                    let url = file ? ('docs/' + String(file).replace(/^\/+/, '')) : '';
                    if (!url && doc.slug) url = 'docs/' + doc.slug;
                    if (!url) {
                        alert('No file available to preview.');
                        return;
                    }
                    this.docPreviewUrl = url;
                },
                async postReq(a, p, reload=true) {
                    try {
                        const r = await fetch('index.php', {
                            method:'POST',
                            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf || window.CSRF_TOKEN || ''},
                            body:JSON.stringify({action:a, ...p})
                        });
                        const text = await r.text();
                        let j;
                        try {
                            j = JSON.parse(text);
                        } catch (parseErr) {
                            const snip = (text || '').replace(/\s+/g, ' ').slice(0, 160);
                            throw new Error('Server did not return JSON (HTTP ' + r.status + '). ' + snip);
                        }
                        if (!r.ok && j.status !== 'success') {
                            throw new Error(j.message || ('HTTP Error ' + r.status));
                        }
                        if (reload && j.status === 'success') location.reload();
                        return j;
                    } catch(error) {
                        alert('Request failed: ' + (error.message || error));
                        return { status: 'error', message: error.message };
                    }
                },
                async logout() {
                    try {
                        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                            navigator.serviceWorker.controller.postMessage('clearAll');
                        }
                        if (window.caches) {
                            const keys = await caches.keys();
                            await Promise.all(keys.map(k => caches.delete(k)));
                        }
                    } catch (e) {  }
                    this.postReq('logout', {});
                },
                deleteItem(id, ns) { if(confirm('Retire this record permanently from the enterprise registry? This action cannot be reversed.')) this.postReq('delete', {id, ns}); },
                async runOptimise() {
                    if (!confirm(
                        'Run the System Optimizer?\n\n' +
                        '\u2022 Normalises schema, slugs, phones and names\n' +
                        '\u2022 Skips team members with a locked slug\n' +
                        '\u2022 Clears expired sessions and trims logs\n\n' +
                        'Make sure you have a recent backup before continuing.'
                    )) return;
                    this.optLoading = true;
                    this.optLogs = ['Starting…'];
                    try {
                        const csrf = (window.APP && window.APP.csrf) || (window.APP_CSRF) || '';
                        const res = await fetch('index.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': csrf
                            },
                            body: JSON.stringify({ action: 'optimise', csrf_token: csrf, csrf: csrf })
                        });
                        const raw = await res.text();
                        let data = null;
                        try { data = JSON.parse(raw); } catch (parseErr) {
                            this.optLoading = false;
                            this.optLogs = [
                                '⚠️ Non-JSON response (HTTP ' + res.status + ')',
                                (raw || '').slice(0, 400)
                            ];
                            return;
                        }
                        this.optLoading = false;
                        if (data && (data.status === 'success' || data.status === 'ok')) {
                            const logs = data.logs;
                            this.optLogs = Array.isArray(logs) ? logs : (logs ? Object.values(logs) : [data.message || 'Done']);
                        } else {
                            this.optLogs = [
                                '⚠️ ' + ((data && (data.message || data.error)) || ('HTTP ' + res.status)),
                            ].concat(Array.isArray(data && data.logs) ? data.logs : []);
                        }
                    } catch (e) {
                        this.optLoading = false;
                        this.optLogs = ['⚠️ Error: ' + (e && e.message ? e.message : e)];
                    }
                },
                async exportExcel() {
                    const tab = this.cur || 'data';
                    const list = Array.isArray(this.filteredList) ? this.filteredList : [];
                    if (!list.length) { alert('No data to export on this tab.'); return; }
                    const maps = {
                        team: ['name','slug','phone','email','designation_name','department_name','location_name','house_no','house_number','gotra','dob','blood_group','gender'],
                        bank: ['holder_name','bank_name','branch','acc_no','ifsc','upi_id','slug'],
                        cartags: ['tag_id','registration_number','plate','make_model','colour','vehicle_class','owner_name','member_id'],
                        docs: ['name','title','file_type','version','size','updated_at','slug'],
                        events: ['name','date','location','description','is_virtual'],
                        locations: ['name','address','city','state','pincode','map_url'],
                        statutory: ['company_name','pan','cin','gst','rera','msme','tan','address'],
                    };
                    const cols = maps[tab] || null;
                    const rows = list.map(item => {
                        let row = {};
                        const keys = cols || Object.keys(item);
                        keys.forEach(k => {
                            if (item[k] === undefined || item[k] === null) return;
                            if (k === '_photoPreview' || k === '__search') return;
                            if (typeof item[k] === 'object') return;
                            row[String(k).toUpperCase()] = item[k];
                        });
                        if (cols) {
                            Object.keys(item).forEach(k => {
                                if (cols.includes(k)) return;
                                if (k === '_photoPreview' || k === '__search') return;
                                if (typeof item[k] === 'object') return;
                                row[String(k).toUpperCase()] = item[k];
                            });
                        }
                        return row;
                    });
                    await window.__loadXlsx(); const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.json_to_sheet(rows);
                    XLSX.utils.book_append_sheet(wb, ws, String(tab).slice(0, 28) || 'Export');
                    XLSX.writeFile(wb, 'Export_' + tab + '_' + Date.now() + '.xlsx');
                },
                printActiveTab() {
                    const tab = this.cur || 'team';
                    const title = (this.state && this.state.context && this.state.context.title) || tab;
                    document.body.setAttribute('data-print-tab', tab);
                    document.body.setAttribute('data-print-title', title);
                    document.body.setAttribute('data-print-date', new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata', dateStyle: 'medium', timeStyle: 'short' }));
                    if (tab === 'team') {
                        window.open('tools/print_directory.php?tab=team', '_blank', 'noopener');
                        return;
                    }
                    window.print();
                },
                importPreviewOpen: false,
                importPreviewHeaders: [],
                importPreviewRows: [],
                async importExcel(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const inputEl = e.target;
                    const name = (file.name || '').toLowerCase();
                    if (!/\.(xlsx|xls|csv)$/.test(name)) {
                        alert('Please choose an .xlsx, .xls, or .csv file.');
                        inputEl.value = '';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = async (evt) => {
                        try {
                            await window.__loadXlsx();
                            if (!window.XLSX) throw new Error('Spreadsheet library failed to load (SheetJS). Check network / CSP.');
                            const data = new Uint8Array(evt.target.result);
                            const wb = XLSX.read(data, { type: 'array' });
                            const sheet = wb.Sheets[wb.SheetNames[0]];
                            const rawData = XLSX.utils.sheet_to_json(sheet, { defval: '' });
                            if (!rawData.length) {
                                alert('The spreadsheet has no data rows.');
                                return;
                            }
                            const parsedData = rawData.map(row => {
                                const nr = {};
                                for (const k in row) {
                                    if (!Object.prototype.hasOwnProperty.call(row, k)) continue;
                                    nr[String(k).trim()] = row[k] == null ? '' : String(row[k]).trim();
                                }
                                return nr;
                            });
                            const headerSet = new Set();
                            parsedData.forEach(r => Object.keys(r).forEach(k => headerSet.add(k)));
                            this.importPreviewHeaders = Array.from(headerSet).slice(0, 6);
                            this.importPreviewRows = parsedData.map(row => ({ _selected: true, _row: row }));
                            this.importPreviewOpen = true;
                        } catch (err) {
                            alert('Error parsing file: ' + (err.message || err));
                        } finally {
                            inputEl.value = '';
                        }
                    };
                    reader.onerror = () => alert('Could not read the selected file.');
                    reader.readAsArrayBuffer(file);
                },
                get importSelectedCount() {
                    return this.importPreviewRows.filter(r => r._selected).length;
                },
                selectAllImportRows() {
                    this.importPreviewRows.forEach(r => { r._selected = true; });
                },
                unselectAllImportRows() {
                    this.importPreviewRows.forEach(r => { r._selected = false; });
                },
                cancelImportPreview() {
                    this.importPreviewOpen = false;
                    this.importPreviewRows = [];
                    this.importPreviewHeaders = [];
                },
                async confirmImportSelected() {
                    const chosen = this.importPreviewRows.filter(r => r._selected).map(r => r._row);
                    if (!chosen.length) {
                        alert('No rows selected. Check at least one row, or Select All.');
                        return;
                    }
                    let ns = String(this.cur || '').toLowerCase();
                    const sample = chosen[0] || {};
                    const keys = Object.keys(sample).join(' ').toLowerCase();
                    const looksBank = /bank_name|holder_name|acc_no|ifsc|upi_id|account/.test(keys);
                    if (looksBank) ns = 'bank';
                    if (!ns || ns === 'opt' || ns === 'monitor' || ns === 'tenants' || ns === 'access') {
                        alert('Open the Bank (or Team) tab first, then import.');
                        return;
                    }
                    const res = await this.postReq('import', { ns: ns, data: chosen }, false);
                    if (res && res.status === 'success') {
                        alert(res.message || ('Imported into ' + ns + ': ' + (res.inserted || 0) + ' new, ' + (res.updated || 0) + ' updated.'));
                        this.cancelImportPreview();
                        location.reload();
                    } else {
                        alert('Import failed: ' + (res && res.message ? res.message : 'Unspecified system fault.'));
                    }
                },
                async downloadImportTemplate() {
                    try {
                        const res = await this.postReq('import_template', { ns: this.cur }, false);
                        if (!res || res.status !== 'success' || !res.rows) {
                            alert('Could not build template: ' + (res && res.message ? res.message : 'error'));
                            return;
                        }
                        await window.__loadXlsx();
                        const wb = XLSX.utils.book_new();
                        const ws = XLSX.utils.json_to_sheet(res.rows, { header: res.headers });
                        XLSX.utils.book_append_sheet(wb, ws, 'Template');
                        XLSX.writeFile(wb, 'import_template_' + (this.cur || 'data') + '.xlsx');
                    } catch (err) {
                        alert('Template download failed: ' + (err.message || err));
                    }
                },
                shareItem(type, item) {
                    const eSep = '\n\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n';
                    const v = (val) => (val && String(val).trim() !== '' && val !== '-') ? String(val).trim() : null;
                    let text = '';
                    if (type === 'team') {
                        const name    = v(item.name)   || 'Team Member';
                        const desig   = v(item.designation_name);
                        const dept    = v(item.department_name);
                        const phone   = v(item.phone   || item.mobile);
                        const email   = v(item.email   || item.email_address);
                        const loc     = v(window.fullAddress(item));
                        const cardUrl = window.location.origin + '/?card=business&slug=' + encodeURIComponent(item.slug || item.id);
                        const rule    = '\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500';
                        text  = '*' + name + '*\n';
                        if (desig || dept) text += [desig, dept].filter(Boolean).join(' \u00B7 ') + '\n';
                        text += rule + '\n';
                        if (phone) text += phone + '\n';
                        if (email) text += email + '\n';
                        if (loc)   text += loc    + '\n';
                        text += rule + '\n';
                        text += 'View full profile\n' + cardUrl;
                    } else if (type === 'bank') {
                        const holder = v(item.holder_name || item.account_holder);
                        const bank   = v(item.bank_name || item.bank || item.name);
                        const branch = v(item.branch);
                        const accNo  = v(item.acc_no || item.account_no || item.account_number);
                        const ifsc   = v(item.ifsc);
                        const upi    = v(item.upi_id || item.upi);
                        text  = '*Bank Details*';
                        if (holder) text += '\n_' + holder + '_';
                        text += eSep;
                        if (bank)   text += 'Bank:    ' + bank   + '\n';
                        if (branch) text += 'Branch:  ' + branch + '\n';
                        if (accNo)  text += 'A/C No:  ' + accNo  + '\n';
                        if (ifsc)   text += 'IFSC:    ' + ifsc   + '\n';
                        if (upi)    text += 'UPI:     ' + upi    + '\n';
                        text = text.replace(/\n$/, '') + eSep.trimEnd();
                        this._shareBankQr(text, upi, holder, item.qr_image || '');
                        return;
} else if (type === 'docs') {
                        const name    = v(item.name || item.title) || 'Document';
                        const ver     = v(item.version);
                        const ftype   = v(item.file_type);
                        const docLink = item.external_url ? item.external_url : (window.location.origin + '/docs/' + (item.doc_file || item.slug));
                        const meta    = [ver ? 'v' + ver : null, ftype].filter(Boolean).join(' \u00B7 ');
                        text  = '*' + name + '*\n';
                        if (meta) text += '_' + meta + '_\n';
                        text += '\n' + docLink;
                    } else if (type === 'events') {
                        const name   = v(item.name) || 'Event';
                        const loc    = v(item.location);
                        const isUrl  = loc && (loc.startsWith('http://') || loc.startsWith('https://'));
                        const desc   = v(item.description);
                        let dt = '';
                        if (item.date) {
                            const d = new Date(item.date);
                            dt = d.toLocaleDateString('en-IN', {weekday:'short', day:'numeric', month:'short', year:'numeric'})
                               + ' \u00B7 '
                               + d.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', hour12:true}).toUpperCase();
                        }
                        text  = '*' + name + '*\n';
                        if (dt)              text += dt  + '\n';
                        if (loc && !isUrl)   text += loc + '\n';
                        if (item.is_virtual) text += '_Online / Virtual_\n';
                        if (desc)            text += eSep + desc.trim() + eSep.trimEnd();
                        if (item.url)        text += '\n' + item.url;
                        if (loc && isUrl)    text += '\n' + loc;
                    } else if (type === 'locations') {
                        const name     = v(item.name) || 'Location';
                        const address  = v(item.address);
                        const city     = v(item.city);
                        const state    = v(item.state);
                        const pincode  = v(item.pincode);
                        const mapUrl   = v(item.map_url);
                        const cityLine = [city, state, pincode ? 'PIN ' + pincode : null].filter(Boolean).join(', ');
                        text = '*' + name + '*\n';
                        if (address)  text += address + '\n';
                        if (cityLine) text += cityLine + '\n';
                        if (mapUrl)   text += '\n' + mapUrl;
                    } else if (type === 'statutory') {
                        const co = v(item.company_name) || 'Company';
                        text = '*' + co + '*' + eSep;
                        const rows = [
                            ['PAN',                   item.pan],
                            ['CIN',                   item.cin],
                            ['GSTIN',                 item.gst || item.gstin],
                            ['TAN',                   item.tan],
                            ['LEI',                   item.lei],
                            ['ROC Code',              item.roc_code],
                            ['Date of Incorporation', item.date_of_incorporation],
                            ['RERA',                  item.rera],
                            ['MSME / Udyam',          item.msme || item.udyam],
                            ['DPIIT Start-up',        item.dpiit_startup || item.dpiit],
                            ['ESIC',                  item.esic],
                            ['Establishment Code (PF)', item.pf_code || item.epf],
                            ['ISIN (NSDL)',            item.isin],
                            ['Demat DP-Client ID',     item.demat_id || item.demat],
                            ['Bank Account',          item.bank_account || item.bank],
                            ['Telephone',             item.phone || item.telephone],
                            ['e-Mail',                item.email],
                        ];
                        for (const [label, val] of rows) {
                            const s = v(val);
                            if (s) text += label + ':  ' + s + '\n';
                        }
                        const reg = v(item.address || item.registered_address);
                        if (reg) text += eSep + '*Reg. Office*\n' + reg + '\n';
                        const dev = v(item.development_office);
                        if (dev) text += '\n*Development Office*\n' + dev + '\n';
                        const gstOff = v(item.gst_sales_office);
                        if (gstOff) text += '\n*GST Sales Office*\n' + gstOff + '\n';
                        const reraProj = v(item.rera_project_name);
                        if (reraProj) text += '\n*RERA Project*\n' + reraProj + '\n';
                        for (const ph of ['rera_phase_1', 'rera_phase_2', 'rera_phase_3']) {
                            const pv = v(item[ph]);
                            if (pv) text += ph.replace('rera_', '').replace('_', ' ').toUpperCase() + ': ' + pv + '\n';
                        }
                        text = text.replace(/\n+$/, '');
                    } else {
                        const title = v(item.name || item.title) || 'Record';
                        const code  = v(item.code || item.id);
                        text = '*' + title + '*';
                        if (code) text += '\n_' + code + '_';
                    }
                    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
                },
                async _shareBankQr(text, upi, holder, qrImage) {
                    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
                    let qrUrl = '';
                    if (qrImage) {
                        qrUrl = (window.location.origin || '') + '/images/' + String(qrImage).replace(/^\/+/, '');
                    } else if (upi) {
                        const pay = 'upi://pay?pa=' + encodeURIComponent(upi)
                            + (holder ? '&pn=' + encodeURIComponent(holder) : '')
                            + '&cu=INR';
                        qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&margin=16&ecc=M&data=' + encodeURIComponent(pay);
                    }
                    if (!qrUrl) return;
                    try {
                        window.open(qrUrl, '_blank', 'noopener');
                    } catch (e2) {}
                },
            }));
        });
