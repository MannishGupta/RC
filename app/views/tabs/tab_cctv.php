<?php
// dbd/tab_cctv.php — Version: 260916.14
//
// Replaces the standalone /cctv/index.html portal.
//
// WHY THIS MOVED INTO THE DASHBOARD:
// The supplied cctv/index.html described itself as a "secure live camera
// portal" but had NO authentication of any kind — it was a static page
// printing live DVR usernames and passwords in plain text, with copy
// buttons, OG share tags, and JSON-LD (i.e. built to be shared and indexed).
// Its only "security" was a console.warn(). Anyone who learned or guessed
// the URL got working CCTV credentials. The companion cctv/pwd.txt was the
// same credentials again as a plain .txt in a web-served folder.
//
// Rendering them here instead means they inherit the dashboard's existing
// session auth, and the values live in data/cctv.json — a path already
// protected by web.config (hiddenSegments "data" + .json denied).
//
// ACTION REQUIRED AFTER DEPLOY:
//   1. DELETE /cctv/pwd.txt and /cctv/index.html* from the server.
//   2. ROTATE both DVR passwords — they have been sitting in a publicly
//      reachable file and in a .zip, so treat them as compromised.
//   3. Re-enter the new passwords via this tab.
if (!defined('BASE_PATH')) exit;

$cctvFeeds = class_exists('AppDB') ? (AppDB::read('cctv') ?: []) : [];
?>

<div class="w-full flex flex-col space-y-4"
     x-data="cctvTab()">

    <!-- Security banner -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
        <i class="fa-solid fa-shield-halved text-amber-500 mt-0.5 shrink-0"></i>
        <div class="text-xs text-amber-800 leading-relaxed">
            <span class="font-bold">Restricted credentials.</span>
            These sign-ins are visible only to logged-in dashboard users. Don't screenshot or
            forward this page. If a password is ever pasted into chat, email or a shared doc,
            rotate it on the DVR and update it here.
        </div>
    </div>

    <!-- Empty state -->
    <template x-if="feeds.length === 0">
        <div class="flex flex-col items-center justify-center h-64 text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl w-full bg-slate-50/50">
            <i class="fa-solid fa-video text-4xl mb-4 text-slate-300"></i>
            <p class="text-sm font-semibold text-slate-600">No camera feeds configured.</p>
            <?php if ($isAdmin): ?>
            <button @click="openNew()" class="mt-4 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg transition">
                <i class="fa-solid fa-plus mr-1.5"></i> Add Camera Feed
            </button>
            <?php endif; ?>
        </div>
    </template>

    <!-- Feed cards -->
    <template x-if="feeds.length > 0">
        <div class="space-y-4">

            <?php if ($isAdmin): ?>
            <div class="flex justify-end">
                <button @click="openNew()" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg transition shadow-sm">
                    <i class="fa-solid fa-plus mr-1.5"></i> Add Camera Feed
                </button>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <template x-for="f in feeds" :key="f.id">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden group hover:border-blue-300 hover:shadow-md transition-all flex flex-col">

                        <div class="bg-gradient-to-r from-slate-800 to-slate-700 px-5 py-4 flex items-start justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                    <h3 class="font-black text-white text-base leading-tight truncate" x-text="f.name || 'Unnamed Camera'"></h3>
                                </div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1" x-text="f.location || 'Location not set'"></p>
                            </div>
                            <?php if ($isAdmin): ?>
                            <div class="flex gap-1.5 shrink-0 opacity-0 group-hover:opacity-100 transition">
                                <button @click.stop="editFeed(f)" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white/80 hover:text-white flex items-center justify-center transition text-xs" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button @click.stop="removeFeed(f)" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-red-500/60 text-white/80 hover:text-white flex items-center justify-center transition text-xs" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-5 space-y-3 flex-1">
                            <!-- URL -->
                            <div>
                                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Login URL</div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs text-slate-700 truncate flex-1" x-text="f.url || '—'"></span>
                                    <button @click="copy(f.url, 'URL')" x-show="f.url"
                                            class="shrink-0 px-2 py-1 text-[10px] font-bold rounded border border-slate-200 text-slate-500 hover:text-blue-600 hover:border-blue-300 transition">Copy</button>
                                </div>
                            </div>

                            <!-- Username -->
                            <div>
                                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Username</div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs text-slate-700 truncate flex-1" x-text="f.username || '—'"></span>
                                    <button @click="copy(f.username, 'Username')" x-show="f.username"
                                            class="shrink-0 px-2 py-1 text-[10px] font-bold rounded border border-slate-200 text-slate-500 hover:text-blue-600 hover:border-blue-300 transition">Copy</button>
                                </div>
                            </div>

                            <!-- Password (masked until revealed) -->
                            <div>
                                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Password</div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs text-slate-700 truncate flex-1"
                                          x-text="shown[f.id] ? (f.password || '—') : '••••••••••'"></span>
                                    <button @click="shown[f.id] = !shown[f.id]" x-show="f.password"
                                            class="shrink-0 px-2 py-1 text-[10px] font-bold rounded border border-slate-200 text-slate-500 hover:text-slate-800 transition"
                                            x-text="shown[f.id] ? 'Hide' : 'Show'"></button>
                                    <button @click="copy(f.password, 'Password')" x-show="f.password"
                                            class="shrink-0 px-2 py-1 text-[10px] font-bold rounded border border-slate-200 text-slate-500 hover:text-blue-600 hover:border-blue-300 transition">Copy</button>
                                </div>
                            </div>

                            <!-- RTSP: masked by default because the URL normally embeds
                                 the camera credentials inline. -->
                            <div x-show="f.rtsp">
                                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">RTSP Stream</div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs text-slate-700 truncate flex-1"
                                          x-text="shownRtsp[f.id] ? f.rtsp : maskRtsp(f.rtsp)"></span>
                                    <button @click="shownRtsp[f.id] = !shownRtsp[f.id]"
                                            class="shrink-0 px-2 py-1 text-[10px] font-bold rounded border border-slate-200 text-slate-500 hover:text-slate-800 transition"
                                            x-text="shownRtsp[f.id] ? 'Hide' : 'Show'"></button>
                                    <button @click="copy(f.rtsp, 'RTSP URL')"
                                            class="shrink-0 px-2 py-1 text-[10px] font-bold rounded border border-slate-200 text-slate-500 hover:text-blue-600 hover:border-blue-300 transition">Copy</button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">Open in VLC: Media &rarr; Open Network Stream</p>
                            </div>

                            <p x-show="f.notes" class="text-[11px] text-slate-500 leading-relaxed pt-2 border-t border-slate-100" x-text="f.notes"></p>
                        </div>

                        <div class="px-5 pb-5">
                            <a :href="normalizeUrl(f.url)" x-show="f.url" target="_blank" rel="noopener noreferrer"
                               class="flex items-center justify-center gap-2 w-full py-2.5 bg-slate-900 hover:bg-blue-600 text-white text-xs font-bold rounded-lg transition">
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                Open Camera Portal
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- Toast -->
    <div x-show="toast" x-transition x-cloak
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-slate-900 text-white text-xs font-bold px-4 py-2.5 rounded-lg shadow-xl"
         x-text="toast" style="display:none"></div>

    <?php if ($isAdmin): ?>
    <!-- Editor modal -->
    <div x-show="modalOpen" x-cloak style="display:none" role="dialog" aria-modal="true" data-rc-dialog="cctv-editor"
         class="fixed inset-0 z-[100] bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4"
         @click.self="modalOpen = false" @keydown.escape.window="modalOpen = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-black text-slate-800" x-text="form.id ? 'Edit Camera Feed' : 'New Camera Feed'"></h3>
                <button @click="modalOpen=false" class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-6 space-y-3">
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Camera Name</label>
                    <input x-model="form.name" placeholder="e.g. The Rivulet: Driveway"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Location / Label</label>
                    <input x-model="form.location" placeholder="e.g. Camera 01"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Login URL</label>
                    <input x-model="form.url" placeholder="https://cms.example.co.in"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Username</label>
                    <input x-model="form.username" autocomplete="off"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Password</label>
                    <input x-model="form.password" type="text" autocomplete="off"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">RTSP Stream (optional)</label>
                    <input x-model="form.rtsp" placeholder="rtsp://admin:pass@192.168.0.100:554/ch0_0.264"
                           autocomplete="off" spellcheck="false"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono outline-none focus:border-blue-500">
                    <p class="text-[10px] text-slate-400 mt-1 leading-snug">
                        Browsers cannot play RTSP directly &mdash; no browser has supported it for years.
                        This is stored so it can be copied into VLC, a DVR client or an NVR.
                        Note the URL usually embeds the camera password, so treat it like one.
                    </p>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Notes (optional)</label>
                    <textarea x-model="form.notes" rows="2"
                              class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                <button @click="modalOpen=false" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-800 transition">Cancel</button>
                <button @click="saveFeed()" :disabled="saving"
                        class="px-5 py-2 bg-slate-900 hover:bg-blue-600 disabled:opacity-60 text-white text-sm font-bold rounded-lg transition">
                    <span x-text="saving ? 'Saving…' : 'Save'"></span>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function cctvTab() {
    return {
        feeds: <?= json_encode(array_values($cctvFeeds), JSON_UNESCAPED_SLASHES) ?>,
        shown: {},
        shownRtsp: {},

        // Hide the inline credentials in rtsp://user:pass@host/... by default.
        maskRtsp(u) {
            if (!u) return '';
            return String(u).replace(/\/\/([^:@/]+):([^@/]+)@/, '//$1:\u2022\u2022\u2022\u2022@');
        },

        toast: '',
        modalOpen: false,
        saving: false,
        form: { id:'', name:'', location:'', url:'', username:'', password:'', rtsp:'', notes:'' },

        normalizeUrl(u) {
            u = (u || '').trim();
            if (!u) return '#';
            // Only http(s) may reach an href — never a javascript:/data: value.
            if (!/^[a-z][a-z0-9+.-]*:/i.test(u)) u = 'https://' + u.replace(/^\/+/, '');
            return /^https?:\/\//i.test(u) ? u : '#';
        },

        async copy(val, label) {
            if (!val) return;
            try {
                await navigator.clipboard.writeText(val);
                this.flash(label + ' copied');
            } catch (e) {
                this.flash('Copy failed — select manually');
            }
        },

        flash(msg) {
            this.toast = msg;
            clearTimeout(this._t);
            this._t = setTimeout(() => this.toast = '', 1800);
        },

        openNew() {
            this.form = { id:'', name:'', location:'', url:'', username:'', password:'', rtsp:'', notes:'' };
            this.modalOpen = true;
        },

        editFeed(f) {
            this.form = {
                id: f.id || '', name: f.name || '', location: f.location || '',
                url: f.url || '', username: f.username || '',
                password: f.password || '', rtsp: f.rtsp || '', notes: f.notes || ''
            };
            this.modalOpen = true;
        },

        async saveFeed() {
            if (!this.form.name.trim()) { alert('Camera name is required.'); return; }
            this.saving = true;
            const isEdit = !!this.form.id;
            const payload = { ...this.form };
            if (!isEdit) delete payload.id;
            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                body: JSON.stringify({
                    action: 'save', ns: 'cctv',
                    id: this.form.id, __edit_mode: isEdit,
                    payload: payload
                })
            }).then(r => r.json()).catch(() => ({ status: 'error', message: 'Network error' }));
            this.saving = false;
            if (res.status === 'success') location.reload();
            else alert('Save failed: ' + (res.message || 'Unknown error'));
        },

        async removeFeed(f) {
            if (!confirm('Delete camera "' + (f.name || f.id) + '"?')) return;
            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                body: JSON.stringify({ action: 'delete', ns: 'cctv', id: f.id })
            }).then(r => r.json()).catch(() => ({ status: 'error' }));
            if (res.status === 'success') location.reload();
            else alert('Delete failed.');
        }
    };
}
</script>
