<?php
/* ─────────────────────────────────────────────────────────────────────────
   Construction Progress Portal | Release v14.1.0 (2026-10-11)
   Single release version across all files. See CHANGELOG.md for changes.
   ───────────────────────────────────────────────────────────────────── */?>
<div class="d-flex gap-2 flex-nowrap action-btn-group" style="overflow-x: auto; padding-bottom: 2px;" role="toolbar" aria-label="Export and share actions">
    <button onclick="handleAction('shareWhatsApp')" class="btn btn-light border fw-bold text-dark d-flex align-items-center gap-2" type="button" aria-label="Share via WhatsApp">
        <i class="fab fa-whatsapp text-success fs-5" aria-hidden="true"></i>
        <span class="d-none d-sm-inline">Share</span>
    </button>
    <button onclick="handleAction('exportExcel')" class="btn btn-light border fw-bold text-dark d-flex align-items-center gap-2" type="button" title="Export database to XLSX" aria-label="Export to Excel">
        <i class="fas fa-file-excel text-success fs-5" aria-hidden="true"></i>
        <span class="d-none d-sm-inline">Excel</span>
    </button>
    <a href="report.php" target="_blank" rel="noopener" class="btn btn-light border fw-bold text-dark d-flex align-items-center gap-2" id="btn-pdf-export" aria-label="Download PDF report">
        <i class="far fa-file-pdf text-danger fs-5" aria-hidden="true"></i>
        <span class="d-none d-sm-inline">PDF Report</span>
    </a>
    <a href="report.php?print=1" target="_blank" rel="noopener" class="btn btn-light border fw-bold text-dark d-flex align-items-center gap-2" id="btn-print" aria-label="Print Report">
        <i class="fas fa-print text-primary fs-5" aria-hidden="true"></i>
        <span class="d-none d-sm-inline">Print</span>
    </a>
</div>

<script>
(function() {
    const shareBrand = <?php echo json_encode((string)cfg('brand.company', ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.dbProjects = <?php echo json_encode(array_values($projects ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.dbComponents = <?php echo json_encode(array_values($components ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.dbUpdates = <?php echo json_encode(array_values($updates ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    window.loadXLSX = function(callback) {
        if (window.XLSX) { callback(window.XLSX); return; }
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
        script.onload = function() { callback(window.XLSX); };
        document.head.appendChild(script);
    };

    window.handleAction = function(action) {
        if (typeof logAnalytics === 'function') logAnalytics('button_click', { button_name: action });
        if (action === 'shareWhatsApp') shareWhatsApp();
        if (action === 'exportExcel') exportToExcel();
    };

    function shareWhatsApp() {
        const titleEl = document.getElementById('viewTitle');
        const title = titleEl ? titleEl.innerText : 'Construction Progress Report';
        const url = window.location.href.split('?')[0];
        window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent('View the official construction progress report for ' + title + ' by ' + shareBrand + ':\n\n' + url), '_blank', 'noopener,noreferrer');
    }

    function exportToExcel() {
        loadXLSX(function (XLSX) {
            const expUpdates = dbUpdates.map(u => ({
                ...u,
                images: Array.isArray(u.images) ? u.images.join(',') : (u.images || '')
            }));
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(dbProjects), 'Projects');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(dbComponents), 'Components');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(expUpdates), 'Updates');
            XLSX.writeFile(wb, 'Fusion_Status_Report_' + new Date().toISOString().split('T')[0] + '.xlsx');
        });
    }
})();
</script>