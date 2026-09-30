<?php
// Version: 260918.12 — document file version, file size, file preview (from record data)
if (!defined('BASE_PATH')) exit;

/** Human size from bytes */
$docFmt = static function ($bytes): string {
    $n = (int)$bytes;
    if ($n <= 0) return '';
    if ($n < 1024) return $n . ' B';
    if ($n < 1048576) return round($n / 1024, 1) . ' KB';
    if ($n < 1073741824) return round($n / 1048576, 1) . ' MB';
    return round($n / 1073741824, 2) . ' GB';
};
?>
<div class="w-full flex flex-col space-y-4"
     x-data="{
        fileVer(i) {
            const v = (i && (i.version || i.doc_version || i.file_version || i.rev)) || '';
            return String(v).trim();
        },
        fileSize(i) {
            if (!i) return '';
            if (i.size && String(i.size).trim()) return String(i.size).trim();
            const b = parseInt(i.size_bytes || i.file_size || i.filesize || i.bytes || 0, 10);
            if (b > 0) {
                const u = ['B','KB','MB','GB'];
                let n = b, idx = 0;
                while (n >= 1024 && idx < u.length - 1) { n /= 1024; idx++; }
                const r = (n < 10 && idx > 0) ? Math.round(n * 10) / 10 : Math.round(n);
                return r + ' ' + u[idx];
            }
            if (i.external_url) return 'Live link';
            return '';
        },
        fileLabel(i) {
            const t = (i.file_type || '').toString();
            if (t) return t.toUpperCase();
            const f = String(i.doc_file || i.name || '');
            const m = f.match(/\.([a-z0-9]{2,5})$/i);
            if (m) return m[1].toUpperCase();
            if (i.external_url) return 'LINK';
            return 'FILE';
        }
     }">

    <template x-if="paginatedList.length === 0">
        <div class="flex flex-col items-center justify-center h-64 text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl w-full bg-slate-50/50 mt-2">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                <i class="fa-solid fa-folder-open text-3xl text-slate-300"></i>
            </div>
            <p class="text-sm font-semibold text-slate-600">No documents found.</p>
            <p class="text-xs text-slate-400 mt-1">Upload a document with version number to get started.</p>
        </div>
    </template>

    <template x-if="paginatedList.length > 0">
        <div class="w-full bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left min-w-[720px] table-auto">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider w-12">Type</th>
                            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Document</th>
                            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap" title="Version stored on the document record">File version</th>
                            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap" title="Size of the uploaded file">File size</th>
                            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Updated</th>
                            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-right">File preview</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="i in paginatedList" :key="i.id || i.slug">
                            <tr class="hover:bg-blue-50/40 transition-colors group">
                                <td class="px-3 py-3">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center"
                                         :class="getFileIconColor(i.file_type || i.doc_file || i.external_url || i.name || '')">
                                        <i class="text-sm" :class="getFileIcon(i.file_type || i.doc_file || i.external_url || i.name || '')"></i>
                                    </div>
                                </td>
                                <td class="px-3 py-3 min-w-0">
                                    <div class="font-bold text-slate-800 text-sm truncate" x-text="i.name || i.title || 'Untitled'"></div>
                                    <div class="text-[10px] text-slate-400 font-mono truncate" x-text="fileLabel(i)"></div>
                                </td>
                                <td class="px-3 py-3">
                                    <template x-if="fileVer(i)">
                                        <span class="font-mono text-[11px] font-bold bg-indigo-50 text-indigo-800 px-2 py-0.5 rounded border border-indigo-100"
                                              x-text="fileVer(i)"></span>
                                    </template>
                                    <template x-if="!fileVer(i)">
                                        <span class="text-xs text-slate-400">—</span>
                                    </template>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-700 whitespace-nowrap font-medium">
                                    <span x-text="fileSize(i) || (i.external_url ? 'Live link' : '—')"></span>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-500 whitespace-nowrap"
                                    x-text="i.updated_at || i.created_at || i.date || '—'"></td>
                                <td class="px-3 py-3 text-right whitespace-nowrap" @click.stop>
                                    <div class="inline-flex items-center gap-1 justify-end">
                                        <button type="button" @click="previewDoc(i)"
                                                class="px-2.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold"
                                                title="Open file preview">
                                            <i class="fa-solid fa-eye mr-1"></i> Preview
                                        </button>
                                        <button type="button" @click="shareItem('docs', i)" class="w-8 h-8 rounded-lg hover:bg-green-50 text-slate-500 hover:text-green-600" title="Share"><i class="fa-brands fa-whatsapp"></i></button>
                                        <?php if (!empty($isAdmin)): ?>
                                        <button type="button" @click="window.openModalEditor(true, 'docs', i)" class="w-8 h-8 rounded-lg hover:bg-blue-50 text-slate-500 hover:text-blue-600" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" @click="deleteItem(i.id, 'docs')" class="w-8 h-8 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>
</div>
