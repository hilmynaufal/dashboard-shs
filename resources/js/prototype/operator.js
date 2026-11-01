import { statuses, rupiah, escapeHtml as e, editable, ready, itemProblems, makePackages, importFields, mapHeaders, importRows, parsePrice, csvCell } from './data.js';

const root = document.querySelector('#operator-prototype');
const icons = {
    building: '<path d="M3 21h18M5 21V5l7-3 7 3v16M9 21v-5h6v5M9 7h.01M15 7h.01M9 11h.01M15 11h.01"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5"/>',
    message: '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/>',
    chart: '<path d="M3 3v18h18M8 16v-5M13 16V7M18 16v-8"/>',
    search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    right: '<path d="m9 5 7 7-7 7"/>',
    left: '<path d="m15 5-7 7 7 7"/>',
    arrow: '<path d="M5 12h14m-6-6 6 6-6 6"/>',
    upload: '<path d="M12 16V3m-5 5 5-5 5 5M3 16v4a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-4"/>',
    download: '<path d="M12 3v13m-5-5 5 5 5-5M3 16v4a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-4"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    check: '<path d="m5 12 4 4L19 6"/>',
    circleCheck: '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    alert: '<path d="m10.3 3.9-8 14a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0M12 9v4M12 17h.01"/>',
    help: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 4.2 1.8c-1 .5-1.7 1.2-1.7 2.2M12 17h.01"/>',
    folder: '<path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
    menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    close: '<path d="m6 6 12 12M6 18 18 6"/>',
    edit: '<path d="m16 3 5 5-13 13H3v-5zM14 5l5 5"/>',
    trash: '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>',
    link: '<path d="m10 13 4-4M8 16l-1 1a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0m2 1 1-1a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0"/>',
    shield: '<path d="M12 3 3 7v5c0 5 9 10 9 10s9-5 9-10V7zM8 12l3 3 5-6"/>',
    send: '<path d="m22 2-7 20-4-9-9-4 20-7M22 2 11 13"/>',
    sort: '<path d="M8 4v16m-4-4 4 4 4-4M16 20V4m-4 4 4-4 4 4"/>',
};
const icon = (name, cls = '') => `<svg class="icon ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name] || icons.file}</svg>`;
const button = (label, action, glyph, kind = 'secondary', attrs = '') => `<button type="button" class="btn ${kind}" data-action="${action}" ${attrs}>${glyph ? icon(glyph) : ''}<span>${label}</span></button>`;
const badge = status => `<span class="badge ${statuses[status].tone}"><span class="status-dot"></span>${statuses[status].label}</span>`;
const badgeItem = item => `<span class="badge ${ready(item) ? 'green' : 'warning'}">${icon(ready(item) ? 'check' : 'clock')}${ready(item) ? 'Lengkap' : 'Belum lengkap'}</span>`;
const href = path => `#/${path}`;
const state = { packages: makePackages(), filter: 'all', query: '', type: '', listPage: 1, itemQuery: '', itemFilter: 'all', itemPage: 1, tab: 'items', dialog: null, editor: null, import: null, urls: new Set() };
let toastTimer;
let previousPath = '';

root.innerHTML = `<div class="drawer-scrim" data-action="close-menu"></div>
<aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
    <a href="${href('usulan')}" class="brand"><span class="brand-mark">${icon('building')}</span><span><strong>SHS<span class="brand-slash"> / </span>SBU</strong><small>Ruang usulan</small></span></a>
    <button class="org-switch" type="button" data-action="organization"><span class="org-avatar">PD</span><span><strong>Perangkat Daerah</strong><small>Organisasi contoh</small></span>${icon('down')}</button>
    <p class="nav-section">RUANG KERJA</p>
    <nav><a href="${href('ringkasan')}" data-nav="ringkasan">${icon('grid')}Ringkasan</a><a href="${href('usulan')}" data-nav="usulan">${icon('folder')}Usulan<span class="nav-count" id="nav-total"></span></a><a href="${href('tanggapan')}" data-nav="tanggapan">${icon('message')}Tanggapan<span class="nav-count attention">1</span></a><a href="${href('rekap')}" data-nav="rekap">${icon('chart')}Rekap</a></nav>
    <div class="sidebar-bottom"><div class="period-card">${icon('calendar')}<div><span>Periode pengusulan</span><strong>Tahun anggaran 2027</strong><small><i></i> Terbuka · contoh</small></div></div>
    <button type="button" class="sidebar-help" data-action="help">${icon('help')}Panduan pengusulan</button>
    <button type="button" class="user-button" data-action="profile"><span class="avatar">OD</span><span><strong>Operator Demo</strong><small>Operator SKPD</small></span>${icon('down')}</button></div>
</aside>
<div class="app-main"><header class="topbar"><button type="button" class="icon-btn menu-toggle" data-action="menu" aria-label="Buka navigasi" aria-controls="sidebar" aria-expanded="false">${icon('menu')}</button><div class="breadcrumb" id="breadcrumb"></div><div class="topbar-actions"><button class="demo-tag" type="button" data-action="demo"><span></span>Prototipe · data contoh</button><button type="button" class="icon-btn" data-action="help" aria-label="Bantuan">${icon('help')}</button></div></header>
<main id="main-content" tabindex="-1"></main><footer class="app-footer"><span>Inspektorat Daerah</span><span>Ruang pengusulan SHS & SBU</span></footer></div>
<div class="toast" id="toast" role="status" aria-live="polite" hidden></div><dialog id="app-dialog" aria-labelledby="dialog-title"></dialog>`;

function toast(message) {
    const node = document.querySelector('#toast');
    node.innerHTML = `${icon('circleCheck')}<span>${e(message)}</span>`;
    node.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { node.hidden = true; }, 4800);
}

function navigate(path) { location.hash = href(path).slice(1); }
function currentRoute() { return location.hash.replace(/^#\/?/, '').split('/').filter(Boolean); }
function getPackage(id) { return state.packages.find(pkg => pkg.id === id); }
function currentPackage() { return getPackage(currentRoute()[1]); }
function countReady(pkg) { return pkg.items.filter(ready).length; }
function closeMenu() { root.classList.remove('menu-open'); document.querySelector('.menu-toggle').setAttribute('aria-expanded', 'false'); if (matchMedia('(max-width: 1000px)').matches) document.querySelector('#sidebar').inert = true; }
function refreshSidebar() { if (matchMedia('(max-width: 1000px)').matches && !root.classList.contains('menu-open')) document.querySelector('#sidebar').inert = true; else document.querySelector('#sidebar').inert = false; }
function empty(title, text, action = '') { return `<div class="empty-state">${icon('search')}<h3>${title}</h3><p>${text}</p>${action}</div>`; }
function heading(title, description, actions = '') { return `<div class="page-heading"><div><h1>${e(title)}</h1><p>${e(description)}</p></div><div class="heading-actions">${actions}</div></div>`; }
function search(id, value, placeholder) { return `<label class="search-field">${icon('search')}<span class="sr-only">${placeholder}</span><input type="search" id="${id}" value="${e(value)}" placeholder="${placeholder}" autocomplete="off"></label>`; }

function render() {
    const focusId = document.activeElement?.id;
    const selection = document.activeElement?.selectionStart;
    const path = currentRoute();
    const page = path[0] || 'usulan';
    const pkg = getPackage(path[1]);
    const changed = previousPath !== location.hash;
    if (changed) { state.itemQuery = ''; state.itemFilter = 'all'; state.itemPage = 1; state.tab = 'items'; closeMenu(); }
    previousPath = location.hash;
    document.querySelectorAll('[data-nav]').forEach(link => {
        const active = link.dataset.nav === page;
        link.classList.toggle('active', active);
        if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
    });
    document.querySelector('#nav-total').textContent = state.packages.length;
    const title = { usulan: 'Usulan', ringkasan: 'Ringkasan', tanggapan: 'Tanggapan', rekap: 'Rekap' }[page] || 'Usulan';
    document.querySelector('#breadcrumb').innerHTML = `<span>Ruang kerja</span>${icon('right')}<a href="${href(page)}">${title}</a>${pkg ? `${icon('right')}<strong>${e(pkg.number)}</strong>` : ''}`;
    document.title = `${pkg ? pkg.title : title + ' SKPD'} · SHS/SBU`;
    let html;
    if (page === 'usulan' && path[1] && !pkg) html = empty('Paket tidak ditemukan', 'Kembali ke daftar untuk memilih paket yang tersedia.', `<a class="btn secondary" href="${href('usulan')}">Kembali ke daftar</a>`);
    else if (pkg && path[2] === 'item') html = renderEditor(pkg, path[3]);
    else if (pkg && path[2] === 'impor') html = renderImport(pkg);
    else if (pkg && path[2] === 'periksa') html = renderCheck(pkg);
    else if (pkg && path[2] === 'terkirim') html = renderSuccess(pkg);
    else if (pkg) html = renderPackage(pkg);
    else if (page === 'tanggapan') html = renderResponses();
    else if (page === 'ringkasan') html = renderOverview();
    else if (page === 'rekap') html = renderRecap();
    else html = renderList();
    document.querySelector('#main-content').innerHTML = html;
    refreshSidebar();
    if (!changed && focusId) {
        const target = document.getElementById(focusId);
        if (target) { target.focus({ preventScroll: true }); if (selection != null && ['search', 'text'].includes(target.type)) target.setSelectionRange(selection, selection); }
    }
    if (changed) { window.scrollTo({ top: 0 }); document.querySelector('#main-content').focus({ preventScroll: true }); }
}

function renderList() {
    const all = state.packages;
    const filtered = all.filter(pkg => (state.filter === 'all' || pkg.status === state.filter) && (!state.type || pkg.type === state.type) && `${pkg.title} ${pkg.number}`.toLowerCase().includes(state.query.toLowerCase()));
    const perPage = 8;
    const max = Math.max(1, Math.ceil(filtered.length / perPage));
    state.listPage = Math.min(state.listPage, max);
    const rows = filtered.slice((state.listPage - 1) * perPage, state.listPage * perPage);
    const stats = [
        ['folder', all.length, 'Paket usulan'], ['file', all.reduce((sum, p) => sum + p.items.length, 0), 'Item diusulkan'],
        ['message', all.filter(p => p.status === 'correction').length, 'Perlu perbaikan'], ['clock', all.filter(p => p.status === 'draft').length, 'Masih berupa draf'],
    ];
    const attention = all.find(p => p.status === 'correction');
    return `${heading('Usulan SKPD', 'Kelola usulan harga, dari draf hingga hasil reviu.', button('Buat usulan', 'create-package', 'plus', 'primary'))}
    <div class="inline-stats">${stats.map(([glyph, value, label]) => `<div class="inline-stat"><span class="stat-icon">${icon(glyph)}</span><span><strong>${value}</strong><small>${label}</small></span></div>`).join('')}<div class="year-note">${icon('calendar')}Tahun anggaran <strong>2027</strong></div></div>
    ${attention ? `<div class="attention-banner"><div class="attention-copy">${icon('message')}<span><strong>Ada usulan yang perlu Anda perbaiki.</strong><span> Periksa catatan dan lengkapi sebelum dikirim kembali.</span></span></div><a href="${href(`usulan/${attention.id}`)}">Buka usulan ${icon('arrow')}</a></div>` : ''}
    <div class="list-tools"><div class="status-filters" aria-label="Filter status"><button type="button" data-action="filter" data-id="all" class="filter-chip ${state.filter === 'all' ? 'selected' : ''}" aria-pressed="${state.filter === 'all'}">Semua <span>${all.length}</span></button>${Object.entries(statuses).map(([key, value]) => `<button type="button" class="filter-chip ${state.filter === key ? 'selected' : ''}" data-action="filter" data-id="${key}" aria-pressed="${state.filter === key}"><i class="tiny-dot ${value.tone}"></i>${value.label} <span>${all.filter(p => p.status === key).length}</span></button>`).join('')}</div></div>
    <section class="table-panel" aria-label="Daftar paket usulan"><div class="table-toolbar">${search('package-search', state.query, 'Cari nama atau nomor usulan…')}<div class="toolbar-end"><label class="select-wrap"><span class="sr-only">Jenis usulan</span><select id="type-filter"><option value="">Semua jenis</option><option ${state.type === 'SHS' ? 'selected' : ''}>SHS</option><option ${state.type === 'SBU' ? 'selected' : ''}>SBU</option></select></label>${button('Urutkan', 'sort-packages', 'sort', 'ghost')}</div></div>
    ${rows.length ? `<div class="table-scroll"><table class="package-table"><thead><tr><th scope="col">Paket usulan</th><th scope="col">Jenis</th><th scope="col" class="number">Item</th><th scope="col">Kelengkapan</th><th scope="col">Status</th><th scope="col">Diperbarui</th><th scope="col"><span class="sr-only">Buka paket</span></th></tr></thead><tbody>${rows.map(pkg => `<tr><td><a class="package-link" href="${href(`usulan/${pkg.id}`)}"><span class="package-icon ${pkg.type === 'SBU' ? 'purple-soft' : ''}">${icon('file')}</span><span><strong>${e(pkg.title)}</strong><small>${e(pkg.number)} <span>·</span> Versi ${pkg.version}</small></span></a></td><td><span class="type-label">${pkg.type}</span></td><td class="number">${pkg.items.length}</td><td>${progress(pkg)}</td><td>${badge(pkg.status)}</td><td class="muted nowrap">${e(pkg.updated)}</td><td><a class="icon-btn" href="${href(`usulan/${pkg.id}`)}" aria-label="Buka ${e(pkg.title)}">${icon('right')}</a></td></tr>`).join('')}</tbody></table></div>` : empty('Tidak ada usulan yang cocok', 'Coba kata kunci lain atau tampilkan semua status.', button('Hapus filter', 'clear-filters', null, 'secondary'))}
    <div class="table-footer"><span>Menampilkan ${filtered.length ? (state.listPage - 1) * perPage + 1 : 0}–${Math.min(state.listPage * perPage, filtered.length)} dari ${filtered.length} paket</span><div class="pagination">${button('Sebelumnya', 'list-prev', 'left', 'icon-text', state.listPage <= 1 ? 'disabled' : '')}<span class="page-number">${state.listPage}</span>${button('Berikutnya', 'list-next', 'right', 'icon-text', state.listPage >= max ? 'disabled' : '')}</div></div></section>
    <div class="quiet-note">${icon('shield')}Usulan dan dokumen ditampilkan sesuai lingkup SKPD Anda.</div>`;
}

function progress(pkg) {
    const count = countReady(pkg);
    return `<div class="progress-cell"><span class="progress-track"><i style="width:${pkg.items.length ? count / pkg.items.length * 100 : 0}%"></i></span><span>${count}<span class="muted">/${pkg.items.length}</span></span></div>`;
}

function packageTabs(pkg) {
    return `<div class="detail-tabs" role="tablist" aria-label="Bagian paket">${[['items', 'Item usulan', pkg.items.length], ['notes', 'Catatan', pkg.items.filter(i => i.note).length], ['history', 'Riwayat', pkg.history.length]].map(([key, label, count]) => `<button type="button" role="tab" aria-selected="${state.tab === key}" class="${state.tab === key ? 'selected' : ''}" data-action="tab" data-id="${key}">${label}<span>${count}</span></button>`).join('')}</div>`;
}

function renderPackage(pkg) {
    const canEdit = editable(pkg);
    const filtered = pkg.items.filter(item => (!state.itemQuery || `${item.name} ${item.code}`.toLowerCase().includes(state.itemQuery.toLowerCase())) && (state.itemFilter !== 'missing' || !ready(item)));
    const max = Math.max(1, Math.ceil(filtered.length / 8));
    state.itemPage = Math.min(state.itemPage, max);
    const shown = filtered.slice((state.itemPage - 1) * 8, state.itemPage * 8);
    return `<a class="back-link" href="${href('usulan')}">${icon('left')}Semua usulan</a>${heading(pkg.title, `${pkg.number} · ${pkg.type} · Tahun anggaran ${pkg.year}`, canEdit ? button('Periksa & kirim', 'check-package', 'send', 'primary') : badge(pkg.status))}
    <section class="package-summary"><div><span class="summary-label">Status paket</span>${badge(pkg.status)}</div><div><span class="summary-label">Item usulan</span><strong>${pkg.items.length} <span>item</span></strong></div><div><span class="summary-label">Kelengkapan</span><strong>${countReady(pkg)} <span>dari ${pkg.items.length} item lengkap</span></strong></div><div><span class="summary-label">Versi aktif</span><strong>Versi ${pkg.version}</strong></div><div class="summary-description">${icon('folder')}<span>${e(pkg.description)}</span></div></section>
    ${pkg.status === 'correction' ? `<div class="attention-banner"><div class="attention-copy">${icon('message')}<span><strong>Lengkapi sesuai catatan BKAD.</strong> Ada ${pkg.items.filter(i => i.note).length} item dengan catatan yang perlu ditanggapi.</span></div>${button('Lihat catatan', 'show-notes', null, 'text-button')}</div>` : ''}
    ${!canEdit ? `<div class="readonly-note">${icon('shield')}Paket sudah diajukan. Data versi ini ditampilkan untuk dibaca.</div>` : ''}
    <section class="table-panel">${packageTabs(pkg)}${state.tab === 'notes' ? renderNotes(pkg) : state.tab === 'history' ? renderHistory(pkg) : `<div class="table-toolbar">${search('item-search', state.itemQuery, 'Cari uraian atau kode barang…')}<div class="toolbar-end"><label class="select-wrap"><span class="sr-only">Kelengkapan item</span><select id="item-filter"><option value="all">Semua item</option><option value="missing" ${state.itemFilter === 'missing' ? 'selected' : ''}>Belum lengkap</option></select></label>${canEdit ? `${button('Impor Excel', 'import', 'upload')}${button('Tambah item', 'new-item', 'plus', 'primary')}` : ''}</div></div>
    ${shown.length ? `<div class="table-scroll"><table class="item-table"><thead><tr><th scope="col" class="number">No.</th><th scope="col">Uraian & spesifikasi</th><th scope="col">Satuan</th><th scope="col" class="number">Harga usulan</th><th scope="col">Bukti</th><th scope="col">Kelengkapan</th><th scope="col"><span class="sr-only">Detail item</span></th></tr></thead><tbody>${shown.map((item, index) => `<tr><td class="number muted">${(state.itemPage - 1) * 8 + index + 1}</td><td><a class="item-name" href="${href(`usulan/${pkg.id}/item/${item.id}`)}">${e(item.name || 'Item tanpa uraian')}${item.note ? `<span class="note-marker" title="Ada catatan">${icon('message')}</span>` : ''}</a><small class="cell-detail">${e(item.spec)}</small><small class="cell-code">${e(item.code || 'Kode belum diisi')}</small></td><td>${e(item.unit || '—')}</td><td class="number price">${rupiah(item.price)}</td><td><span class="evidence-count ${item.evidence.length ? '' : 'missing'}">${icon('link')}${item.evidence.length ? `${item.evidence.length} berkas` : 'Belum ada'}</span></td><td>${badgeItem(item)}</td><td><a href="${href(`usulan/${pkg.id}/item/${item.id}`)}" class="icon-btn" aria-label="${canEdit ? 'Edit' : 'Lihat'} ${e(item.name)}">${icon(canEdit ? 'edit' : 'right')}</a></td></tr>`).join('')}</tbody></table></div>` : empty(pkg.items.length ? 'Item tidak ditemukan' : 'Mulai tambahkan item usulan', pkg.items.length ? 'Gunakan kata kunci lain atau ubah filter kelengkapan.' : 'Isi satu per satu atau gunakan Excel untuk menambahkan banyak item.', canEdit ? button('Tambah item', 'new-item', 'plus', 'primary') : '')}
    <div class="table-footer"><span>${filtered.length} item · ${countReady(pkg)} lengkap dalam paket</span><div class="pagination">${button('Sebelumnya', 'item-prev', 'left', 'icon-text', state.itemPage === 1 ? 'disabled' : '')}<span class="page-number">${state.itemPage}</span>${button('Berikutnya', 'item-next', 'right', 'icon-text', state.itemPage >= max ? 'disabled' : '')}</div></div>`}</section>`;
}

function renderNotes(pkg) {
    const notes = pkg.items.filter(item => item.note);
    return notes.length ? `<div class="notes-list">${notes.map(item => `<article class="note-row"><span class="note-icon">${icon('message')}</span><div><div class="note-header"><strong>BKAD <span>· Catatan administrasi</span></strong><small>31 Okt 2026</small></div><h3>${e(item.name)}</h3><p>${e(item.note)}</p>${item.response ? `<div class="response-text"><strong>Tanggapan Anda</strong><p>${e(item.response)}</p></div>` : ''}${editable(pkg) ? `<form class="response-form" data-item="${e(item.id)}"><label for="response-${e(item.id)}">Tanggapan untuk catatan ini</label><textarea id="response-${e(item.id)}" name="response" required maxlength="2000" placeholder="Jelaskan perbaikan atau dokumen yang dilengkapi…">${e(item.response || '')}</textarea><div><a class="btn secondary" href="${href(`usulan/${pkg.id}/item/${item.id}`)}">${icon('edit')}Perbaiki item</a><button class="btn primary" type="submit">Simpan tanggapan</button></div></form>` : ''}</div></article>`).join('')}</div>` : empty('Belum ada catatan', 'Catatan resmi atas usulan akan muncul di sini.');
}

function renderHistory(pkg) {
    return `<ol class="timeline">${[...pkg.history].reverse().map(event => `<li><span class="timeline-dot">${icon('check')}</span><div><strong>${e(event.title)}</strong><p>${e(event.description)}</p></div><time>${e(event.date)}</time></li>`).join('')}</ol>`;
}

function renderEditor(pkg, id) {
    const original = pkg.items.find(i => i.id === id);
    if (id !== 'baru' && !original) return empty('Item tidak ditemukan', 'Kembali ke paket untuk memilih item.', `<a class="btn secondary" href="${href(`usulan/${pkg.id}`)}">Kembali ke paket</a>`);
    if (state.editor?.route !== `${pkg.id}/${id}`) state.editor = { route: `${pkg.id}/${id}`, ...(original ? { ...original, evidence: [...original.evidence] } : { id: crypto.randomUUID(), name: '', spec: '', code: '', account: '', unit: '', price: '', evidence: [], note: '' }) };
    const item = state.editor;
    const canEdit = editable(pkg);
    const field = (key, label, help = '', type = 'text', full = false) => `<div class="form-field ${full ? 'full-width' : ''}"><label for="edit-${key}">${label}${key === 'name' ? ' <span class="required">*</span>' : ''}</label><input id="edit-${key}" name="${key}" type="${type}" value="${e(item[key])}" ${key === 'name' ? 'required maxlength="250"' : key === 'price' ? 'min="0.01" step="0.01" inputmode="decimal"' : 'maxlength="2000"'} ${canEdit ? '' : 'readonly'}>${help ? `<small>${help}</small>` : ''}</div>`;
    return `<a class="back-link" href="${href(`usulan/${pkg.id}`)}">${icon('left')}Kembali ke paket</a>${heading(original ? (canEdit ? 'Edit item usulan' : 'Detail item usulan') : 'Tambah item usulan', `${pkg.title} · ${pkg.type}`)}
    <form id="item-form"><div class="editor-grid"><section class="form-panel"><div class="section-heading"><span class="section-icon">${icon('file')}</span><div><h2>Informasi item</h2><p>Isi uraian yang jelas agar item mudah diperiksa.</p></div></div>
    ${item.note ? `<div class="field-note">${icon('message')}<div><strong>Catatan BKAD</strong><p>${e(item.note)}</p></div></div>` : ''}
    <div class="form-grid">${field('name', 'Uraian barang / biaya', '', 'text', true)}<div class="form-field full-width"><label for="edit-spec">Spesifikasi</label><textarea id="edit-spec" name="spec" rows="3" maxlength="4000" placeholder="Ukuran, bahan, kapasitas, atau rincian lain…" ${canEdit ? '' : 'readonly'}>${e(item.spec)}</textarea><small>Jelaskan karakteristik item secara terukur.</small></div>${field('code', 'Kode barang', 'Kode dipertahankan sebagai teks.')}${field('account', 'Kode rekening', 'Pisahkan dengan koma jika lebih dari satu.')}${field('unit', 'Satuan', 'Contoh: Unit, Rim, atau Paket.')}${field('price', 'Harga usulan (Rp)', 'Harga per satuan, termasuk komponen sesuai usulan.', 'number')}</div>
    <div class="form-bottom-note">${icon('help')}Draf dapat disimpan sebelum seluruh data dan bukti lengkap.</div></section>
    <aside class="evidence-panel"><div class="section-heading"><span class="section-icon">${icon('link')}</span><div><h2>Bukti pendukung</h2><p>Survei harga dan dokumen per item.</p></div></div>
    ${canEdit ? `<label class="upload-zone compact" for="evidence-input">${icon('upload')}<strong>Tambahkan bukti</strong><span>Pilih berkas PDF, JPG, atau PNG</span><small>Maks. 10 MB per berkas · hanya di tab ini</small><input type="file" id="evidence-input" accept="application/pdf,image/jpeg,image/png" multiple class="sr-only"></label>` : ''}
    <div id="file-error" class="inline-error" role="alert" hidden></div><div class="evidence-files">${item.evidence.map((file, index) => `<div class="evidence-file"><span class="file-symbol">${icon('file')}</span><div><strong>${e(file.name)}</strong><small>${file.sample ? 'Lampiran contoh' : `${Math.max(1, Math.round(file.size / 1024))} KB · lokal`}</small>${file.url ? `<button type="button" class="text-button" data-action="preview-file" data-id="${index}">Lihat berkas</button>` : ''}</div>${canEdit ? `<button type="button" class="icon-btn" data-action="remove-file" data-id="${index}" aria-label="Hapus ${e(file.name)}">${icon('close')}</button>` : ''}</div>`).join('') || '<p class="muted evidence-empty">Belum ada bukti untuk item ini.</p>'}</div>
    <div class="evidence-tip">${icon('shield')}Bukti terhubung dengan item ini. Pada aplikasi operasional, akses mengikuti kewenangan pengguna.</div></aside></div>
    <div class="form-actionbar"><a class="btn secondary" href="${href(`usulan/${pkg.id}`)}">${canEdit ? 'Batal' : 'Kembali ke paket'}</a>${canEdit ? `<button type="submit" class="btn primary">${icon('check')}Simpan item</button>` : ''}</div></form>`;
}

function renderImport(pkg) {
    if (!editable(pkg)) return empty('Paket sudah diajukan', 'Impor hanya tersedia pada draf atau paket yang dikembalikan.', `<a href="${href(`usulan/${pkg.id}`)}" class="btn secondary">Kembali ke paket</a>`);
    const imp = state.import;
    const active = imp?.pkgId === pkg.id ? imp : null;
    return `<a class="back-link" href="${href(`usulan/${pkg.id}`)}">${icon('left')}Kembali ke paket</a>${heading('Impor item dari Excel', 'Periksa hasil pembacaan sebelum menambahkannya ke paket.')}
    <ol class="stepper"><li class="complete"><span>1</span>Pilih berkas</li><li class="${active?.rows ? 'current' : ''}"><span>2</span>Periksa data</li><li><span>3</span>Simpan ke paket</li></ol>
    ${!active?.rows ? `<section class="import-start"><div class="import-upload"><span class="large-file">${icon('file')}</span><h2>Bawa data Anda dari Excel</h2><p>Gunakan satu baris untuk setiap item. Kolom dapat dipetakan setelah berkas dibaca.</p><label for="excel-input" class="upload-zone" id="excel-drop">${icon('upload')}<strong>${active?.loading ? 'Membaca berkas…' : 'Letakkan berkas Excel di sini'}</strong><span>atau klik untuk memilih berkas</span><small>.xlsx · maksimum 5 MB dan 2.000 baris untuk prototipe</small><input id="excel-input" type="file" accept=".xlsx" class="sr-only" ${active?.loading ? 'disabled' : ''}></label>${active?.error ? `<div class="inline-error" role="alert">${e(active.error)}</div>` : ''}<div class="upload-alternatives">${button('Coba data contoh', 'sample-import', null, 'text-button')}${button('Unduh contoh CSV', 'download-template', 'download', 'text-button')}</div></div><aside class="import-guide"><h3>Sebelum mengimpor</h3><div>${icon('check')}<p>Sertakan uraian, spesifikasi, kode barang, rekening, satuan, dan harga.</p></div><div>${icon('check')}<p>Pastikan kode disimpan sebagai teks agar angka nol di awal tetap terbaca.</p></div><div>${icon('check')}<p>Bukti survei dilampirkan pada masing-masing item setelah impor.</p></div><div class="local-file-note">${icon('shield')}Berkas dibaca di browser. Tidak diunggah ke server pada prototipe ini.</div></aside></section>` : renderImportPreview(active)} `;
}

function refreshImport() {
    const imp = state.import;
    const sheet = imp.sheets[imp.sheetIndex];
    const headerIndex = imp.headerRow - 1;
    imp.headers = sheet.data[headerIndex] || [];
    const source = sheet.data.slice(headerIndex + 1).map((row, index) => ({ row, line: headerIndex + index + 2 })).filter(({ row }) => row.some(value => value != null && String(value).trim()));
    imp.rows = source.map(({ row, line }) => importRows([row], imp.mapping, line)[0]);
    imp.page = 1;
    imp.omitted = new Set();
}

function renderImportPreview(imp) {
    const activeRows = imp.rows.filter(row => !imp.omitted.has(row.row));
    const invalid = activeRows.filter(row => row.errors.length);
    const shown = (imp.onlyErrors ? activeRows.filter(row => row.errors.length) : activeRows);
    const max = Math.max(1, Math.ceil(shown.length / 8));
    imp.page = Math.min(imp.page || 1, max);
    return `<section class="table-panel import-preview"><div class="import-filebar"><span class="file-symbol">${icon('file')}</span><div><strong>${e(imp.name)}</strong><small>${imp.rows.length} baris terbaca · ${imp.omitted.size} dilewati</small></div>${button('Ganti berkas', 'reset-import', 'upload', 'secondary')}</div>
    <div class="mapping-bar"><label>Lembar<select id="import-sheet">${imp.sheets.map((sheet, index) => `<option value="${index}" ${index === imp.sheetIndex ? 'selected' : ''}>${e(sheet.sheet)}</option>`).join('')}</select></label><label>Baris judul kolom<select id="header-row">${imp.sheets[imp.sheetIndex].data.slice(0, 10).map((_, index) => `<option value="${index + 1}" ${imp.headerRow === index + 1 ? 'selected' : ''}>Baris ${index + 1}</option>`).join('')}</select></label><span>Pilih kolom sumber yang sesuai di bawah.</span></div>
    <div class="mapping-grid">${importFields.map(([key, label]) => `<label>${label}<select data-mapping="${key}" aria-label="Kolom sumber untuk ${label}"><option value="-1">Pilih kolom</option>${imp.headers.map((header, index) => `<option value="${index}" ${imp.mapping[key] === index ? 'selected' : ''}>${String.fromCharCode(65 + index)} · ${e(header || 'Tanpa judul')}</option>`).join('')}</select></label>`).join('')}</div>
    <div class="import-status ${invalid.length ? 'has-errors' : ''}"><div role="status">${icon(invalid.length ? 'alert' : 'circleCheck')}<span><strong>${invalid.length ? `${invalid.length} baris perlu diperbaiki` : `${activeRows.length} baris siap ditambahkan`}</strong><small>${invalid.length ? 'Edit sel yang bermasalah, petakan ulang kolom, atau lewati baris pengelompokan.' : 'Bukti pendukung dilengkapi setelah item masuk ke paket.'}</small></span></div><label class="checkbox-label"><input type="checkbox" id="only-import-errors" ${imp.onlyErrors ? 'checked' : ''}> Hanya yang bermasalah</label></div>
    ${shown.length ? `<div class="table-scroll"><table class="import-table"><thead><tr><th>Baris</th>${importFields.map(([, label]) => `<th>${label}</th>`).join('')}<th>Hasil pemeriksaan</th><th><span class="sr-only">Lewati baris</span></th></tr></thead><tbody>${shown.slice((imp.page - 1) * 8, imp.page * 8).map(row => `<tr class="${row.errors.length ? 'error-row' : ''}"><td class="number">${row.row}</td>${importFields.map(([key, label]) => { const invalidField = key === 'price' ? !(Number.isFinite(row.price) && row.price > 0) : !String(row[key] ?? '').trim(); return `<td><input id="import-${row.row}-${key}" aria-label="${label} baris ${row.row}" aria-invalid="${invalidField}" ${invalidField ? `aria-describedby="import-errors-${row.row}"` : ''} data-import-row="${row.row}" data-key="${key}" value="${e(row.inputValues?.[key] ?? (key === 'price' && !Number.isFinite(row.price) ? '' : row[key]))}" class="import-cell ${invalidField ? 'invalid' : ''}" ${key === 'price' ? 'inputmode="decimal"' : ''}></td>`; }).join('')}<td>${row.errors.length ? `<span id="import-errors-${row.row}" class="import-error-text">${e(row.errors.join('; '))}</span>` : `<span class="valid-text">${icon('check')}Siap</span>`}</td><td><button type="button" class="icon-btn" data-action="omit-row" data-id="${row.row}" aria-label="Lewati baris ${row.row}">${icon('close')}</button></td></tr>`).join('')}</tbody></table></div>` : empty('Tidak ada baris pada tampilan ini', 'Ubah filter atau ganti lembar untuk memeriksa data lainnya.')}
    <div class="table-footer"><span>${activeRows.length} baris dipilih · ${activeRows.length - invalid.length} valid</span><div class="pagination">${button('Sebelumnya', 'import-prev', 'left', 'icon-text', imp.page === 1 ? 'disabled' : '')}<span class="page-number">${imp.page}</span>${button('Berikutnya', 'import-next', 'right', 'icon-text', imp.page >= max ? 'disabled' : '')}</div></div></section>
    <div class="form-actionbar"><a class="btn secondary" href="${href(`usulan/${imp.pkgId}`)}">Batal</a>${button(`Tambahkan ${activeRows.length} item`, 'save-import', 'check', 'primary', invalid.length || !activeRows.length ? 'disabled' : '')}</div>`;
}

function renderCheck(pkg) {
    const problems = pkg.items.filter(item => !ready(item));
    const missingResponses = pkg.items.filter(item => item.note && !item.response?.trim());
    const blocked = !pkg.items.length || problems.length || missingResponses.length;
    return `<a class="back-link" href="${href(`usulan/${pkg.id}`)}">${icon('left')}Kembali ke paket</a>${heading('Periksa sebelum mengirim', 'Pastikan data, bukti, dan tanggapan sudah lengkap.')}
    <div class="check-layout"><section class="form-panel"><div class="check-heading ${blocked ? 'warning' : 'green'}">${icon(blocked ? 'alert' : 'circleCheck')}<div><h2>${blocked ? 'Masih ada yang perlu dilengkapi' : 'Paket siap diajukan'}</h2><p>${countReady(pkg)} dari ${pkg.items.length} item telah lengkap.</p></div></div>
    ${!pkg.items.length ? empty('Paket belum memiliki item', 'Tambahkan item sebelum mengirim usulan.', button('Tambah item', 'new-item', 'plus', 'primary')) : ''}
    ${problems.map(item => `<div class="check-item"><div><strong>${e(item.name)}</strong><p>${e(itemProblems(item).join(' · '))}</p></div><a class="btn secondary" href="${href(`usulan/${pkg.id}/item/${item.id}`)}">Lengkapi ${icon('right')}</a></div>`).join('')}
    ${missingResponses.length ? `<div class="field-note">${icon('message')}<div><strong>${missingResponses.length} catatan belum ditanggapi.</strong><p>Tambahkan tanggapan melalui tab Catatan pada paket.</p><a href="${href(`usulan/${pkg.id}`)}" data-action="notes-link">Buka paket</a></div></div>` : ''}
    ${!blocked ? `<div class="check-complete">${[['Data item', 'Uraian, spesifikasi, referensi, satuan, dan harga terisi.'], ['Bukti pendukung', 'Setiap item memiliki lampiran pendukung.'], ['Tanggapan', 'Tidak ada catatan yang belum ditanggapi.']].map(([title, text]) => `<div>${icon('circleCheck')}<span><strong>${title}</strong><p>${text}</p></span></div>`).join('')}</div>` : ''}</section>
    <aside class="submit-panel"><h2>Ringkasan pengajuan</h2><dl><div><dt>Paket</dt><dd>${e(pkg.title)}</dd></div><div><dt>Jenis / tahun</dt><dd>${pkg.type} / ${pkg.year}</dd></div><div><dt>Jumlah item</dt><dd>${pkg.items.length} item</dd></div><div><dt>Penerima berikutnya</dt><dd>BKAD · pemeriksaan kelengkapan</dd></div></dl><p class="submit-explanation">Setelah diajukan, versi ini ditampilkan untuk dibaca. Perbaikan dilakukan jika paket dikembalikan.</p>
    <label class="checkbox-label submit-agreement"><input type="checkbox" id="submit-agreement" ${blocked || !editable(pkg) ? 'disabled' : ''}> Saya telah memeriksa data dan bukti pendukung paket ini.</label>${button('Simulasikan pengiriman', 'submit-package', 'send', 'primary full-button', 'disabled id="submit-button"')}<small class="demo-explanation">Mode prototipe: pengiriman ini tidak diteruskan ke BKAD.</small></aside></div>`;
}

function renderSuccess(pkg) {
    return `<section class="success-panel"><span class="success-icon">${icon('circleCheck')}</span><h1>Simulasi pengiriman berhasil</h1><p>Paket <strong>${e(pkg.number)}</strong> kini berstatus Diajukan di tab ini.</p><div class="success-package"><span class="package-icon">${icon('file')}</span><div><strong>${e(pkg.title)}</strong><small>${pkg.items.length} item · ${pkg.type} · Versi ${pkg.version}</small></div>${badge(pkg.status)}</div><div class="next-step"><span>${icon('shield')}</span><div><h3>Berikutnya: pemeriksaan kelengkapan</h3><p>Pada aplikasi operasional, BKAD memeriksa usulan dan dapat mengembalikannya jika membutuhkan perbaikan.</p></div></div><div class="success-actions"><a class="btn secondary" href="${href('usulan')}">Semua usulan</a><a class="btn primary" href="${href(`usulan/${pkg.id}`)}">Lihat paket ${icon('arrow')}</a></div><small class="muted">Ini simulasi lokal. Muat ulang halaman untuk mereset data contoh.</small></section>`;
}

function renderResponses() {
    const list = state.packages.filter(pkg => pkg.status === 'correction');
    return `${heading('Tanggapan & perbaikan', 'Catatan resmi yang perlu ditindaklanjuti oleh SKPD Anda.')}<section class="table-panel">${list.map(pkg => `<div class="response-package"><span class="note-icon">${icon('message')}</span><div><h2>${e(pkg.title)}</h2><p>${pkg.number} · ${pkg.items.filter(i => i.note).length} catatan administrasi dari BKAD</p>${badge(pkg.status)}</div><a class="btn secondary" href="${href(`usulan/${pkg.id}`)}">Buka paket ${icon('arrow')}</a></div>`).join('') || empty('Tidak ada usulan yang perlu diperbaiki', 'Catatan untuk SKPD Anda akan tampil di sini.')}</section>`;
}

function renderOverview() {
    const action = state.packages.filter(pkg => editable(pkg));
    return `${heading('Ringkasan ruang kerja', 'Lanjutkan usulan dan tindak lanjuti catatan yang membutuhkan perhatian.', button('Buat usulan', 'create-package', 'plus', 'primary'))}<div class="overview-layout"><section class="table-panel"><div class="section-heading padded"><span class="section-icon">${icon('folder')}</span><div><h2>Perlu ditindaklanjuti</h2><p>${action.length} paket masih dalam pengerjaan.</p></div></div>${action.map(pkg => `<div class="overview-row"><div><a class="item-name" href="${href(`usulan/${pkg.id}`)}">${e(pkg.title)}</a><small>${pkg.items.length} item · ${pkg.number}</small></div>${badge(pkg.status)}<a class="icon-btn" href="${href(`usulan/${pkg.id}`)}" aria-label="Buka ${e(pkg.title)}">${icon('arrow')}</a></div>`).join('')}</section><aside class="form-panel overview-guide"><span class="large-file">${icon('file')}</span><h2>Dari usulan ke hasil reviu</h2><ol><li>Susun item dan lengkapi bukti.</li><li>Ajukan untuk pemeriksaan kelengkapan BKAD.</li><li>Tanggapi catatan resmi yang diterima.</li><li>Pantau proses reviu dan hasilnya.</li></ol><a class="btn secondary" href="${href('usulan')}">Buka semua usulan ${icon('arrow')}</a></aside></div>`;
}

function renderRecap() {
    return `${heading('Rekap usulan', 'Ringkasan paket dalam lingkup SKPD dan periode pengusulan ini.', button('Unduh rekap CSV', 'export-recap', 'download', 'secondary'))}<section class="table-panel"><div class="table-toolbar"><h2>Tahun anggaran 2027</h2><span class="muted">${state.packages.length} paket · data contoh</span></div><div class="table-scroll"><table><thead><tr><th>Status</th><th class="number">Paket SHS</th><th class="number">Paket SBU</th><th class="number">Jumlah item</th></tr></thead><tbody>${Object.keys(statuses).map(key => { const group = state.packages.filter(pkg => pkg.status === key); return `<tr><td>${badge(key)}</td><td class="number">${group.filter(p => p.type === 'SHS').length}</td><td class="number">${group.filter(p => p.type === 'SBU').length}</td><td class="number">${group.reduce((sum, p) => sum + p.items.length, 0)}</td></tr>`; }).join('')}</tbody></table></div></section><p class="quiet-note">${icon('help')}Unduhan prototipe menggunakan CSV yang dapat dibuka di Excel.</p>`;
}

function openDialog(title, content, cls = '') {
    const dialog = document.querySelector('#app-dialog');
    dialog.className = cls;
    dialog.innerHTML = `<div class="dialog-header"><h2 id="dialog-title">${title}</h2><button type="button" class="icon-btn" data-action="close-dialog" aria-label="Tutup dialog">${icon('close')}</button></div>${content}`;
    dialog.showModal();
}

function createPackage() {
    openDialog('Buat usulan baru', `<p class="dialog-description">Mulai dengan identitas paket. Item dan bukti dilengkapi pada langkah berikutnya.</p><form id="package-form"><div class="form-field"><label for="new-title">Nama paket <span class="required">*</span></label><input id="new-title" name="title" required maxlength="150" placeholder="Contoh: Kebutuhan operasional perkantoran" autofocus></div><div class="form-grid"><div class="form-field"><label for="new-type">Jenis usulan</label><select name="type" id="new-type"><option value="SHS">SHS · Standar Harga Satuan</option><option value="SBU">SBU · Standar Biaya Umum</option></select></div><div class="form-field"><label for="new-year">Tahun anggaran</label><select name="year" id="new-year"><option>2027</option></select></div></div><div class="form-field"><label for="new-description">Keterangan <span class="optional">(opsional)</span></label><textarea id="new-description" name="description" maxlength="1000" rows="2" placeholder="Jelaskan lingkup usulan…"></textarea></div><div class="dialog-actions">${button('Batal', 'close-dialog', null, 'secondary')}<button type="submit" class="btn primary">Buat draf ${icon('arrow')}</button></div></form>`);
}

function fileError(message) { const node = document.querySelector('#file-error'); if (node) { node.hidden = false; node.textContent = message; } }
async function readExcel(file) {
    const pkg = currentPackage();
    if (!file || !pkg || !editable(pkg)) return;
    if (!/\.xlsx$/i.test(file.name)) { state.import = { pkgId: pkg.id, error: 'Pilih berkas .xlsx. Simpan ulang berkas Excel lama sebagai .xlsx terlebih dahulu.' }; render(); return; }
    if (file.size > 5 * 1024 * 1024) { state.import = { pkgId: pkg.id, error: 'Ukuran berkas melebihi 5 MB. Gunakan berkas yang lebih kecil untuk prototipe.' }; render(); return; }
    state.import = { pkgId: pkg.id, name: file.name, loading: true };
    render();
    try {
        const buffer = await file.arrayBuffer();
        const hash = [...new Uint8Array(await crypto.subtle.digest('SHA-256', buffer))].map(x => x.toString(16).padStart(2, '0')).join('');
        if (pkg.importedHashes?.includes(hash)) throw new Error('Berkas yang sama sudah diimpor ke paket ini. Pilih berkas lain.');
        const { default: readExcelFile } = await import('read-excel-file/universal');
        const sheets = await readExcelFile(buffer);
        if (!sheets.length || !sheets.some(sheet => sheet.data.length)) throw new Error('Berkas tidak berisi data. Periksa lembar Excel Anda.');
        if (sheets.some(sheet => sheet.data.length > 2001 || sheet.data.some(row => row.length > 60))) throw new Error('Berkas terlalu besar untuk pratinjau ini. Batasi hingga 2.000 baris dan 60 kolom per lembar.');
        if (currentPackage()?.id !== pkg.id || currentRoute()[2] !== 'impor') return;
        const sheetIndex = sheets.findIndex(sheet => sheet.data.length > 0);
        const header = sheets[sheetIndex].data.slice(0, 10).findIndex(row => Object.values(mapHeaders(row)).filter(index => index >= 0).length >= 3);
        state.import = { pkgId: pkg.id, name: file.name, hash, sheets, sheetIndex, headerRow: Math.max(0, header) + 1, mapping: mapHeaders(sheets[sheetIndex].data[Math.max(0, header)]), onlyErrors: false };
        refreshImport(); render();
    } catch (error) {
        if (currentPackage()?.id !== pkg.id || currentRoute()[2] !== 'impor') return;
        const known = /^(Berkas|Ukuran)/.test(error.message);
        state.import = { pkgId: pkg.id, error: known ? error.message : 'Berkas belum dapat dibaca. Pastikan berkas .xlsx tidak rusak atau dilindungi kata sandi.' }; render();
    }
}

function download(name, rows) {
    const blob = new Blob(['\ufeff' + rows.map(row => row.map(csvCell).join(';')).join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob); const a = document.createElement('a');
    a.href = url; a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
}

root.addEventListener('click', event => {
    const node = event.target.closest('[data-action]');
    if (!node || node.disabled) return;
    const action = node.dataset.action, id = node.dataset.id, pkg = currentPackage();
    if (action === 'menu') { root.classList.toggle('menu-open'); const open = root.classList.contains('menu-open'); document.querySelector('#sidebar').inert = !open; node.setAttribute('aria-expanded', String(open)); if (open) document.querySelector('.brand').focus(); return; }
    if (action === 'close-menu') { closeMenu(); return; }
    if (action === 'close-dialog') { document.querySelector('#app-dialog').close(); return; }
    if (action === 'create-package') { createPackage(); return; }
    if (action === 'demo') { openDialog('Tentang prototipe ini', '<div class="dialog-copy"><p>Anda sedang mencoba rancangan UI dengan data sintetis. Perubahan hanya berlaku pada tab ini dan akan hilang ketika halaman dimuat ulang.</p><p>File diproses di browser dan pengiriman tidak diteruskan ke BKAD. Integrasi penyimpanan aplikasi dikerjakan pada tahap fitur berikutnya.</p></div>'); return; }
    if (action === 'help') { openDialog('Panduan pengusulan', `<div class="dialog-copy"><ol><li>Buat paket SHS atau SBU pada periode terbuka.</li><li>Tambahkan item satu per satu atau gunakan impor Excel.</li><li>Lengkapi spesifikasi, referensi, harga, dan bukti setiap item.</li><li>Gunakan <strong>Periksa & kirim</strong> untuk melihat kekurangan.</li><li>Tindak lanjuti catatan melalui paket yang dikembalikan.</li></ol><p>Prototipe ini hanya memakai data contoh dan simulasi di tab browser.</p></div>`); return; }
    if (action === 'profile' || action === 'organization') { openDialog(action === 'profile' ? 'Operator Demo' : 'Perangkat Daerah', '<div class="dialog-copy"><p>Identitas ini digunakan untuk demonstrasi Operator SKPD. Seluruh paket berada dalam organisasi contoh.</p><p>Pengelolaan akun dan penetapan organisasi akan menggunakan akun resmi pada aplikasi operasional.</p></div>'); return; }
    if (action === 'filter') { state.filter = id; state.listPage = 1; }
    if (action === 'clear-filters') { state.filter = 'all'; state.query = ''; state.type = ''; state.listPage = 1; }
    if (action === 'sort-packages') { state.packages.reverse(); node.setAttribute('aria-label', 'Urutan paket dibalik'); }
    if (action === 'list-prev') state.listPage--;
    if (action === 'list-next') state.listPage++;
    if (action === 'tab') state.tab = id;
    if (action === 'show-notes') state.tab = 'notes';
    if (action === 'item-prev') state.itemPage--;
    if (action === 'item-next') state.itemPage++;
    if (action === 'new-item' && pkg && editable(pkg)) { state.editor = null; navigate(`usulan/${pkg.id}/item/baru`); return; }
    if (action === 'check-package' && pkg) { navigate(`usulan/${pkg.id}/periksa`); return; }
    if (action === 'import' && pkg && editable(pkg)) { navigate(`usulan/${pkg.id}/impor`); return; }
    if (action === 'reset-import') state.import = null;
    if (action === 'sample-import' && pkg) {
        const data = [['Uraian', 'Spesifikasi', 'Kode barang', 'Rekening', 'Satuan', 'Harga'], ['Kertas HVS A4', '80 gsm, 500 lembar', '1.3.2.01.001', '5.1.02.01.01.0024', 'Rim', 65000], ['Tinta printer', 'Botol hitam 135 ml', '1.3.2.01.002', '5.1.02.01.01.0024', 'Botol', ''], ['Map arsip', 'Karton folio bertali', '1.3.2.01.003', '5.1.02.01.01.0024', 'Buah', 8500]];
        state.import = { pkgId: pkg.id, name: 'Contoh pratinjau · data sintetis', hash: `sample-${crypto.randomUUID()}`, sheets: [{ sheet: 'Item usulan', data }], sheetIndex: 0, headerRow: 1, mapping: mapHeaders(data[0]) }; refreshImport();
    }
    if (action === 'omit-row' && state.import) state.import.omitted.add(Number(id));
    if (action === 'import-prev') state.import.page--;
    if (action === 'import-next') state.import.page++;
    if (action === 'save-import' && pkg && editable(pkg)) {
        const imp = state.import; const rows = imp.rows.filter(row => !imp.omitted.has(row.row));
        if (!rows.length || rows.some(row => row.errors.length)) return;
        pkg.items.push(...rows.map(({ errors, row, inputValues, ...item }) => ({ ...item, id: crypto.randomUUID() })));
        pkg.importedHashes = [...(pkg.importedHashes || []), imp.hash];
        pkg.history.push({ title: `${rows.length} item ditambahkan melalui impor`, description: 'Operator Demo · simulasi lokal', date: 'Baru saja' });
        state.import = null; navigate(`usulan/${pkg.id}`); toast(`${rows.length} item ditambahkan. Lengkapi bukti pada setiap item.`); return;
    }
    if (action === 'remove-file' && pkg && editable(pkg) && state.editor) {
        const file = state.editor.evidence[Number(id)];
        if (file?.url) { URL.revokeObjectURL(file.url); state.urls.delete(file.url); }
        state.editor.evidence.splice(Number(id), 1);
    }
    if (action === 'preview-file' && state.editor) {
        const file = state.editor.evidence[Number(id)];
        if (file?.url) { openDialog(e(file.name), `${file.type.startsWith('image/') ? `<img class="file-preview" alt="Pratinjau ${e(file.name)}" src="${file.url}">` : `<iframe class="pdf-preview" title="Pratinjau ${e(file.name)}" sandbox src="${file.url}"></iframe>`}`, 'preview-dialog'); } return;
    }
    if (action === 'submit-package' && pkg && editable(pkg) && pkg.items.length && pkg.items.every(ready) && !pkg.items.some(item => item.note && !item.response?.trim()) && document.querySelector('#submit-agreement')?.checked) {
        if (pkg.status === 'correction') pkg.version++;
        pkg.status = 'submitted'; pkg.updated = 'Baru saja';
        pkg.history.push({ title: 'Pengajuan disimulasikan', description: `Versi ${pkg.version} · belum dikirim ke BKAD`, date: 'Baru saja' });
        navigate(`usulan/${pkg.id}/terkirim`); return;
    }
    if (action === 'download-template') { download('contoh-item-usulan.csv', [['Uraian', 'Spesifikasi', 'Kode barang', 'Rekening', 'Satuan', 'Harga'], ['Kertas HVS A4', '80 gsm, 500 lembar', '1.3.2.01.001', '5.1.02.01.01.0024', 'Rim', 65000]]); toast('Contoh CSV diunduh. Buka di Excel, lalu simpan sebagai .xlsx untuk mengimpor.'); return; }
    if (action === 'export-recap') { download('rekap-usulan-contoh.csv', [['Nomor', 'Nama paket', 'Jenis', 'Tahun', 'Status', 'Jumlah item'], ...state.packages.map(p => [p.number, p.title, p.type, p.year, statuses[p.status].label, p.items.length])]); toast('Rekap data contoh diunduh.'); return; }
    render();
});

root.addEventListener('input', event => {
    const input = event.target;
    if (input.id === 'package-search') { state.query = input.value; state.listPage = 1; render(); }
    if (input.id === 'item-search') { state.itemQuery = input.value; state.itemPage = 1; render(); }
    if (input.closest('#item-form') && input.name && state.editor) state.editor[input.name] = input.value;
    if (input.dataset.importRow && state.import) {
        const row = state.import.rows.find(row => row.row === Number(input.dataset.importRow));
        row.inputValues = { ...row.inputValues, [input.dataset.key]: input.value };
        row[input.dataset.key] = input.dataset.key === 'price' ? parsePrice(input.value) : input.value;
        row.errors = itemProblems({ ...row, evidence: [{}] });
        render();
    }
});

root.addEventListener('change', async event => {
    const input = event.target;
    if (input.id === 'type-filter') { state.type = input.value; state.listPage = 1; render(); }
    if (input.id === 'item-filter') { state.itemFilter = input.value; state.itemPage = 1; render(); }
    if (input.id === 'excel-input') await readExcel(input.files[0]);
    if (input.id === 'evidence-input') {
        if (!editable(currentPackage()) || !state.editor) return;
        let errors = [];
        for (const file of input.files) {
            if (!['application/pdf', 'image/jpeg', 'image/png'].includes(file.type)) { errors.push(`${file.name}: gunakan PDF, JPG, atau PNG.`); continue; }
            if (file.size > 10 * 1024 * 1024) { errors.push(`${file.name}: ukuran melebihi 10 MB.`); continue; }
            const url = URL.createObjectURL(file); state.urls.add(url);
            state.editor.evidence.push({ name: file.name, size: file.size, type: file.type, url });
        }
        render(); if (errors.length) fileError(errors.join(' '));
    }
    if (input.id === 'submit-agreement') document.querySelector('#submit-button').disabled = !input.checked;
    if (input.id === 'only-import-errors') { state.import.onlyErrors = input.checked; state.import.page = 1; render(); }
    if (input.dataset.mapping) { state.import.mapping[input.dataset.mapping] = Number(input.value); refreshImport(); render(); }
    if (input.id === 'import-sheet' || input.id === 'header-row') {
        const imp = state.import;
        if (input.id === 'import-sheet') { imp.sheetIndex = Number(input.value); imp.headerRow = 1; } else imp.headerRow = Number(input.value);
        imp.mapping = mapHeaders(imp.sheets[imp.sheetIndex].data[imp.headerRow - 1] || []); refreshImport(); render();
    }
    if (input.dataset.importRow) {
        const row = state.import.rows.find(row => row.row === Number(input.dataset.importRow));
        row.inputValues = { ...row.inputValues, [input.dataset.key]: input.value };
        row[input.dataset.key] = input.dataset.key === 'price' ? parsePrice(input.value) : input.value;
        row.errors = itemProblems({ ...row, evidence: [{}] }); render();
    }
});

root.addEventListener('submit', event => {
    event.preventDefault();
    const form = event.target, data = new FormData(form);
    if (form.id === 'package-form') {
        const title = String(data.get('title')).trim();
        if (!title) { document.querySelector('#new-title').setCustomValidity('Isi nama paket usulan.'); document.querySelector('#new-title').reportValidity(); return; }
        const id = crypto.randomUUID();
        state.packages.unshift({ id, number: `DRAF/${String(state.packages.length + 1).padStart(3, '0')}`, title, type: String(data.get('type')), year: '2027', description: String(data.get('description')).trim(), status: 'draft', version: 1, updated: 'Baru saja', items: [], history: [{ title: 'Draf paket dibuat', description: 'Operator Demo · simulasi lokal', date: 'Baru saja' }] });
        document.querySelector('#app-dialog').close(); navigate(`usulan/${id}`); toast('Draf paket dibuat di tab ini.');
    }
    if (form.id === 'item-form') {
        const pkg = currentPackage(); if (!pkg || !editable(pkg)) return;
        const item = { ...state.editor };
        for (const [key] of importFields) item[key] = String(data.get(key) || '').trim();
        item.price = item.price === '' ? '' : Number(item.price);
        if (!item.name) { document.querySelector('#edit-name').setCustomValidity('Isi uraian item.'); document.querySelector('#edit-name').reportValidity(); return; }
        const index = pkg.items.findIndex(i => i.id === item.id);
        if (index >= 0) pkg.items[index] = item; else pkg.items.push(item);
        pkg.updated = 'Baru saja'; pkg.history.push({ title: index >= 0 ? 'Item diperbarui' : 'Item ditambahkan', description: item.name, date: 'Baru saja' });
        state.editor = null; navigate(`usulan/${pkg.id}`); toast('Item disimpan pada draf di tab ini.');
    }
    if (form.classList.contains('response-form')) {
        const pkg = currentPackage(); if (!pkg || !editable(pkg)) return;
        const item = pkg.items.find(i => i.id === form.dataset.item); const response = String(data.get('response') || '').trim();
        if (!item || !response) return;
        item.response = response; pkg.history.push({ title: 'Tanggapan disimpan', description: item.name, date: 'Baru saja' }); render(); toast('Tanggapan disimpan di tab ini.');
    }
});

root.addEventListener('dragover', event => { if (event.target.closest('#excel-drop')) { event.preventDefault(); event.target.closest('#excel-drop').classList.add('dragging'); } });
root.addEventListener('dragleave', event => event.target.closest('#excel-drop')?.classList.remove('dragging'));
root.addEventListener('drop', event => { if (event.target.closest('#excel-drop')) { event.preventDefault(); readExcel(event.dataTransfer.files[0]); } });
root.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); if (event.key === 'Tab' && root.classList.contains('menu-open')) { const focusable = [...document.querySelectorAll('#sidebar a, #sidebar button')]; if (event.shiftKey && document.activeElement === focusable[0]) { event.preventDefault(); focusable.at(-1).focus(); } else if (!event.shiftKey && document.activeElement === focusable.at(-1)) { event.preventDefault(); focusable[0].focus(); } } });
root.addEventListener('input', event => { if (event.target.setCustomValidity) event.target.setCustomValidity(''); });
window.addEventListener('hashchange', render);
window.addEventListener('resize', refreshSidebar);
window.addEventListener('pagehide', () => state.urls.forEach(url => URL.revokeObjectURL(url)));
render();
