<?php
// app/views/tabs/tab_stat.php — Version: 260917.07
// Statutory / company compliance cards.
// Grouped by identity → tax → labour → capital markets → addresses → RERA.
// Format hints match Indian statutory identifier standards.
if (!defined('BASE_PATH')) exit;
?>
<div class="w-full flex flex-col space-y-4">

    <template x-if="filteredList.length === 0">
        <div class="flex flex-col items-center justify-center h-64 text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl w-full bg-slate-50/50 mt-2">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                <i class="fa-solid fa-scale-balanced text-3xl text-slate-300"></i>
            </div>
            <p class="text-sm font-semibold text-slate-600">No statutory records found.</p>
            <p class="text-xs text-slate-400 mt-1">Click <span class="font-bold text-slate-500">Add New</span> to create a company profile.</p>
        </div>
    </template>

    <template x-if="filteredList.length > 0">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <template x-for="i in filteredList" :key="i.id || i.pan || i.cin">

                <div class="w-full bg-white rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden group hover:border-blue-300 hover:shadow-md transition-all duration-200 flex flex-col"
                     x-data="{ expanded: false }">

                    <div class="flex items-start justify-between p-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                        <div class="flex-1 min-w-0 pr-3">
                            <h3 class="font-black text-slate-800 text-lg leading-tight" x-text="i.company_name || 'Unnamed Entity'"></h3>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <button type="button" x-show="i.cin" @click.stop="copyToClipboard(i.cin, $event)"
                                        class="inline-flex items-center gap-1 px-2 py-1 bg-slate-100 text-slate-700 font-mono text-[10px] font-bold rounded-lg border border-slate-200 hover:bg-slate-200 transition max-w-full"
                                        title="CIN — 21 characters (e.g. U68100DL2022PLC400132)">
                                    <span class="text-slate-400 font-sans font-bold">CIN</span>
                                    <span class="truncate" x-text="i.cin"></span>
                                    <i class="fa-regular fa-copy text-[8px] opacity-50 shrink-0"></i>
                                </button>
                                <button type="button" x-show="i.pan" @click.stop="copyToClipboard(i.pan, $event)"
                                        class="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 font-mono text-[10px] font-bold rounded-lg border border-blue-100 hover:bg-blue-100 transition"
                                        title="PAN — 10 characters: 5 letters + 4 digits + 1 letter">
                                    <span class="font-sans font-bold opacity-70">PAN</span>
                                    <span x-text="i.pan"></span>
                                    <i class="fa-regular fa-copy text-[8px] opacity-50"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flex gap-1.5 shrink-0 no-print">
                            <button type="button" @click.stop="shareItem('statutory', i)"
                                    class="w-8 h-8 rounded-lg bg-white border border-slate-200 shadow-sm text-slate-400 hover:text-green-600 hover:border-green-300 flex items-center justify-center transition"
                                    title="Share statutory info">
                                <i class="fa-brands fa-whatsapp text-sm"></i>
                            </button>
                            <?php if ($isAdmin): ?>
                            <button type="button" @click.stop="window.openModalEditor(true, cur, i)"
                                    class="w-8 h-8 rounded-lg bg-white border border-slate-200 shadow-sm text-slate-400 hover:text-blue-600 hover:border-blue-300 flex items-center justify-center transition"
                                    title="Edit">
                                <i class="fa-solid fa-pen text-xs"></i>
                            </button>
                            <button type="button" @click.stop="deleteItem(i.id, cur)"
                                    class="w-8 h-8 rounded-lg bg-white border border-slate-200 shadow-sm text-slate-400 hover:text-red-600 hover:border-red-300 flex items-center justify-center transition"
                                    title="Delete">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="p-5 space-y-4 flex-1">

                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                <i class="fa-solid fa-receipt text-[9px]"></i> Tax identifiers
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="box in [
                                    {k:'GSTIN', v: i.gst || i.gstin, pattern: 'GSTIN'},
                                    {k:'TAN',   v: i.tan, pattern: 'TAN'},
                                    {k:'PAN',   v: i.pan, pattern: 'PAN'},
                                    {k:'LEI',   v: i.lei, pattern: 'LEI'},
                                ]" :key="box.k">
                                    <div class="bg-slate-50 rounded-xl border px-3 py-2.5 flex items-center justify-between gap-2"
                                         :class="idCheck(box.pattern, box.v) === true ? 'border-emerald-200' : (box.v ? 'border-rose-200' : 'border-slate-100')"
                                         :title="idHint(box.pattern)">
                                        <div class="min-w-0 flex-1">
                                            <div class="text-[9px] text-slate-400 uppercase font-bold tracking-widest flex items-center gap-1">
                                                <span x-text="box.k"></span>
                                                <i class="fa-solid text-[10px]"
                                                   :class="idCheck(box.pattern, box.v) === true ? 'fa-circle-check text-emerald-500' : (box.v ? 'fa-circle-xmark text-rose-500' : 'fa-circle text-slate-300')"
                                                   :title="idCheck(box.pattern, box.v) === true ? 'Valid format' : (box.v ? 'Invalid format' : 'Not set')"></i>
                                            </div>
                                            <div class="font-mono font-semibold text-xs truncate"
                                                 :class="box.v ? 'text-slate-700' : 'text-slate-300'"
                                                 x-text="box.v || '— not set —'"></div>
                                            <div class="text-[9px] text-slate-400 mt-0.5 truncate" x-text="idHint(box.pattern)"></div>
                                        </div>
                                        <button type="button" x-show="box.v" @click.stop="copyToClipboard(box.v, $event)"
                                                class="shrink-0 w-6 h-6 rounded-md text-slate-300 hover:bg-white hover:text-blue-600 transition flex items-center justify-center">
                                            <i class="fa-regular fa-copy text-[10px]"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                <i class="fa-solid fa-building-columns text-[9px]"></i> Corporate identity
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="box in [
                                    {k:'CIN',                   v: i.cin, hint:'21 characters (MCA)'},
                                    {k:'ROC Code',              v: i.roc_code, hint:'Registrar of Companies code'},
                                    {k:'Date of Incorporation', v: i.date_of_incorporation, hint:'DD-MM-YYYY'},
                                    {k:'MSME / Udyam',          v: i.msme, hint:'UDYAM-XX-XX-#######'},
                                    {k:'DPIIT Start-up',        v: i.dpiit_startup || i.dpiit, hint:'DPIIT recognition no. or N/A'},
                                    {k:'Bank Account',          v: i.bank_account || i.bank, hint:'Account number + bank name'},
                                ].filter(b => b.v)" :key="box.k">
                                    <div class="bg-slate-50 rounded-xl border border-slate-100 px-3 py-2.5 flex items-center justify-between gap-2"
                                         :title="box.hint">
                                        <div class="min-w-0">
                                            <div class="text-[9px] text-slate-400 uppercase font-bold tracking-widest" x-text="box.k"></div>
                                            <div class="font-mono font-semibold text-slate-700 text-xs truncate" x-text="box.v"></div>
                                        </div>
                                        <button type="button" @click.stop="copyToClipboard(box.v, $event)"
                                                class="shrink-0 w-6 h-6 rounded-md text-slate-300 hover:bg-white hover:text-blue-600 transition flex items-center justify-center">
                                            <i class="fa-regular fa-copy text-[10px]"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <template x-if="i.esic || i.pf_code">
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                    <i class="fa-solid fa-people-group text-[9px]"></i> Labour &amp; social security
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <template x-for="box in [
                                        {k:'ESIC', v: i.esic, hint:'ESIC establishment code'},
                                        {k:'PF Establishment Code', v: i.pf_code, hint:'EPFO establishment code'},
                                    ].filter(b => b.v)" :key="box.k">
                                        <div class="bg-slate-50 rounded-xl border border-slate-100 px-3 py-2.5 flex items-center justify-between gap-2"
                                             :title="box.hint">
                                            <div class="min-w-0">
                                                <div class="text-[9px] text-slate-400 uppercase font-bold tracking-widest" x-text="box.k"></div>
                                                <div class="font-mono font-semibold text-slate-700 text-xs truncate" x-text="box.v"></div>
                                            </div>
                                            <button type="button" @click.stop="copyToClipboard(box.v, $event)"
                                                    class="shrink-0 w-6 h-6 rounded-md text-slate-300 hover:bg-white hover:text-blue-600 transition flex items-center justify-center">
                                                <i class="fa-regular fa-copy text-[10px]"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="i.isin || i.demat_id">
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                    <i class="fa-solid fa-chart-line text-[9px]"></i> Capital markets
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <template x-for="box in [
                                        {k:'ISIN (NSDL)', v: i.isin, pattern:'ISIN'},
                                        {k:'Demat DP-Client ID', v: i.demat_id, pattern:null},
                                    ].filter(b => b.v)" :key="box.k">
                                        <div class="bg-slate-50 rounded-xl border px-3 py-2.5 flex items-center justify-between gap-2"
                                             :class="box.pattern && idCheck(box.pattern, box.v) === true ? 'border-emerald-200' : (box.pattern && box.v ? 'border-rose-200' : 'border-slate-100')"
                                             :title="box.pattern ? idHint(box.pattern) : ''">
                                            <div class="min-w-0">
                                                <div class="text-[9px] text-slate-400 uppercase font-bold tracking-widest flex items-center gap-1">
                                                    <span x-text="box.k"></span>
                                                    <template x-if="box.pattern">
                                                        <i class="fa-solid text-[10px]"
                                                           :class="idCheck(box.pattern, box.v) === true ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-rose-500'"></i>
                                                    </template>
                                                </div>
                                                <div class="font-mono font-semibold text-slate-700 text-xs truncate" x-text="box.v"></div>
                                            </div>
                                            <button type="button" @click.stop="copyToClipboard(box.v, $event)"
                                                    class="shrink-0 w-6 h-6 rounded-md text-slate-300 hover:bg-white hover:text-blue-600 transition flex items-center justify-center">
                                                <i class="fa-regular fa-copy text-[10px]"></i>
                                            </button>
                                        </div>
                                    </template>

                                </div>
                            </div>
                        </template>

                        <template x-if="i.phone || i.telephone || i.email || i.email_address">
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                    <i class="fa-solid fa-address-book text-[9px]"></i> Contact
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <template x-for="box in [
                                        {k:'Telephone', v: i.phone || i.telephone, hint:'10-digit Indian mobile / landline'},
                                        {k:'e-Mail',    v: i.email || i.email_address, hint:'Primary company email'},
                                    ].filter(b => b.v)" :key="box.k">
                                        <div class="bg-slate-50 rounded-xl border border-slate-100 px-3 py-2.5 flex items-center justify-between gap-2"
                                             :title="box.hint">
                                            <div class="min-w-0">
                                                <div class="text-[9px] text-slate-400 uppercase font-bold tracking-widest" x-text="box.k"></div>
                                                <div class="font-semibold text-slate-700 text-xs truncate" x-text="box.v"></div>
                                            </div>
                                            <button type="button" @click.stop="copyToClipboard(box.v, $event)"
                                                    class="shrink-0 w-6 h-6 rounded-md text-slate-300 hover:bg-white hover:text-blue-600 transition flex items-center justify-center">
                                                <i class="fa-regular fa-copy text-[10px]"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="space-y-2.5 border-t border-slate-100 pt-3">
                            <div class="flex items-start gap-2.5 text-xs">
                                <i class="fa-solid fa-location-dot text-slate-400 mt-0.5 shrink-0 w-4 text-center"></i>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-500 text-[9px] uppercase tracking-widest mb-0.5">Registered Office</div>
                                    <span class="text-slate-600 leading-relaxed whitespace-pre-line" x-text="i.address || 'Address not provided'"></span>
                                </div>
                            </div>
                            <template x-if="i.development_office || i.gst_sales_office">
                                <div class="flex items-start gap-2.5 text-xs border-t border-slate-100 pt-2.5">
                                    <i class="fa-solid fa-building text-slate-400 mt-0.5 shrink-0 w-4 text-center"></i>
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-500 text-[9px] uppercase tracking-widest mb-0.5">Development / GST Sales Office</div>
                                        <span class="text-slate-600 leading-relaxed whitespace-pre-line"
                                              x-text="i.development_office || i.gst_sales_office"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <template x-if="i.rera || i['RERA_Number_(Company)'] || i.rera_project_name || i.rera_phase_1 || i.rera_phase_2 || i.rera_phase_3">
                            <div class="border-t border-slate-100 pt-3">
                                <button type="button" @click.stop="expanded = !expanded"
                                        class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 hover:text-blue-600 transition">
                                    <i class="fa-solid text-[8px]" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    <span x-text="expanded ? 'Hide RERA details' : 'RERA &amp; project details'"></span>
                                </button>
                                <div x-show="expanded" x-collapse class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2.5">
                                    <template x-for="row in [
                                        {k:'RERA Reg.',    v: i.rera || i['RERA_Number_(Company)']},
                                        {k:'RERA Project', v: i.rera_project_name},
                                        {k:'RERA Phase 1', v: i.rera_phase_1},
                                        {k:'RERA Phase 2', v: i.rera_phase_2},
                                        {k:'RERA Phase 3', v: i.rera_phase_3},
                                    ].filter(r => r.v)" :key="row.k">
                                        <div class="bg-amber-50/50 rounded-xl border border-amber-100 px-3 py-2 flex items-center justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="text-[9px] text-amber-700/70 uppercase font-bold tracking-widest" x-text="row.k"></div>
                                                <div class="font-mono text-slate-700 text-xs truncate" x-text="row.v"></div>
                                            </div>
                                            <button type="button" @click.stop="copyToClipboard(row.v, $event)"
                                                    class="shrink-0 w-6 h-6 rounded-md text-amber-300 hover:bg-white hover:text-amber-700 transition flex items-center justify-center">
                                                <i class="fa-regular fa-copy text-[10px]"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                    </div>

                    <div class="px-5 pb-4 no-print">
                        <details class="text-[10px] text-slate-500" open>
                            <summary class="cursor-pointer font-bold uppercase tracking-wider hover:text-slate-700 text-slate-400">
                                Identifier standards
                                <span class="ml-2 font-normal normal-case tracking-normal">
                                    <i class="fa-solid fa-circle-check text-emerald-500"></i> valid ·
                                    <i class="fa-solid fa-circle-xmark text-rose-500"></i> invalid ·
                                    <i class="fa-solid fa-circle text-slate-300"></i> empty
                                </span>
                            </summary>
                            <ul class="mt-2 space-y-1 leading-relaxed list-disc pl-4">
                                <li><b>PAN</b> — 10 chars: 5 letters + 4 digits + 1 letter (e.g. AAXCA1651P)</li>
                                <li><b>TAN</b> — 10 chars: 4 letters + 5 digits + 1 letter (e.g. DELA67622C)</li>
                                <li><b>CIN</b> — 21 chars (MCA), e.g. U68100DL2022PLC400132</li>
                                <li><b>GSTIN</b> — 15 chars: state(2) + PAN(10) + entity(1) + Z + check</li>
                                <li><b>LEI</b> — 20 alphanumeric (ISO 17442)</li>
                                <li><b>ISIN</b> — 12 chars: 2 letters + 9 alnum + check digit</li>
                                <li><b>Udyam</b> — UDYAM-XX-XX-#######</li>
                            </ul>
                        </details>
                    </div>
                </div>

            </template>
        </div>
    </template>
</div>


<script>
/** Indian identifier format checks — green tick / red cross on statutory fields */
function idHint(kind) {
    const m = {
        PAN: 'PAN — 10 chars: 5 letters + 4 digits + 1 letter (e.g. AAXCA1651P)',
        TAN: 'TAN — 10 chars: 4 letters + 5 digits + 1 letter (e.g. DELA67622C)',
        CIN: 'CIN — 21 chars (MCA), e.g. U68100DL2022PLC400132',
        GSTIN: 'GSTIN — 15 chars: state(2) + PAN(10) + entity(1) + Z + check',
        LEI: 'LEI — 20 alphanumeric (ISO 17442)',
        ISIN: 'ISIN — 12 chars: 2 letters + 9 alnum + check digit',
        UDYAM: 'Udyam — UDYAM-XX-XX-#######',
    };
    return m[kind] || '';
}
function idCheck(kind, val) {
    if (val == null || String(val).trim() === '') return null;
    const v = String(val).trim().toUpperCase();
    switch (kind) {
        case 'PAN':   return /^[A-Z]{5}[0-9]{4}[A-Z]$/.test(v);
        case 'TAN':   return /^[A-Z]{4}[0-9]{5}[A-Z]$/.test(v);
        case 'CIN':   return /^[A-Z]{1}[0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/.test(v) || v.length === 21;
        case 'GSTIN': return /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/.test(v);
        case 'LEI':   return /^[A-Z0-9]{20}$/.test(v);
        case 'ISIN':  return /^[A-Z]{2}[A-Z0-9]{9}[0-9]$/.test(v);
        case 'UDYAM': return /^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/.test(v);
        default: return null;
    }
}

async function copyToClipboard(value, evt) {
    const btn = evt.currentTarget;
    const icon = btn.querySelector('i.fa-copy') || btn.querySelector('i');
    const orig = icon ? icon.className : '';
    let ok = false;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(String(value));
            ok = true;
        } else {
            const ta = document.createElement('textarea');
            ta.value = String(value);
            ta.style.position = 'fixed'; ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.focus(); ta.select();
            ok = document.execCommand('copy');
            document.body.removeChild(ta);
        }
    } catch (e) { ok = false; }
    if (icon) {
        icon.className = ok ? 'fa-solid fa-check text-[10px] text-emerald-500' : 'fa-solid fa-xmark text-[10px] text-rose-500';
        setTimeout(() => { icon.className = orig; }, 1400);
    }
}
</script>
