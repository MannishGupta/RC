<?php
// Version: 260916.14
// views/tab_numero.php — V2.0.0
// ✅ System-adaptive dark/light mode (OS prefers-color-scheme)
// ✅ Compare toggle → dynamic side-by-side layout
// ✅ Person B captures: Name, DOB, Gender, Mobile
// ✅ Empowerment-first copy & micro-labels
if (!defined('BASE_PATH')) exit;
?>
<style>:root,.numero-root,[data-theme="light"] .numero-root{--n-bg:#F8FAFC;--n-card:#FFFFFF;--n-border:#E2E8F0;--n-text:#0F172A;--n-muted:#475569;--n-label:#2563EB;--n-input-line:#CBD5E1;--n-focus-line:#2563EB;--n-divider:#E2E8F0;--n-pill-bg:#EFF6FF;--n-pill-text:#1D4ED8;--n-shadow:rgba(15,23,42,0.08);font-family:Inter,"Noto Sans Devanagari",system-ui,sans-serif;color:var(--n-text);background:var(--n-bg)}@media (prefers-color-scheme:dark){.nw{--n-bg:#0B0F19;--n-card:#1E293B;--n-border:#334155;--n-text:#f1f5f9;--n-muted:#94a3b8;--n-label:#60A5FA;--n-input-line:#475569;--n-focus-line:#3B82F6;--n-divider:#334155;--n-pill-bg:#1E3A5F;--n-pill-text:#93C5FD;--n-shadow:rgba(0,0,0,0.4)}}.nw-card{background:var(--n-card);border:1px solid var(--n-border);border-radius:1.25rem;box-shadow:0 12px 40px var(--n-shadow);overflow:hidden}.n-label{display:block;font-size:10px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--n-label);margin-bottom:6px}.n-input{width:100%;padding:12px 14px;border-radius:12px;border:1px solid var(--n-input-line);background:var(--n-bg);color:var(--n-text);font-size:0.9rem;font-weight:600;outline:none;transition:border-color 0.15s,box-shadow 0.15s}.n-input:focus{border-color:var(--n-focus-line);box-shadow:0 0 0 3px rgba(249,115,22,0.2)}.n-input::placeholder{color:var(--n-muted);opacity:0.85}.n-input[type="date"]::-webkit-calendar-picker-indicator{filter:invert(0.4);cursor:pointer}@media (prefers-color-scheme:dark){.n-input[type="date"]::-webkit-calendar-picker-indicator{filter:invert(0.85)}}select.n-input{appearance:none;cursor:pointer}.n-field{flex:1;min-width:0}.n-group{display:flex;gap:12px;align-items:flex-start}.n-row{margin-bottom:4px}.n-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:var(--n-pill-bg);color:var(--n-label);flex-shrink:0;margin-top:18px}.n-btn{width:100%;padding:14px;border-radius:14px;border:1px solid rgba(251,146,60,0.45);font-size:0.875rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#fff;display:flex;align-items:center;justify-content:center;gap:10px;cursor:pointer;transition:all 0.2s;background:linear-gradient(135deg,#2563eb,#1d4ed8);box-shadow:0 8px 24px rgba(249,115,22,0.35)}.n-btn:hover{opacity:0.92;transform:translateY(-1px)}.n-btn:active{transform:translateY(0)}.n-compare-toggle{display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;padding:10px 16px;border-radius:12px;background:var(--n-pill-bg);border:1px dashed var(--n-border);font-size:0.75rem;font-weight:700;color:var(--n-label);text-transform:uppercase;letter-spacing:0.07em}.n-toggle-dot{width:36px;height:20px;border-radius:10px;background:var(--n-input-line);position:relative;flex-shrink:0}.n-toggle-dot::after{content:"";position:absolute;width:14px;height:14px;border-radius:50%;background:#fff;top:3px;left:3px;transition:transform 0.2s;box-shadow:0 1px 3px rgba(0,0,0,0.2)}.n-toggle-dot.on{background:#2563eb}.n-toggle-dot.on::after{transform:translateX(16px)}.n-compare-panels{display:grid;grid-template-columns:1fr 1fr;gap:16px}@media (max-width:600px){.n-compare-panels{grid-template-columns:1fr}}.n-panel-label{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:6px;margin-bottom:14px}.n-panel-a{background:rgba(99,102,241,0.12);color:#4f46e5}.n-panel-b{background:rgba(236,72,153,0.12);color:#db2777}@media (prefers-color-scheme:dark){.n-panel-a{color:#a5b4fc}.n-panel-b{color:#f9a8d4}}.n-divider-v{width:1px;background:var(--n-divider)}@media (max-width:600px){.n-divider-v{display:none}}.n-compare-section{overflow:hidden;transition:max-height 0.35s ease,opacity 0.3s ease}.n-compare-section.hidden{max-height:0;opacity:0}.n-compare-section.visible{max-height:900px;opacity:1}.vedic-gradient{background:linear-gradient(135deg,#1e40af 0%,#1d4ed8 40%,#1e3a8a 100%)}</style>

<div class="nw w-full min-h-[80vh] flex items-center justify-center p-4">
    <div class="max-w-2xl mx-auto w-full" id="numero-root">
        <div class="nw-card">

            <!-- ── Hero Banner ── -->
            <div class="vedic-gradient pt-12 pb-8 px-8 text-center relative overflow-hidden">
                <div class="absolute -top-12 -right-12 w-40 h-40 bg-yellow-300/20 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute bottom-0 -left-10 w-32 h-32 bg-indigo-400/30 rounded-full blur-2xl pointer-events-none"></div>
                <div class="absolute top-4 right-1/4 w-2 h-2 bg-white/60 rounded-full shadow-[0_0_10px_rgba(255,255,255,0.8)] pointer-events-none"></div>
                <div class="absolute bottom-6 left-1/3 w-1.5 h-1.5 bg-white/50 rounded-full shadow-[0_0_8px_rgba(255,255,255,0.8)] pointer-events-none"></div>
                <div class="relative z-10">
                    <div class="w-16 h-16 bg-white/10 backdrop-blur-md border border-white/20 text-white rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4 shadow-lg">
                        <i class="fa-solid fa-dharmachakra animate-[spin_20s_linear_infinite]"></i>
                    </div>
                    <h2 class="text-3xl font-extrabold text-white tracking-tight drop-shadow-md">Vedic Numero Engine</h2>
                    <p class="font-kalam text-blue-100 text-lg mt-2 drop-shadow-sm">Name, mobile &amp; date of birth → diagnostic or compatibility report</p>
                </div>
            </div>

            <!-- ── Form ── -->
            
<div class="flex flex-wrap gap-2 mb-4 no-print">
  <a href="janam_patri.php" class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700">
    <i class="fa-solid fa-om"></i> Janam Patri &amp; Kundli Milan
  </a>
  <a href="janam_patri.php?view=match" class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-indigo-200 text-indigo-800 text-xs font-bold bg-indigo-50 hover:bg-indigo-100">
    <i class="fa-solid fa-heart"></i> Matchmaking
  </a>
</div>
<form action="?" method="GET"  id="numero-form" class="p-7 space-y-6" style="background:var(--n-card)">
                <input type="hidden" name="card" value="numero">
                <?php if(!empty($_GET['slug'])): ?>
                <input type="hidden" name="slug" value="<?= htmlspecialchars((string)($_GET['slug'] ?? '')) ?>">
                <?php endif; ?>

                <!-- ── Compare Toggle ── -->
                <div class="flex items-center justify-between gap-4">
                    <div class="n-compare-toggle" id="compare-toggle" onclick="NumeroForm.toggleCompare()">
                        <span class="n-toggle-dot" id="compare-dot"></span>
                        <span id="compare-label">Compare Two People</span>
                    </div>
                    <div class="text-right text-[9px]" style="color:var(--n-muted)">
                        Arthsathi Vedic Numero
                    </div>
                </div>

                <!-- ══ SINGLE / PERSON A PANEL ══ -->
                <div id="single-panel" class="space-y-6">

                    <!-- Name -->
                    <div class="n-group n-row">
                        <div class="n-icon"><i class="fa-solid fa-signature text-sm"></i></div>
                        <div class="n-field">
                            <span class="n-label">Full Name</span>
                            <input type="text" name="name" class="n-input" required placeholder="Enter full name" autocomplete="name">
                        </div>
                    </div>

                    <!-- Mobile -->
                    <div class="n-group n-row">
                        <div class="n-icon"><i class="fa-solid fa-mobile-screen text-sm"></i></div>
                        <div class="n-field">
                            <span class="n-label">Mobile Number</span>
                            <input type="tel" name="mobile" class="n-input" style="font-family:monospace;letter-spacing:.1em" placeholder="9876543210 (Optional)" autocomplete="tel">
                        </div>
                    </div>

                    <!-- DOB + Gender -->
                    <div class="n-group" style="display:flex;align-items:flex-start;gap:16px">
                        <div class="n-icon" style="margin-top:8px"><i class="fa-solid fa-calendar-days text-sm"></i></div>
                        <div style="flex:1;display:grid;grid-template-columns:1fr 1fr;gap:20px">
                            <div class="n-field">
                                <span class="n-label">Date of Birth</span>
                                <input type="date" name="dob" class="n-input" style="margin-top:4px" required autocomplete="bday">
                            </div>
                            <div class="n-field" style="position:relative">
                                <span class="n-label">Gender</span>
                                <select name="gender" class="n-input" style="margin-top:4px;padding-right:24px">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                                <i class="fa-solid fa-chevron-down" style="position:absolute;right:4px;top:18px;font-size:10px;color:var(--n-muted);pointer-events:none"></i>
                            </div>
                        </div>
                    </div>

                </div><!-- /single-panel -->

                <!-- ══ COMPARE SECTION (hidden by default) ══ -->
                <div class="n-compare-section hidden" id="compare-section">
                    <div style="border-top:1px solid var(--n-divider);margin-bottom:20px;padding-top:20px">
                        <div class="n-compare-panels">

                            <!-- Person A column -->
                            <div id="col-a">
                                <div class="n-panel-label n-panel-a"><i class="fa-solid fa-user"></i> Person A (Primary)</div>
                                <!-- fields moved here by JS -->
                                <div id="col-a-fields"></div>
                            </div>

                            <div class="n-divider-v"></div>

                            <!-- Person B column -->
                            <div id="col-b">
                                <div class="n-panel-label n-panel-b"><i class="fa-solid fa-user-plus"></i> Person B (Compare)</div>

                                <!-- B: Name -->
                                <div class="n-group n-row" style="margin-bottom:20px">
                                    <div class="n-icon"><i class="fa-solid fa-signature text-sm"></i></div>
                                    <div class="n-field">
                                        <span class="n-label">Full Name</span>
                                        <input type="text" name="compare_name" class="n-input" placeholder="Person B's name">
                                    </div>
                                </div>

                                <!-- B: Mobile -->
                                <div class="n-group n-row" style="margin-bottom:20px">
                                    <div class="n-icon"><i class="fa-solid fa-mobile-screen text-sm"></i></div>
                                    <div class="n-field">
                                        <span class="n-label">Mobile (Optional)</span>
                                        <input type="tel" name="compare_mobile" class="n-input" style="font-family:monospace;letter-spacing:.1em" placeholder="9876543210">
                                    </div>
                                </div>

                                <!-- B: DOB + Gender -->
                                <div class="n-group" style="display:flex;align-items:flex-start;gap:16px">
                                    <div class="n-icon" style="margin-top:8px"><i class="fa-solid fa-calendar-days text-sm"></i></div>
                                    <div style="flex:1;display:grid;grid-template-columns:1fr 1fr;gap:16px">
                                        <div class="n-field">
                                            <span class="n-label">Date of Birth</span>
                                            <input type="date" name="compare_dob" class="n-input" style="margin-top:4px">
                                        </div>
                                        <div class="n-field" style="position:relative">
                                            <span class="n-label">Gender</span>
                                            <select name="compare_gender" class="n-input" style="margin-top:4px;padding-right:24px">
                                                <option value="Female">Female</option>
                                                <option value="Male">Male</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <i class="fa-solid fa-chevron-down" style="position:absolute;right:4px;top:18px;font-size:10px;color:var(--n-muted);pointer-events:none"></i>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /col-b -->

                        </div><!-- /compare-panels -->
                    </div>
                </div><!-- /compare-section -->

                <!-- ── Submit ── -->
                <div>
                    <button type="submit" class="n-btn vedic-gradient">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color:#93c5fd"></i>
                        <span id="btn-label">Generate Diagnostic Report</span>
                    </button>
                </div>

                <!-- Footer note -->
                <div style="text-align:center;display:flex;flex-direction:column;align-items:center;gap:4px;font-size:10px;color:var(--n-muted);font-weight:700;text-transform:uppercase;letter-spacing:.07em">
                    <span><i class="fa-solid fa-lock" style="margin-right:4px;opacity:.5"></i> Deterministic Engine Logic</span>
                    <span>Deterministic engine · Arthsathi</span>
                </div>

            </form><!-- /form -->
        </div><!-- /nw-card -->
    </div><!-- /max-w -->
</div><!-- /nw -->

<script>(function(){
    var compareActive = false;
    var singlePanel   = document.getElementById('single-panel');
    var compareSection= document.getElementById('compare-section');
    var colAFields    = document.getElementById('col-a-fields');
    var dot           = document.getElementById('compare-dot');
    var btnLabel      = document.getElementById('btn-label');
    var form          = document.getElementById('numero-form');
    function moveToPanels() {
    }
    function moveToSingle() {
    }
    window.NumeroForm = {
        toggleCompare: function() {
            compareActive = !compareActive;
            dot.classList.toggle('on', compareActive);
            if (compareActive) {
                singlePanel.style.display = 'none';
                compareSection.classList.remove('hidden');
                compareSection.classList.add('visible');
                btnLabel.textContent = 'Generate Compatibility Report';
            } else {
                singlePanel.style.display = '';
                compareSection.classList.remove('visible');
                compareSection.classList.add('hidden');
                btnLabel.textContent = 'Generate Diagnostic Report';
                form.querySelectorAll('[name^="compare_"]').forEach(function(el){ el.value = ''; });
            }
        }
    };
    if (new URLSearchParams(window.location.search).get('compare_name')) {
        window.NumeroForm.toggleCompare();
    }
    if (form) {
        form.addEventListener('submit', function(e) {
            var name = (form.querySelector('[name="name"]') || {}).value || '';
            var dob  = (form.querySelector('[name="dob"]') || {}).value || '';
            if (!String(name).trim() || !String(dob).trim()) {
                e.preventDefault();
                alert('Please enter full name and date of birth for Person A.');
                return false;
            }
            if (compareActive) {
                var cn = (form.querySelector('[name="compare_name"]') || {}).value || '';
                var cd = (form.querySelector('[name="compare_dob"]') || {}).value || '';
                if (!String(cn).trim() || !String(cd).trim()) {
                    e.preventDefault();
                    alert('Compatibility report needs Person B name and date of birth.');
                    return false;
                }
            }
        });
    }
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    function applyTheme(isDark) {
        var root = document.documentElement;
        if (isDark) {
            root.style.setProperty('--n-bg','#0f172a');
            root.style.setProperty('--n-card','#1e293b');
            root.style.setProperty('--n-border','#334155');
            root.style.setProperty('--n-text','#f1f5f9');
            root.style.setProperty('--n-muted','#94a3b8');
            root.style.setProperty('--n-input-line','#334155');
            root.style.setProperty('--n-label','#60A5FA');
            root.style.setProperty('--n-divider','#334155');
            root.style.setProperty('--n-pill-bg','#1E3A5F');
            root.style.setProperty('--n-pill-text','#93C5FD');
            root.style.setProperty('--n-shadow','rgba(0,0,0,.4)');
        } else {
            root.style.setProperty('--n-bg','#ffffff');
            root.style.setProperty('--n-card','#ffffff');
            root.style.setProperty('--n-border','#E2E8F0');
            root.style.setProperty('--n-text','#1e293b');
            root.style.setProperty('--n-muted','#64748b');
            root.style.setProperty('--n-input-line','#f1f5f9');
            root.style.setProperty('--n-label','#ea580c');
            root.style.setProperty('--n-divider','#f1f5f9');
            root.style.setProperty('--n-pill-bg','#EFF6FF');
            root.style.setProperty('--n-pill-text','#1D4ED8');
            root.style.setProperty('--n-shadow','rgba(154,52,18,.08)');
        }
    }
    mq.addEventListener('change', function(e){ applyTheme(e.matches); });
    applyTheme(mq.matches);
})();
</script>
