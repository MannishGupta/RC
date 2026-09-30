<?php
/**
 * Governance hub: Terms, Privacy (DPDP-oriented), Location notice, Accessibility.
 * Version: 260921.36
 */
if (!defined('BASE_PATH')) exit;

$developerName    = 'Arthsathi Limited';
$developerWebsite = 'https://arthsathi.com';
$developerEmail   = 'legal@arthsathi.com';
$privacyEmail     = 'privacy@arthsathi.com';
$clientName       = $viewData['company']['name'] ?? ($companyData['name'] ?? 'Client Organization');
$clientName       = is_string($clientName) ? $clientName : 'Client Organization';
$termsEffectiveDate = defined('TERMS_EFFECTIVE_DATE') ? TERMS_EFFECTIVE_DATE : '23 September 2026';
$appVer = defined('APP_VERSION') ? APP_VERSION : 'unknown';
$appDate = defined('APP_VERSION_DATE') ? APP_VERSION_DATE : '';
$h = static function ($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<style>
    .gov-hub h2 { color: #0f172a; font-weight: 800; border-bottom: 2px solid #f1f5f9; padding-bottom: .6rem; margin: 1.75rem 0 .85rem; font-size: 1.15rem; letter-spacing: -.02em; }
    .gov-hub h3 { color: #1e293b; font-weight: 700; margin: 1.25rem 0 .5rem; font-size: 1rem; }
    .gov-hub p, .gov-hub li { color: #475569; line-height: 1.75; font-size: .925rem; margin-bottom: .65rem; }
    .gov-hub ul { list-style: disc; padding-left: 1.35rem; margin-bottom: 1rem; }
    .gov-hub strong { color: #1e293b; font-weight: 700; }
    .gov-hub a { color: #2563eb; font-weight: 600; text-decoration: none; }
    .gov-hub a:hover { text-decoration: underline; color: #1d4ed8; }
    .gov-tab { border: 1px solid #e2e8f0; background: #fff; color: #475569; font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; padding: .55rem .9rem; border-radius: 9999px; cursor: pointer; transition: .15s; }
    .gov-tab:hover, .gov-tab:focus-visible { border-color: #93c5fd; color: #1d4ed8; outline: 2px solid #93c5fd; outline-offset: 2px; }
    .gov-tab[aria-selected="true"] { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
    .gov-callout { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1rem 1.15rem; margin: 1rem 0; }
    .gov-callout.dpdp { background: #eff6ff; border-color: #bfdbfe; }
    .gov-callout.loc { background: #fff7ed; border-color: #fed7aa; }
</style>

<div class="w-full bg-white rounded-3xl shadow-sm border border-slate-200 p-6 md:p-10 mb-8"
     x-data="{ panel: (new URLSearchParams(location.search).get('policy') || 'terms') }"
     x-init="$watch('panel', v => { const u = new URL(location.href); u.searchParams.set('tab','terms'); u.searchParams.set('policy', v); history.replaceState({}, '', u); })">

    <div class="mb-8 text-center border-b border-slate-100 pb-8">
        <div class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black uppercase tracking-widest mb-4 border border-slate-200">
            Governance &amp; Compliance
        </div>
        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight mb-3">Platform Policies</h1>
        <p class="text-slate-500 font-medium text-sm">Effective date: <?= $h($termsEffectiveDate) ?> · Licensed to <?= $h($clientName) ?></p>
        <p class="text-slate-400 text-xs mt-2 max-w-2xl mx-auto">These documents support operational transparency under applicable Indian law, including the Digital Personal Data Protection Act, 2023 (DPDP Act), and accessibility good practice aligned with WCAG 2.2.</p>
    </div>

    <div class="flex flex-wrap gap-2 justify-center mb-8" role="tablist" aria-label="Policy sections">
        <button type="button" class="gov-tab" role="tab" :aria-selected="panel==='terms'" @click="panel='terms'" id="gov-tab-terms">Terms of Use</button>
        <button type="button" class="gov-tab" role="tab" :aria-selected="panel==='privacy'" @click="panel='privacy'" id="gov-tab-privacy">Privacy &amp; DPDP</button>
        <button type="button" class="gov-tab" role="tab" :aria-selected="panel==='location'" @click="panel='location'" id="gov-tab-location">Location Tracking</button>
        <button type="button" class="gov-tab" role="tab" :aria-selected="panel==='a11y'" @click="panel='a11y'" id="gov-tab-a11y">Accessibility</button>
    </div>

    <div class="gov-hub max-w-4xl mx-auto">

        <!-- ═══════════════ TERMS ═══════════════ -->
        <div x-show="panel==='terms'" x-cloak role="tabpanel" aria-labelledby="gov-tab-terms">
            <h2>1. Parties and platform</h2>
            <p>This enterprise digital platform (the “Platform”) is developed and supported by <strong><?= $h($developerName) ?></strong> (“Developer”). This instance is operated for <strong><?= $h($clientName) ?></strong> (“Client”, “organisation”). Authorised personnel (“Users”) access the Platform subject to these terms and the Client’s internal policies.</p>

            <h2>2. Licence and acceptable use</h2>
            <ul>
                <li>The Platform is licensed for the Client’s legitimate business operations (directory, cards, documents, logistics modules as enabled).</li>
                <li>Users must not attempt unauthorised access, reverse engineer the software beyond lawful rights, upload malware, or misuse personal data of colleagues or third parties.</li>
                <li>Credentials are personal; sharing admin access keys is prohibited unless the Client formally provisions shared roles.</li>
            </ul>

            <h2>3. Client responsibilities</h2>
            <ul>
                <li>Accuracy of roster, banking references, documents, and designations.</li>
                <li>Obtaining any workplace consents required for photographs, contact sharing, and location tracking.</li>
                <li>Configuring integrations (email, maps, messaging, wallets) lawfully and securely.</li>
                <li>Retaining backups of organisational data stored in Platform data files.</li>
            </ul>

            <h2>4. Advisory modules</h2>
            <p>Numerology, muhurat, and related interpretive outputs are informational cultural tools only. They are <strong>not</strong> professional, medical, financial, or legal advice. The Client and Users remain solely responsible for decisions made after viewing such outputs.</p>

            <h2>5. Intellectual property</h2>
            <p>Software, design system, and documentation remain the property of <?= $h($developerName) ?> or its licensors. Client-supplied content (employee data, logos, documents) remains the Client’s responsibility and property as between Client and its personnel.</p>

            <h2>6. Limitation of liability</h2>
            <p>To the maximum extent permitted by applicable law, the Developer is not liable for indirect or consequential loss, including loss arising from inaccurate user-entered data, third-party API outages, hosting failures, or decisions based on advisory modules. Nothing excludes liability that cannot be excluded under Indian law.</p>

            <h2>7. Governing law</h2>
            <p>These terms are governed by the laws of India. Courts at New Delhi shall have exclusive jurisdiction, without prejudice to mandatory consumer or employment protections where applicable.</p>

            <h2>8. Contact</h2>
            <p>Legal and compliance: <a href="mailto:<?= $h($developerEmail) ?>"><?= $h($developerEmail) ?></a> · <a href="<?= $h($developerWebsite) ?>" rel="noopener noreferrer"><?= $h($developerWebsite) ?></a></p>
        </div>

        <!-- ═══════════════ PRIVACY / DPDP ═══════════════ -->
        <div x-show="panel==='privacy'" x-cloak role="tabpanel" aria-labelledby="gov-tab-privacy">
            <div class="gov-callout dpdp">
                <p class="mb-0"><strong>DPDP-oriented notice.</strong> This policy describes how personal data may be processed in this Platform instance in a manner consistent with the principles of the Digital Personal Data Protection Act, 2023 (India). The Client organisation typically acts as the primary <em>Data Fiduciary</em> for its workforce data. <?= $h($developerName) ?> acts as a technology provider / data processor to the extent it hosts or supports the instance under contract.</p>
            </div>

            <h2>1. Roles</h2>
            <ul>
                <li><strong>Data Principal:</strong> the individual (employee, contractor, or other person) to whom personal data relates.</li>
                <li><strong>Data Fiduciary:</strong> <?= $h($clientName) ?> for organisational HR, directory, and operations data it controls.</li>
                <li><strong>Processing by Platform:</strong> storage, display, export (e.g. VCF), optional maps/messaging integrations as configured by the Client.</li>
            </ul>

            <h2>2. Categories of personal data</h2>
            <ul>
                <li>Identity and employment: name, designation, department, employee identifiers, rank, photographs.</li>
                <li>Contact: phone, email, social or messaging handles where entered.</li>
                <li>Optional operational: work locations, document metadata, event participation.</li>
                <li>Where enabled: approximate or precise <strong>location</strong>, movement summaries, and policy-violation timestamps for mandated field roles.</li>
                <li>Technical logs: authentication events, IP/session metadata as generated by the host environment.</li>
            </ul>

            <h2>3. Purposes</h2>
            <ul>
                <li>Internal directory, digital visiting cards, and operational coordination.</li>
                <li>Document and statutory record reference for authorised staff.</li>
                <li>Logistics and field operations (tracking, dispatch) where the Client enables those modules.</li>
                <li>Security of the Platform and prevention of misuse.</li>
            </ul>
            <p>Personal data is not sold by the Platform. Sharing with third-party processors (e.g. map, email, or messaging providers) occurs only when the Client configures those integrations.</p>

            <h2>4. Lawful basis / consent (operational guidance)</h2>
            <ul>
                <li>Employment and legitimate operational purposes as determined by the Client under applicable law.</li>
                <li><strong>Location tracking</strong> for mandated designations should be enabled only with clear workplace notice and, where required, consent or employment policy authority.</li>
                <li>Users should not upload third-party personal data without authority.</li>
            </ul>

            <h2>5. Rights of Data Principals</h2>
            <p>Subject to the DPDP Act and employment law, individuals may request from the Client (Data Fiduciary):</p>
            <ul>
                <li>Access to personal data held about them in the Platform;</li>
                <li>Correction of inaccurate data;</li>
                <li>Erasure where legally available;</li>
                <li>Withdrawal of consent where processing is consent-based;</li>
                <li>Grievance redressal via the contacts below.</li>
            </ul>
            <p>Requests should be directed first to the Client’s HR / admin administrators who control the live data files.</p>

            <h2>6. Retention</h2>
            <p>Retention follows the Client’s HR and statutory schedules. Location breadcrumbs and violation logs should be retained only as long as needed for operations, safety, or legal defence, then deleted or archived securely by the Client.</p>

            <h2>7. Security safeguards</h2>
            <ul>
                <li>Access control (authenticated sessions; admin-gated settings).</li>
                <li>Transport security (HTTPS recommended/required in production).</li>
                <li>Restricted access to server-side data stores and secrets (API keys, SMTP).</li>
                <li>Client responsibility for host hardening, backups, and user provisioning.</li>
            </ul>

            <h2>8. Cross-border transfer</h2>
            <p>If the Client hosts data or uses processors outside India, the Client must ensure such transfer is permitted under applicable law and contractual safeguards.</p>

            <h2>9. Children</h2>
            <p>The Platform is intended for workforce and business use, not for children. Do not enrol minors without lawful authority and verifiable parental consent where required.</p>

            <h2>10. Grievance &amp; contact</h2>
            <ul>
                <li>Client administrators: use internal HR / IT channels for roster and access issues.</li>
                <li>Developer privacy contact: <a href="mailto:<?= $h($privacyEmail) ?>"><?= $h($privacyEmail) ?></a></li>
                <li>Legal: <a href="mailto:<?= $h($developerEmail) ?>"><?= $h($developerEmail) ?></a></li>
            </ul>
            <p class="text-xs text-slate-400">This notice is operational guidance for Platform use. It is not a substitute for formal legal advice or a Client-specific privacy policy filed with regulators.</p>
        </div>

        <!-- ═══════════════ LOCATION ═══════════════ -->
        <div x-show="panel==='location'" x-cloak role="tabpanel" aria-labelledby="gov-tab-location">
            <div class="gov-callout loc">
                <p class="mb-0"><strong>Field personnel notice.</strong> Live location and travel history modules process precise location data. Enable only for designations the organisation has marked for mandatory or voluntary tracking, with clear communication to staff.</p>
            </div>

            <h2>1. When tracking applies</h2>
            <ul>
                <li>Designations flagged for live tracking in organisational configuration.</li>
                <li>During windows defined in policy settings (e.g. working hours), where configured.</li>
                <li>On devices where the individual grants location permission to the browser or tracking page.</li>
            </ul>

            <h2>2. What is collected</h2>
            <ul>
                <li>Coordinates, timestamps, optional speed/battery where supplied by the device.</li>
                <li>Derived daily summaries (distance, active/idle time) for operational reports.</li>
                <li>Disconnect / policy-violation events during mandated windows, where enabled.</li>
            </ul>

            <h2>3. Who can view</h2>
            <p>Authorised administrators and logistics supervisors as provisioned by the Client. Tracking views are not intended for public access.</p>

            <h2>4. Individual responsibilities</h2>
            <ul>
                <li>Keep location services available during mandated duty windows when required by policy.</li>
                <li>Use only organisation-approved tracking links or apps.</li>
                <li>Report device loss or account compromise immediately to administrators.</li>
            </ul>

            <h2>5. HR / admin responsibilities</h2>
            <ul>
                <li>Document which roles require tracking and why.</li>
                <li>Train staff before enabling mandates.</li>
                <li>Review violation logs proportionately; avoid punitive misuse of data.</li>
                <li>Set retention and purge routines for GPS logs.</li>
            </ul>
        </div>

        <!-- ═══════════════ ACCESSIBILITY ═══════════════ -->
        <div x-show="panel==='a11y'" x-cloak role="tabpanel" aria-labelledby="gov-tab-a11y">
            <h2>Accessibility statement</h2>
            <p>The Platform is developed with progressive alignment to <strong>WCAG 2.2</strong> Level A/AA techniques and W3C HTML5 / WAI-ARIA practices. We aim for usable keyboard access, visible focus, labelled controls, dialog focus management, and readable contrast on primary workflows.</p>

            <h2>Measures in place</h2>
            <ul>
                <li>Skip link to main content; landmark regions on primary shells.</li>
                <li>Dialog pattern with focus trap; tooltip pattern with accessible descriptions.</li>
                <li>Form labels and accessible names on key actions.</li>
                <li>Status messages via polite live regions where implemented.</li>
                <li>Reduced-motion consideration in shared accessibility CSS.</li>
            </ul>

            <h2>Known limits</h2>
            <ul>
                <li>Full WCAG 2.2 AA certification is not claimed; third-party maps and dynamic lists may need further testing.</li>
                <li>Complex visual modules (maps, dense tables) should be verified with assistive technology on each major release.</li>
            </ul>

            <h2>Feedback</h2>
            <p>If you encounter an accessibility barrier, contact <a href="mailto:<?= $h($developerEmail) ?>"><?= $h($developerEmail) ?></a> with the page URL, platform, and assistive technology used. The Client’s administrators can also adjust content (e.g. alt text via clearer photo naming, simpler designations) to improve day-to-day usability.</p>

            <h2>Technical references</h2>
            <ul>
                <li>Internal: <code>docs/W3C_VALIDATION_READY.md</code>, <code>docs/WCAG_22_AA_AUDIT_REPORT.md</code></li>
                <li>External: <a href="https://www.w3.org/TR/WCAG22/" rel="noopener noreferrer">WCAG 2.2</a>, <a href="https://www.w3.org/WAI/ARIA/apg/" rel="noopener noreferrer">ARIA Authoring Practices</a></li>
            </ul>
        </div>

        <div class="mt-12 pt-6 border-t border-slate-200 text-sm text-slate-500 bg-slate-50 p-5 rounded-xl">
            <p class="mb-1">Developer: <strong><?= $h($developerName) ?></strong> · <a href="<?= $h($developerWebsite) ?>" rel="noopener noreferrer"><?= $h($developerWebsite) ?></a></p>
            <p class="mb-1 text-xs font-mono text-slate-400">Build <?= $h($appVer) ?><?= $appDate !== '' ? ' · ' . $h($appDate) : '' ?> · Policy pack 260921.36</p>
            <p class="text-xs text-slate-400 mb-0">These policies are provided for operational transparency. Client organisations should adapt or supersede them with counsel-approved documents where required for regulated filings.</p>
        </div>
    </div>
</div>
