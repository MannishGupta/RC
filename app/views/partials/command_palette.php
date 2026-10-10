<?php
/**
 * Command palette — Ctrl/Cmd+K
 * Jump tabs, people, actions. Version: 20261002.24
 */
if (!defined('BASE_PATH')) {
    return;
}
?>
<div id="rc-cmdk"
     class="fixed inset-0 z-[2147483000] flex items-start justify-center pt-[10vh] px-4"
     style="display:none;background:rgba(15,23,42,0.5);backdrop-filter:blur(4px)"
     x-data="rcCmdk"
     x-show="visible"
     x-cloak
     @keydown.escape.window="if(visible){ close(); $event.preventDefault() }"
     @keydown.window="onGlobalKey($event)"
     role="presentation">
  <div class="w-full max-w-xl rounded-2xl border border-slate-200/80 bg-white shadow-2xl overflow-hidden dark:bg-slate-900 dark:border-slate-700"
       style="max-height:min(70vh,32rem)"
       @click.outside="close()"
       role="dialog"
       aria-modal="true"
       aria-label="Command palette"
       @keydown.arrow-down.prevent="move(1)"
       @keydown.arrow-up.prevent="move(-1)"
       @keydown.enter.prevent="go()">
    <div class="flex items-center gap-2 px-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/50">
      <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm shrink-0" aria-hidden="true"></i>
      <input type="search"
             x-ref="input"
             x-model="q"
             @input="filter()"
             placeholder="Search tabs, people, actions…  Ctrl+K"
             class="w-full py-3.5 text-sm bg-transparent outline-none text-slate-900 dark:text-slate-100 placeholder:text-slate-400"
             autocomplete="off"
             aria-autocomplete="list"
             aria-controls="rc-cmdk-list">
      <kbd class="hidden sm:inline text-[10px] font-bold text-slate-400 border border-slate-200 dark:border-slate-600 rounded px-1.5 py-0.5">ESC</kbd>
    </div>
    <ul id="rc-cmdk-list" class="overflow-y-auto py-1" style="max-height:min(55vh,24rem)" role="listbox">
      <template x-for="(item, idx) in filtered" :key="item.id + '-' + idx">
        <li role="option" :aria-selected="idx===active">
          <button type="button"
                  class="w-full text-left px-3 py-2.5 text-sm flex items-center gap-3 transition-colors"
                  :class="idx===active ? 'bg-blue-50 text-blue-950 dark:bg-blue-950/40 dark:text-blue-100' : 'text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'"
                  @mouseenter="active=idx"
                  @click="select(item)">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                  :class="idx===active ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'">
              <i class="fa-solid text-xs" :class="item.icon" aria-hidden="true"></i>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block font-semibold truncate" x-text="item.label"></span>
              <span class="block text-[11px] text-slate-500 dark:text-slate-400 truncate" x-text="item.hint || item.kind"></span>
            </span>
            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 shrink-0" x-text="item.kind"></span>
          </button>
        </li>
      </template>
      <li x-show="filtered.length===0" class="px-4 py-8 text-center text-sm text-slate-500">
        <i class="fa-solid fa-compass text-2xl text-slate-300 mb-2 block"></i>
        No matches — try a tab name or person
      </li>
    </ul>
    <div class="px-3 py-2 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-3 text-[10px] text-slate-400 font-medium">
      <span><kbd class="border border-slate-200 dark:border-slate-600 rounded px-1">↑↓</kbd> navigate</span>
      <span><kbd class="border border-slate-200 dark:border-slate-600 rounded px-1">↵</kbd> open</span>
      <span><kbd class="border border-slate-200 dark:border-slate-600 rounded px-1">⌘K</kbd> / <kbd class="border border-slate-200 dark:border-slate-600 rounded px-1">Ctrl+K</kbd></span>
      <button type="button" class="ml-auto text-blue-600 hover:underline" @click="showHelp()">Shortcuts</button>
    </div>
  </div>
</div>

<div id="rc-shortcuts-help" class="fixed inset-0 z-[2147483001] hidden items-center justify-center p-4" style="background:rgba(15,23,42,0.55)" role="dialog" aria-modal="true" aria-labelledby="rc-shortcuts-title">
  <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-md w-full border border-slate-200 dark:border-slate-700 p-5" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between mb-3">
      <h2 id="rc-shortcuts-title" class="text-base font-bold text-slate-900 dark:text-slate-100">Keyboard shortcuts</h2>
      <button type="button" class="w-9 h-9 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800" onclick="document.getElementById('rc-shortcuts-help').classList.add('hidden');document.getElementById('rc-shortcuts-help').classList.remove('flex')" aria-label="Close">×</button>
    </div>
    <ul class="text-sm text-slate-700 dark:text-slate-300 space-y-2">
      <li class="flex justify-between gap-4"><span>Command palette</span><kbd class="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">Ctrl/⌘ K</kbd></li>
      <li class="flex justify-between gap-4"><span>Close dialogs</span><kbd class="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">Esc</kbd></li>
      <li class="flex justify-between gap-4"><span>Focus list search</span><kbd class="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">/</kbd></li>
      <li class="flex justify-between gap-4"><span>Print</span><kbd class="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">Ctrl/⌘ P</kbd></li>
      <li class="flex justify-between gap-4"><span>This help</span><kbd class="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">?</kbd></li>
    </ul>
    <p class="mt-4 text-xs text-slate-500">Shortcuts ignore fields while you type in inputs (except Ctrl+K).</p>
  </div>
</div>

<script>
document.addEventListener('alpine:init', function () {
  if (window.__rcCmdkReg) return;
  window.__rcCmdkReg = true;
  Alpine.data('rcCmdk', function () {
    return {
    visible: false,
    q: '',
    active: 0,
    items: [],
    filtered: [],
    init() {
      this.rebuild();
      try {
        var tab = new URLSearchParams(location.search).get('tab') || 'team';
        this.pushRecent(tab);
      } catch (e) {}
    },
    onGlobalKey(e) {
      var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
      var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable);
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        this.open();
        return;
      }
      if (typing) return;
      if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
        e.preventDefault();
        var s = document.querySelector('header input[type="search"], input[type="search"].rc-list-search');
        if (s) { s.focus(); if (s.select) s.select(); }
        return;
      }
      if (e.key === '?' && !e.ctrlKey && !e.metaKey) {
        e.preventDefault();
        this.showHelp();
      }
    },
    showHelp() {
      var el = document.getElementById('rc-shortcuts-help');
      if (!el) return;
      el.classList.remove('hidden');
      el.classList.add('flex');
      el.onclick = function (ev) {
        if (ev.target === el) {
          el.classList.add('hidden');
          el.classList.remove('flex');
        }
      };
    },
    pushRecent(tabId) {
      try {
        var key = 'rc_recent_tabs';
        var arr = JSON.parse(localStorage.getItem(key) || '[]');
        if (!Array.isArray(arr)) arr = [];
        arr = arr.filter(function (x) { return x !== tabId; });
        arr.unshift(tabId);
        localStorage.setItem(key, JSON.stringify(arr.slice(0, 8)));
      } catch (e) {}
    },
    recentTabs() {
      try {
        var arr = JSON.parse(localStorage.getItem('rc_recent_tabs') || '[]');
        return Array.isArray(arr) ? arr : [];
      } catch (e) { return []; }
    },
    rebuild() {
      var items = [];
      var tabs = [
        { id: 'team', label: 'Human Capital Index', kind: 'Tab', icon: 'fa-users', href: '?tab=team', hint: 'People directory' },
        { id: 'docs', label: 'Document Vault', kind: 'Tab', icon: 'fa-folder-open', href: '?tab=docs', hint: 'Files & vault' },
        { id: 'bank', label: 'Treasury & Bank', kind: 'Tab', icon: 'fa-building-columns', href: '?tab=bank', hint: 'Accounts & UPI' },
        { id: 'locations', label: 'Standard Locations', kind: 'Tab', icon: 'fa-location-dot', href: '?tab=locations', hint: 'Premises' },
        { id: 'events', label: 'Corporate Calendar', kind: 'Tab', icon: 'fa-calendar-days', href: '?tab=events', hint: 'Events' },
        { id: 'cartags', label: 'Vehicle Inventory', kind: 'Tab', icon: 'fa-car', href: '?tab=cartags', hint: 'Fleet tags' },
        { id: 'statutory', label: 'Statutory Register', kind: 'Tab', icon: 'fa-scale-balanced', href: '?tab=statutory', hint: 'Compliance' },
        { id: 'tracking', label: 'Live Location', kind: 'Tab', icon: 'fa-location-crosshairs', href: '?tab=tracking', hint: 'Field ops' },
        { id: 'dispatch', label: 'Dispatch & Routes', kind: 'Tab', icon: 'fa-route', href: '?tab=dispatch', hint: 'Routes' },
        { id: 'numero', label: 'Vedic Numerology', kind: 'Tab', icon: 'fa-wand-magic-sparkles', href: '?tab=numero', hint: 'Reports' },
        { id: 'terms', label: 'Terms & Privacy', kind: 'Tab', icon: 'fa-shield-halved', href: '?tab=terms', hint: 'Governance' },
        { id: 'statistics', label: 'Statistics', kind: 'Tab', icon: 'fa-chart-line', href: '?tab=statistics', hint: 'Analytics' }
      ];
      var recent = this.recentTabs();
      recent.forEach(function (rid) {
        var t = tabs.find(function (x) { return x.id === rid; });
        if (t) {
          items.push(Object.assign({}, t, { id: 'recent-' + t.id, kind: 'Recent', hint: 'Recently opened' }));
        }
      });
      tabs.forEach(function (t) { items.push(t); });
      items.push(
        { id: 'act-print', label: 'Print current view', kind: 'Action', icon: 'fa-print', href: '#print', hint: 'Browser print dialog' },
        { id: 'act-theme', label: 'Open theme menu', kind: 'Action', icon: 'fa-circle-half-stroke', href: '#theme', hint: 'Light / Dark / System / Reserve' },
        { id: 'act-janam', label: 'Janam Patri module', kind: 'Module', icon: 'fa-om', href: 'janam_patri.php', hint: 'Kundli & Milan' },
        { id: 'act-blood', label: 'Blood report helper', kind: 'Module', icon: 'fa-droplet', href: 'blood_report.php', hint: 'Group facts' }
      );
      try {
        var team = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.data && window.__DASHBOARD_STATE__.data.team) || [];
        if (Array.isArray(team)) {
          team.slice(0, 120).forEach(function (p, i) {
            if (!p || !p.name) return;
            var slug = p.slug || p.id || '';
            items.push({
              id: 'p-' + (slug || i),
              label: String(p.name),
              kind: 'Person',
              icon: 'fa-user',
              hint: [p.designation_name || p.designation, p.department_name || p.department].filter(Boolean).join(' · ') || 'Team member',
              href: '?tab=team&q=' + encodeURIComponent(String(p.name))
            });
            if (slug) {
              items.push({
                id: 'ps-' + slug,
                label: 'Copy link · ' + String(p.name),
                kind: 'Share',
                icon: 'fa-link',
                hint: 'Digital business card URL',
                href: '#copy-share',
                slug: slug,
                person: p
              });
            }
          });
        }
      } catch (e) {}
      this.items = items;
      this.filter();
    },
    open() {
      this.rebuild();
      this.visible = true;
      this.q = '';
      this.filter();
      var self = this;
      this.$nextTick(function () {
        if (self.$refs.input) {
          self.$refs.input.focus();
          if (self.$refs.input.select) self.$refs.input.select();
        }
      });
    },
    close() {
      this.visible = false;
      this.q = '';
    },
    filter() {
      var q = (this.q || '').toLowerCase().trim();
      if (!q) {
        this.filtered = this.items.filter(function (i) {
          return i.kind === 'Recent' || i.kind === 'Tab' || i.kind === 'Action';
        }).slice(0, 14);
      } else {
        this.filtered = this.items.filter(function (i) {
          return (i.label + ' ' + (i.hint || '') + ' ' + i.kind + ' ' + (i.id || '')).toLowerCase().indexOf(q) !== -1;
        }).slice(0, 16);
      }
      this.active = 0;
    },
    move(d) {
      if (!this.filtered.length) return;
      this.active = (this.active + d + this.filtered.length) % this.filtered.length;
    },
    select(item) {
      if (!item) return;
      if (item.href === '#copy-share') {
        this.close();
        if (window.RcFavs) RcFavs.copyShare(item.person || { slug: item.slug });
        return;
      }
      if (item.href === '#print') { this.close(); window.print(); return; }
      if (item.href === '#theme') {
        this.close();
        var btn = document.querySelector('[aria-label*="Theme"], [title*="Theme"], button[data-rc-theme-menu]');
        if (btn) btn.click();
        return;
      }
      if (item.kind === 'Tab' || item.kind === 'Recent') {
        this.pushRecent(String(item.id).replace(/^recent-/, ''));
      }
      this.close();
      if (item.href) location.href = item.href;
    },
    go() {
      if (this.filtered[this.active]) this.select(this.filtered[this.active]);
    }
    };
  });
});
document.addEventListener('rc-cmdk-open', function () {
  var el = document.querySelector('#rc-cmdk');
  if (!el) return;
  try {
    if (window.Alpine && Alpine.$data) Alpine.$data(el).open();
    else if (el._x_dataStack && el._x_dataStack[0]) el._x_dataStack[0].open();
  } catch (e) {}
});
</script>
