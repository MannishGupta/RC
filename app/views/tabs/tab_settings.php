<?php
// app/views/tabs/tab_settings.php — Version: 260916.14
//
// Single place to configure every optional integration: Google Wallet,
// Vehicle Registry lookup, WhatsApp Business API, SMTP, Apple Wallet
// (reference only). Each section maps 1:1 to one config file in data/ —
// this page edits those files through index.php's settings_save action; it
// does not introduce a second, parallel config store.
//
// Admin-only. Every field here is either a low-value setting (host, port) or
// a genuine secret (API key, password) — nothing a non-admin should see.
if (!defined('BASE_PATH') || empty($isAdmin)) { return; }
?>

<div class="w-full flex flex-col space-y-4 max-w-3xl" x-data="settingsTab()" x-init="loadAll()">

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
        <i class="fa-solid fa-shield-halved text-amber-500 mt-0.5 shrink-0"></i>
        <div class="text-xs text-amber-800 leading-relaxed">
            <span class="font-bold">Secrets are never shown once saved.</span>
            A password or API key field left blank on save keeps the existing value —
            it does not clear it. To replace a secret, type the new one; to remove it
            entirely, disable the section below.
        </div>
    </div>

    <!-- ════════════════ Google Wallet ════════════════ -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <header class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center gap-3">
            <div class="w-9 h-9 bg-white text-slate-600 rounded-lg flex items-center justify-center border border-slate-200 shrink-0"><i class="fa-brands fa-google"></i></div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-slate-800">Google Wallet</h3>
                <p class="text-[11px] text-slate-400">"Add to Google Wallet" on the digital business card</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" x-model="gw.enabled" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition-colors"></div>
                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
            </label>
        </header>
        <div class="p-5 space-y-3" x-show="gw.enabled" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Issuer ID</label>
                    <input x-model="gw.issuer_id" placeholder="3388000000012345678" class="set-input">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Class ID</label>
                    <input x-model="gw.class_id" placeholder="digital_business_card" class="set-input">
                </div>
            </div>
            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Service Account Key Filename</label>
                <input x-model="gw.service_account_json" placeholder="google-wallet-sa.json" class="set-input">
                <p class="text-[10px] text-slate-400 mt-1">
                    Upload the JSON key file to <span class="font-mono">data/</span> via FTP first — this field is just the filename, the key itself is not typed here.
                </p>
            </div>
            <p class="text-[10px] text-slate-400">
                Setup: Cloud Console → enable Wallet API → register as Issuer (free) → create a service account →
                one Generic Class for this app. Full steps in <span class="font-mono">data/google_wallet_config.php</span>.
            </p>
        </div>
        <div class="px-5 py-3 border-t border-slate-100 flex justify-end">
            <button @click="save('google_wallet', gw)" :disabled="busy==='google_wallet'" class="set-save">
                <span x-text="busy==='google_wallet' ? 'Saving…' : 'Save'"></span>
            </button>
        </div>
    </section>

    <!-- ════════════════ Vehicle Registry ════════════════ -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <header class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center gap-3">
            <div class="w-9 h-9 bg-white text-slate-600 rounded-lg flex items-center justify-center border border-slate-200 shrink-0"><i class="fa-solid fa-car"></i></div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-slate-800">Vehicle Registry Lookup</h3>
                <p class="text-[11px] text-slate-400">PUC / Insurance / RC status on Vehicle Tags</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" x-model="vr.enabled" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition-colors"></div>
                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
            </label>
        </header>
        <div class="p-5 space-y-3" x-show="vr.enabled" x-cloak>
            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Provider</label>
                <select x-model="vr.provider" class="set-input">
                    <option value="commercial">Commercial gateway (Surepass / Attestr / Signzy…)</option>
                    <option value="apisetu">API Setu (free, gated by application)</option>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Endpoint URL</label>
                    <input x-model="vr.endpoint" placeholder="https://api.provider.com/v1/vehicle/rc-verify" class="set-input">
                </div>
                <div x-show="vr.provider === 'commercial'">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Request Field Name</label>
                    <input x-model="vr.request_field" placeholder="vehicle_number" class="set-input">
                </div>
                <div x-show="vr.provider === 'apisetu'">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Client ID</label>
                    <input x-model="vr.client_id" class="set-input">
                </div>
                <div x-show="vr.provider === 'apisetu'">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Token URL</label>
                    <input x-model="vr.token_url" placeholder="https://apisetu.gov.in/oauth/token" class="set-input">
                </div>
            </div>
            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    API Key <span x-show="vr._has_api_key" class="text-emerald-600 font-normal normal-case">· currently set</span>
                </label>
                <input x-model="vr.api_key" type="password" autocomplete="new-password"
                       :placeholder="vr._has_api_key ? '•••••••• (leave blank to keep current)' : 'Paste your key'" class="set-input">
            </div>
        </div>
        <div class="px-5 py-3 border-t border-slate-100 flex justify-end">
            <button @click="save('vehicle_registry', vr)" :disabled="busy==='vehicle_registry'" class="set-save">
                <span x-text="busy==='vehicle_registry' ? 'Saving…' : 'Save'"></span>
            </button>
        </div>
    </section>

    <!-- ════════════════ WhatsApp Business API ════════════════ -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <header class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center gap-3">
            <div class="w-9 h-9 bg-white text-emerald-600 rounded-lg flex items-center justify-center border border-slate-200 shrink-0"><i class="fa-brands fa-whatsapp"></i></div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-slate-800">WhatsApp Business API</h3>
                <p class="text-[11px] text-slate-400">For automated, server-sent alerts — not the Click-to-Chat links used elsewhere, which need no setup</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" x-model="wa.enabled" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition-colors"></div>
                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
            </label>
        </header>
        <div class="p-5 space-y-3" x-show="wa.enabled" x-cloak>
            <div class="bg-blue-50 border border-blue-100 rounded-lg px-3 py-2.5 text-[11px] text-blue-800 leading-relaxed">
                Meta requires every business-initiated message to use a pre-approved template —
                free-form text is rejected outside a 24-hour reply window. Get API access and a
                template approved at business.facebook.com before this is usable.
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Phone Number ID</label>
                    <input x-model="wa.phone_number_id" class="set-input">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Business Account ID</label>
                    <input x-model="wa.business_account_id" class="set-input">
                </div>
            </div>
            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    Access Token <span x-show="wa._has_access_token" class="text-emerald-600 font-normal normal-case">· currently set</span>
                </label>
                <input x-model="wa.access_token" type="password" autocomplete="new-password"
                       :placeholder="wa._has_access_token ? '•••••••• (leave blank to keep current)' : 'Permanent System User token'" class="set-input">
            </div>
        </div>
        <div class="px-5 py-3 border-t border-slate-100 flex justify-end">
            <button @click="save('whatsapp', wa)" :disabled="busy==='whatsapp'" class="set-save">
                <span x-text="busy==='whatsapp' ? 'Saving…' : 'Save'"></span>
            </button>
        </div>
    </section>

    <!-- ════════════════ SMTP Email ════════════════ -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <header class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center gap-3">
            <div class="w-9 h-9 bg-white text-slate-600 rounded-lg flex items-center justify-center border border-slate-200 shrink-0"><i class="fa-solid fa-envelope"></i></div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-slate-800">Outbound Email (SMTP)</h3>
                <p class="text-[11px] text-slate-400">Lead notifications and future compliance alerts</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" x-model="sm.enabled" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition-colors"></div>
                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
            </label>
        </header>
        <div class="p-5 space-y-3" x-show="sm.enabled" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">SMTP Host</label>
                    <input x-model="sm.host" placeholder="smtp.gmail.com" class="set-input">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Port</label>
                    <input x-model.number="sm.port" type="number" placeholder="587" class="set-input">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Encryption</label>
                    <select x-model="sm.encryption" class="set-input">
                        <option value="tls">STARTTLS (587)</option>
                        <option value="ssl">SSL/TLS (465)</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Username</label>
                    <input x-model="sm.username" autocomplete="off" class="set-input">
                </div>
            </div>
            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    Password <span x-show="sm._has_password" class="text-emerald-600 font-normal normal-case">· currently set</span>
                </label>
                <input x-model="sm.password" type="password" autocomplete="new-password"
                       :placeholder="sm._has_password ? '•••••••• (leave blank to keep current)' : 'App password / SMTP password'" class="set-input">
                <p class="text-[10px] text-slate-400 mt-1">For Gmail/Workspace with 2FA on, this must be an App Password, not your normal login.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">From Email</label>
                    <input x-model="sm.from_email" type="email" class="set-input">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">From Name</label>
                    <input x-model="sm.from_name" class="set-input">
                </div>
            </div>
        </div>
        <div class="px-5 py-3 border-t border-slate-100 flex justify-end">
            <button @click="save('smtp', sm)" :disabled="busy==='smtp'" class="set-save">
                <span x-text="busy==='smtp' ? 'Saving…' : 'Save'"></span>
            </button>
        </div>
    </section>

    <!-- ════════════════ Apple Wallet (reference only) ════════════════ -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden opacity-90">
        <header class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center gap-3">
            <div class="w-9 h-9 bg-white text-slate-600 rounded-lg flex items-center justify-center border border-slate-200 shrink-0"><i class="fa-brands fa-apple"></i></div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-slate-800">Apple Wallet</h3>
                <p class="text-[11px] text-slate-400">Reference fields only — see note below</p>
            </div>
        </header>
        <div class="p-5 space-y-3">
            <div class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-[11px] text-slate-600 leading-relaxed">
                <i class="fa-solid fa-circle-info text-slate-400 mr-1"></i>
                Apple Wallet passes require a signed <span class="font-mono">.pkpass</span> file, which needs a
                Pass Type ID certificate only Apple issues — an Apple Developer Program membership,
                $99/year, with no workaround. Pass generation is not built here for that reason.
                <strong>Google Wallet above needs no paid account and works today</strong> — use it if
                Android/desktop coverage is enough for now.
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Pass Type ID</label>
                    <input x-model="aw.pass_type_id" placeholder="pass.com.yourcompany.card" class="set-input">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Apple Team ID</label>
                    <input x-model="aw.team_id" placeholder="10-character ID" class="set-input">
                </div>
            </div>
        </div>
        <div class="px-5 py-3 border-t border-slate-100 flex justify-end">
            <button @click="save('apple_wallet', aw)" :disabled="busy==='apple_wallet'" class="set-save">
                <span x-text="busy==='apple_wallet' ? 'Saving…' : 'Save'"></span>
            </button>
        </div>
    </section>

    <div id="settingsToast" x-show="toast" x-transition x-cloak style="display:none"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-slate-900 text-white text-xs font-bold px-4 py-2.5 rounded-lg shadow-xl" x-text="toast"></div>
</div>

<style>
    .set-input { width: 100%; margin-top: 4px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 11px; font-size: 13px; outline: none; }
    .set-input:focus { border-color: #3b82f6; }
    .set-save { padding: 8px 18px; background: #0f172a; color: #fff; font-size: 12px; font-weight: 700; border-radius: 8px; }
    .set-save:disabled { opacity: .6; }
</style>

<script>
function settingsTab() {
    return {
        busy: '', toast: '',
        gw: { enabled:false, issuer_id:'', class_id:'', service_account_json:'' },
        vr: { enabled:false, provider:'commercial', endpoint:'', request_field:'vehicle_number', client_id:'', token_url:'', api_key:'', _has_api_key:false },
        wa: { enabled:false, phone_number_id:'', business_account_id:'', access_token:'', _has_access_token:false },
        sm: { enabled:false, host:'', port:587, encryption:'tls', username:'', password:'', from_email:'', from_name:'', _has_password:false },
        aw: { pass_type_id:'', team_id:'' },

        async loadAll() {
            const sections = { google_wallet: 'gw', vehicle_registry: 'vr', whatsapp: 'wa', smtp: 'sm', apple_wallet: 'aw' };
            for (const [key, prop] of Object.entries(sections)) {
                const res = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                    body: JSON.stringify({ action: 'settings_get', key })
                }).then(r => r.json()).catch(() => null);
                if (res && res.status === 'success') Object.assign(this[prop], res.config);
            }
        },

        async save(key, model) {
            this.busy = key;
            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                body: JSON.stringify({ action: 'settings_save', key, payload: model })
            }).then(r => r.json()).catch(() => ({ status: 'error' }));
            this.busy = '';
            if (res.status === 'success') {
                this.flash('Saved.');
                // Never keep a just-typed secret in memory in this tab longer
                // than needed to send it — clear the field and let the
                // "currently set" indicator take over.
                if ('api_key' in model) { model.api_key = ''; model._has_api_key = true; }
                if ('access_token' in model) { model.access_token = ''; model._has_access_token = true; }
                if ('password' in model) { model.password = ''; model._has_password = true; }
            } else {
                this.flash(res.message || 'Save failed.');
            }
        },

        flash(msg) { this.toast = msg; clearTimeout(this._t); this._t = setTimeout(() => this.toast = '', 2200); }
    };
}
</script>
