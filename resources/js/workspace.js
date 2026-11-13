// Peningkatan progresif untuk ruang kerja. Tanpa skrip ini setiap form tetap
// berfungsi dengan input/select bawaan; nama field dan format nilai tidak berubah.

const ICONS = {
    x: '<path d="M6 6l12 12M18 6 6 18"/>',
    chevron: '<path d="m6 9 6 6 6-6"/>',
    check: '<path d="m5 12.5 4.5 4.5L19 7"/>',
    alert: '<path d="M12 4 2.5 20h19zM12 10v4.5M12 17.2v.3"/>',
    upload: '<path d="M12 16V5m-5 5 5-5 5 5M4 20h16"/>',
    file: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
};
const icon = (name) => `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name]}</svg>`;
const el = (tag, props = {}, html = '') => {
    const node = document.createElement(tag);
    Object.entries(props).forEach(([key, value]) => {
        if (key === 'class') node.className = value;
        else if (key === 'text') node.textContent = value;
        else node.setAttribute(key, value);
    });
    if (html) node.innerHTML = html;
    return node;
};
let uid = 0;
const nextId = (prefix) => `${prefix}-${++uid}`;

/* ---------- Toast ---------- */
document.querySelectorAll('[data-toast]').forEach((toast) => {
    const close = el('button', { type: 'button', class: 'toast-close', 'aria-label': 'Tutup pemberitahuan' }, icon('x'));
    const dismiss = () => {
        toast.classList.add('leaving');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
        setTimeout(() => toast.remove(), 400);
    };
    close.addEventListener('click', dismiss);
    toast.append(close);
    let timer = setTimeout(dismiss, 7000);
    toast.addEventListener('pointerenter', () => clearTimeout(timer));
    toast.addEventListener('pointerleave', () => { timer = setTimeout(dismiss, 3000); });
});

/* ---------- Dialog konfirmasi (menggantikan confirm peramban) ---------- */
const confirmDialog = document.querySelector('[data-confirm-dialog]');
if (confirmDialog?.showModal) {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const message = form.dataset?.statusConfirm;
        if (!message) return;
        event.preventDefault();
        event.stopPropagation();
        const submitter = event.submitter;
        confirmDialog.querySelector('[data-confirm-text]').textContent = message;
        confirmDialog.returnValue = 'cancel';
        confirmDialog.showModal();
        confirmDialog.addEventListener('close', () => {
            if (confirmDialog.returnValue !== 'ok') { submitter?.focus(); return; }
            delete form.dataset.statusConfirm;
            form.requestSubmit(submitter?.form === form ? submitter : undefined);
            form.dataset.statusConfirm = message;
        }, { once: true });
    }, true);
}

/* ---------- Tombol sibuk ---------- */
document.addEventListener('submit', (event) => {
    queueMicrotask(() => {
        if (event.defaultPrevented) return;
        const button = event.submitter || event.target.querySelector?.('button[type="submit"]');
        button?.setAttribute('data-busy', '');
    });
});
window.addEventListener('pageshow', () => document.querySelectorAll('[data-busy]').forEach((button) => button.removeAttribute('data-busy')));

/* ---------- Combobox ---------- */
// Daftar pilihan bersama untuk referensi jarak jauh maupun <select> lokal.
function createListbox({ host, input, onPick, load }) {
    const list = el('ul', { class: 'combo-list', role: 'listbox', id: nextId('combo-list'), hidden: '' });
    host.append(list);
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('autocomplete', 'off');
    let options = [], active = -1, token = 0;

    const setActive = (index) => {
        active = index;
        [...list.querySelectorAll('[role="option"]')].forEach((node, i) => {
            node.setAttribute('aria-selected', String(i === active));
            if (i === active) { node.scrollIntoView({ block: 'nearest' }); input.setAttribute('aria-activedescendant', node.id); }
        });
        if (active < 0) input.removeAttribute('aria-activedescendant');
    };
    const close = () => { list.hidden = true; host.classList.remove('open'); input.setAttribute('aria-expanded', 'false'); setActive(-1); };
    const show = () => { list.hidden = false; host.classList.add('open'); input.setAttribute('aria-expanded', 'true'); };
    const render = (items, emptyText) => {
        options = items;
        list.replaceChildren(...items.map((item, index) => {
            const node = el('li', { class: `combo-option${item.name ? '' : ' plain'}${item.chosen ? ' is-chosen' : ''}`, role: 'option', id: `${list.id}-${index}`, 'aria-selected': 'false' });
            if (item.name) { node.append(el('code', { text: item.code }), el('span', { text: item.name })); }
            else { node.append(el('span', { text: item.label ?? item.code })); }
            node.addEventListener('pointerdown', (event) => { event.preventDefault(); onPick(item); close(); });
            node.addEventListener('pointermove', () => { if (active !== index) setActive(index); });
            return node;
        }));
        if (!items.length) list.append(el('li', { class: 'combo-status', role: 'presentation', text: emptyText }));
        show();
    };
    const refresh = async () => {
        const mine = ++token;
        const term = input.value.trim();
        const result = load(term, () => {
            list.replaceChildren(el('li', { class: 'combo-skeleton', role: 'presentation' }), el('li', { class: 'combo-skeleton', role: 'presentation' }));
            show();
        });
        try {
            const { items, empty } = await result;
            if (mine === token) { render(items, empty); if (items.length && term) setActive(0); }
        } catch {
            if (mine === token) render([], 'Saran belum dapat dimuat. Kode tetap dapat diketik langsung.');
        }
    };
    let timer;
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(refresh, 180); });
    input.addEventListener('focus', () => { if (list.hidden) refresh(); });
    input.addEventListener('blur', () => { clearTimeout(timer); token++; close(); });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) { refresh(); return; }
            if (!options.length) return;
            setActive((active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length);
        } else if (event.key === 'Enter' && !list.hidden && active >= 0) {
            event.preventDefault(); onPick(options[active]); close();
        } else if (event.key === 'Escape' && !list.hidden) {
            event.preventDefault(); event.stopPropagation(); close();
        }
    });
    return { close, refresh, isOpen: () => !list.hidden };
}

const referenceCache = new Map();
async function searchReference(url, term) {
    const key = `${url}|${term}`;
    if (referenceCache.has(key)) return referenceCache.get(key);
    const response = await fetch(`${url}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('reference');
    const { options } = await response.json();
    referenceCache.set(key, options);
    return options;
}
const findName = async (url, code) => {
    if (!code || code.length > 100) return null;
    try { return (await searchReference(url, code)).find((option) => option.code === code)?.name ?? null; } catch { return undefined; }
};
const remoteLoader = (url, exclude = () => []) => (term, pending) => {
    if (!term) return Promise.resolve({ items: [], empty: 'Ketik kode atau nama untuk mencari referensi.' });
    if (term.length > 100) return Promise.resolve({ items: [], empty: 'Kata pencarian terlalu panjang.' });
    if (!referenceCache.has(`${url}|${term}`)) pending();
    return searchReference(url, term).then((options) => {
        const taken = exclude();
        return { items: options.filter((option) => !taken.includes(option.code)), empty: 'Tidak ada referensi aktif yang cocok. Hubungi BKAD bila kode belum terdaftar.' };
    });
};

// Referensi tunggal: nilai tetap berupa kode; nama tampil sebagai keterangan.
function enhanceReference(input) {
    const url = input.dataset.reference;
    input.removeAttribute('list');
    const host = el('div', { class: 'combo' });
    const control = el('div', { class: 'combo-control' });
    input.before(host);
    control.append(input);
    host.append(control);
    const hint = el('small', { class: 'combo-hint', hidden: '', 'aria-live': 'polite' });
    host.after(hint);
    input.placeholder = input.placeholder || 'Cari kode atau nama…';
    const describe = (code, name) => {
        hint.hidden = !code;
        hint.classList.toggle('unknown', name === null);
        if (!code) return;
        if (name) { hint.innerHTML = icon('check'); hint.append(el('span', { text: name })); }
        else if (name === null) { hint.innerHTML = icon('alert'); hint.append(el('span', { text: 'Kode tidak ditemukan pada referensi aktif.' })); }
        else hint.hidden = true;
    };
    let check = 0;
    const verify = async () => { const mine = ++check; const code = input.value.trim(); const name = await findName(url, code); if (mine === check) describe(code, name); };
    createListbox({ host, input, load: remoteLoader(url), onPick: ({ code, name }) => { check++; input.value = code; describe(code, name); input.dispatchEvent(new Event('change', { bubbles: true })); } });
    input.addEventListener('input', () => { check++; hint.hidden = true; });
    input.addEventListener('blur', verify);
    if (input.value.trim()) verify();
}

// Referensi banyak (rekening): chip per kode; nilai kirim tetap "kode; kode".
function enhanceMultiReference(input) {
    const url = input.dataset.reference;
    const limit = Number(input.getAttribute('maxlength')) || 3200;
    input.removeAttribute('list');
    const host = el('div', { class: 'combo' });
    const control = el('div', { class: 'combo-control' });
    const search = el('input', { type: 'text', id: nextId('combo-search'), placeholder: 'Cari kode atau nama rekening…', 'aria-label': 'Cari rekening untuk ditambahkan', maxlength: '100' });
    input.before(host);
    host.append(control);
    control.append(search);
    input.classList.add('visually-kept');
    input.tabIndex = -1;
    input.setAttribute('aria-hidden', 'true');
    host.append(input);
    const label = input.id && document.querySelector(`label[for="${input.id}"]`);
    if (label) label.htmlFor = search.id;
    if (input.getAttribute('aria-invalid') === 'true') host.classList.add('invalid');
    let codes = input.value.split(';').map((part) => part.trim()).filter(Boolean);
    const sync = () => { input.value = codes.join('; '); input.dispatchEvent(new Event('change', { bubbles: true })); };
    const addChip = (code, name) => {
        const chip = el('span', { class: 'chip' });
        const text = el('span', { text: name ?? '' });
        const remove = el('button', { type: 'button', 'aria-label': `Hapus rekening ${code}` }, icon('x'));
        chip.append(el('code', { text: code }), text, remove);
        remove.addEventListener('click', () => { codes = codes.filter((item) => item !== code); chip.remove(); sync(); search.focus(); });
        search.before(chip);
        if (name === undefined) findName(url, code).then((found) => { text.textContent = found ?? (found === null ? 'tidak ditemukan' : ''); chip.classList.toggle('unknown', found === null); });
    };
    const add = (code, name) => {
        code = code.trim();
        if (!code || codes.includes(code)) return;
        if ([...codes, code].join('; ').length > limit) return;
        codes.push(code); addChip(code, name); sync();
    };
    codes.forEach((code) => addChip(code));
    const box = createListbox({ host, input: search, load: remoteLoader(url, () => codes), onPick: ({ code, name }) => { add(code, name); search.value = ''; } });
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Backspace' && !search.value && codes.length) { control.querySelectorAll('.chip')[codes.length - 1]?.querySelector('button').click(); }
        // Kode yang diketik manual tetap diterima; pemeriksaan akhir ada di server.
        if ((event.key === 'Enter' || event.key === ';') && search.value.trim() && !event.defaultPrevented) { event.preventDefault(); add(search.value.replace(/;/g, '')); search.value = ''; box.close(); }
    });
    search.addEventListener('blur', () => { if (search.value.trim()) { add(search.value.replace(/;/g, '')); search.value = ''; } });
    control.addEventListener('pointerdown', (event) => { if (event.target === control) { event.preventDefault(); search.focus(); } });
}

// <select> panjang menjadi combobox yang dapat dicari.
function enhanceSelect(select) {
    const host = el('div', { class: 'combo' });
    const control = el('div', { class: 'combo-control' });
    const search = el('input', { type: 'text', id: nextId('combo-select') });
    const caret = el('button', { type: 'button', class: 'combo-caret', tabindex: '-1', 'aria-label': 'Buka pilihan' }, icon('chevron'));
    select.before(host);
    control.append(search);
    host.append(control, caret, select);
    select.classList.add('visually-kept');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    const label = select.id && document.querySelector(`label[for="${select.id}"]`);
    if (label) label.htmlFor = search.id;
    ['aria-describedby', 'aria-invalid'].forEach((name) => select.hasAttribute(name) && search.setAttribute(name, select.getAttribute(name)));
    if (select.getAttribute('aria-invalid') === 'true') host.classList.add('invalid');
    const items = () => [...select.options].filter((option) => !option.disabled).map((option) => ({ code: option.value, label: option.textContent.trim(), chosen: option.value === select.value }));
    const placeholder = [...select.options].find((option) => option.value === '')?.textContent.trim() ?? 'Pilih';
    const showCurrent = () => { const current = select.selectedOptions[0]; search.value = current && current.value !== '' ? current.textContent.trim() : ''; search.placeholder = `${placeholder} (ketik untuk mencari)`; };
    let typed = false;
    const box = createListbox({
        host, input: search,
        load: (term) => {
            const needle = typed ? term.toLowerCase() : '';
            return Promise.resolve({ items: items().filter((item) => item.label.toLowerCase().includes(needle)), empty: 'Tidak ada pilihan yang cocok.' });
        },
        onPick: (item) => { select.value = item.code; typed = false; showCurrent(); select.dispatchEvent(new Event('change', { bubbles: true })); },
    });
    search.addEventListener('input', () => { typed = true; });
    search.addEventListener('focus', () => { typed = false; search.select(); });
    search.addEventListener('blur', () => { typed = false; showCurrent(); });
    caret.addEventListener('pointerdown', (event) => { event.preventDefault(); if (box.isOpen()) box.close(); else { search.focus(); box.refresh(); } });
    showCurrent();
}

document.querySelectorAll('input[data-reference]').forEach((input) => (input.dataset.multipleReference === 'true' ? enhanceMultiReference : enhanceReference)(input));
document.querySelectorAll('select[data-combobox]').forEach(enhanceSelect);

/* ---------- Isian rupiah ---------- */
const groupThousands = (digits) => digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
const formatMoney = (raw) => {
    const cleaned = raw.replace(/[^\d,]/g, '');
    const [whole, ...rest] = cleaned.split(',');
    const fraction = rest.length ? `,${rest.join('').slice(0, 2)}` : '';
    const trimmed = whole.replace(/^0+(?=\d)/, '');
    return (trimmed ? groupThousands(trimmed) : (fraction ? '0' : '')) + fraction;
};
document.querySelectorAll('.form-field input[inputmode="decimal"]').forEach((input) => {
    const wrap = el('div', { class: 'money-field' });
    input.before(wrap);
    wrap.append(input);
    // Nilai dari server berupa desimal polos (65000.00); tampilkan dalam format rupiah.
    const stored = input.value.trim().match(/^(\d+)\.(\d{1,2})$/);
    if (stored) input.value = `${stored[1]}${Number(stored[2]) ? `,${stored[2]}` : ''}`;
    input.value = formatMoney(input.value);
    // Titik yang diketik dianggap koma desimal; pemisah ribuan disisipkan otomatis.
    input.addEventListener('beforeinput', (event) => {
        if (event.inputType !== 'insertText' || event.data !== '.') return;
        event.preventDefault();
        if (!input.value.includes(',')) input.setRangeText(',', input.selectionStart, input.selectionEnd, 'end');
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    input.addEventListener('paste', (event) => {
        const pasted = event.clipboardData?.getData('text').trim().replace(/^Rp\.?\s*/i, '') ?? '';
        const plain = pasted.match(/^(\d+)\.(\d{1,2})$/);
        if (!plain) return;
        event.preventDefault();
        input.value = formatMoney(`${plain[1]},${plain[2]}`);
    });
    input.addEventListener('input', (event) => {
        // Cadangan bila beforeinput tidak tersedia: titik yang baru diketik menjadi koma.
        const caret = input.selectionStart;
        if (event.data === '.' && input.value[caret - 1] === '.') {
            input.value = input.value.slice(0, caret - 1) + (input.value.includes(',') ? '' : ',') + input.value.slice(caret);
            input.setSelectionRange(caret, caret);
        }
        const before = input.value.slice(0, input.selectionStart).replace(/[^\d,]/g, '').length;
        input.value = formatMoney(input.value);
        let position = 0, seen = 0;
        while (position < input.value.length && seen < before) { if (/[\d,]/.test(input.value[position])) seen++; position++; }
        input.setSelectionRange(position, position);
    });
});

/* ---------- Pilihan segmen dan kartu keputusan ---------- */
document.querySelectorAll('select[data-choices]').forEach((select) => {
    const group = el('div', { class: select.dataset.choices === 'cards' ? 'choice-cards' : 'segmented', role: 'radiogroup' });
    const label = select.id && document.querySelector(`label[for="${select.id}"]`);
    if (label) { label.id = label.id || nextId('choice-label'); group.setAttribute('aria-labelledby', label.id); label.removeAttribute('for'); }
    const tones = JSON.parse(select.dataset.tones || '{}');
    const buttons = [...select.options].filter((option) => option.value !== '').map((option) => {
        const button = el('button', { type: 'button', role: 'radio', 'data-value': option.value, text: option.textContent.trim() });
        if (tones[option.value]) button.dataset.tone = tones[option.value];
        button.addEventListener('click', () => { select.value = option.value; select.dispatchEvent(new Event('change', { bubbles: true })); button.focus(); });
        return button;
    });
    const paint = () => buttons.forEach((button, index) => {
        const checked = button.dataset.value === select.value;
        button.setAttribute('aria-checked', String(checked));
        button.tabIndex = checked || (!select.value && index === 0) ? 0 : -1;
    });
    group.addEventListener('keydown', (event) => {
        const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
        if (!step) return;
        event.preventDefault();
        const index = buttons.indexOf(document.activeElement);
        buttons[(index + step + buttons.length) % buttons.length].click();
    });
    group.append(...buttons);
    select.before(group);
    select.classList.add('visually-kept');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.addEventListener('change', paint);
    // Validasi "required" bawaan tidak dapat memfokus select tersembunyi.
    select.addEventListener('invalid', (event) => { event.preventDefault(); buttons[0].focus(); group.classList.add('invalid'); });
    paint();
});
// Alasan menjadi wajib mengikuti pilihan (mis. "Tidak berlaku", "Perlu dilengkapi").
document.querySelectorAll('[data-requires-reason]').forEach((select) => {
    const target = document.getElementById(select.dataset.reasonField);
    const label = target && document.querySelector(`label[for="${target.id}"]`);
    if (!target || !label) return;
    const values = select.dataset.requiresReason.split(',');
    const flag = el('span', { class: 'required-flag', text: ' *', hidden: '' });
    label.append(flag);
    const update = () => { const needed = values.includes(select.value); flag.hidden = !needed; target.required = needed; };
    select.addEventListener('change', update);
    update();
});

/* ---------- Pengalih mode isian ---------- */
// Satu panel aktif per formulir; panel lain disembunyikan dan dinonaktifkan agar tidak dikirim maupun divalidasi.
document.querySelectorAll('select[data-mode-switch]').forEach((select) => {
    const panels = (select.form ?? document).querySelectorAll('[data-mode-panel]');
    const update = () => panels.forEach((panel) => { const active = panel.dataset.modePanel === select.value; panel.hidden = !active; panel.disabled = !active; });
    select.addEventListener('change', update);
    update();
});

/* ---------- Unggah berkas ---------- */
const fileSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
document.querySelectorAll('input[type="file"][data-dropzone]').forEach((input) => {
    const zone = el('div', { class: 'dropzone' }, `${icon('upload')}<strong>Seret berkas ke sini atau klik untuk memilih</strong><span>${input.dataset.dropzone}</span>`);
    const info = el('div', { class: 'dropzone-file', hidden: '', 'aria-live': 'polite' });
    input.before(zone);
    zone.append(input);
    zone.after(info);
    const accepted = (input.getAttribute('accept') || '').split(',').map((type) => type.trim().toLowerCase()).filter(Boolean);
    const maxBytes = Number(input.dataset.maxBytes) || Infinity;
    const describe = () => {
        const file = input.files?.[0];
        info.hidden = !file;
        input.setCustomValidity('');
        if (!file) return;
        const name = file.name.toLowerCase();
        const typeOk = !accepted.length || accepted.some((type) => (type.startsWith('.') ? name.endsWith(type) : file.type === type));
        const problem = !typeOk ? 'Jenis berkas tidak didukung.' : file.size > maxBytes ? `Ukuran melebihi batas ${fileSize(maxBytes)}.` : '';
        info.classList.toggle('invalid', Boolean(problem));
        info.innerHTML = icon(problem ? 'alert' : 'file');
        const text = el('div');
        text.append(el('strong', { text: file.name }), el('small', { text: problem || fileSize(file.size) }));
        info.append(text);
        input.setCustomValidity(problem);
    };
    input.addEventListener('change', describe);
    ['dragenter', 'dragover'].forEach((name) => zone.addEventListener(name, () => zone.classList.add('dragging')));
    ['dragleave', 'drop'].forEach((name) => zone.addEventListener(name, () => zone.classList.remove('dragging')));
});

/* ---------- Area teks: tinggi mengikuti isi, penghitung mendekati batas ---------- */
document.querySelectorAll('.form-field textarea').forEach((area) => {
    const max = Number(area.getAttribute('maxlength'));
    const counter = max ? el('span', { class: 'field-counter', hidden: '', 'aria-hidden': 'true' }) : null;
    if (counter) area.after(counter);
    const fit = () => {
        area.style.height = 'auto';
        area.style.height = `${Math.min(area.scrollHeight + 2, 420)}px`;
        if (!counter) return;
        const near = area.value.length >= max * 0.8;
        counter.hidden = !near;
        counter.classList.toggle('near', area.value.length >= max * 0.95);
        counter.textContent = `${area.value.length.toLocaleString('id-ID')} / ${max.toLocaleString('id-ID')}`;
    };
    area.addEventListener('input', fit);
    if (area.value) fit();
});

/* ---------- Filter daftar langsung diterapkan ---------- */
document.querySelectorAll('form[method="GET" i] select, form[method="get" i] select').forEach((select) => {
    if (select.closest('[data-manual-filter]')) return;
    select.addEventListener('change', () => select.form.requestSubmit());
});

/* ---------- Perubahan belum disimpan ---------- */
document.querySelectorAll('form[data-guard]').forEach((form) => {
    let dirty = false, leaving = false;
    const bar = form.querySelector('.form-actionbar');
    bar?.prepend(el('span', { class: 'dirty-note', text: 'Ada perubahan yang belum disimpan' }));
    const mark = () => { dirty = true; form.classList.add('is-dirty'); };
    form.addEventListener('input', mark);
    form.addEventListener('change', mark);
    form.addEventListener('submit', () => { leaving = true; });
    window.addEventListener('beforeunload', (event) => { if (dirty && !leaving) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pageshow', () => { leaving = false; });
});

/* ---------- Pilih semua, cari dalam daftar, tugaskan massal ---------- */
document.querySelectorAll('[data-check-scope]').forEach((scope) => {
    const boxes = () => [...scope.querySelectorAll('input[type="checkbox"][name$="[]"]')];
    if (boxes().length < 2) return;
    const bar = el('div', { class: 'list-tools-bar' });
    const all = el('label', { class: 'checkbox-label' });
    const master = el('input', { type: 'checkbox' });
    all.append(master, document.createTextNode('Pilih semua yang tampil'));
    const count = el('span', { class: 'count', 'aria-live': 'polite' });
    bar.append(all);
    let search;
    if (boxes().length > 7) {
        const field = el('div', { class: 'search-field' }, `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/></svg>`);
        search = el('input', { type: 'search', placeholder: 'Cari dalam daftar…', 'aria-label': 'Cari dalam daftar' });
        field.append(search);
        bar.append(field);
    }
    bar.append(count);
    scope.prepend(bar);
    const rowOf = (box) => box.closest('tr') || box.closest('label');
    const visible = () => boxes().filter((box) => !rowOf(box).hidden);
    const update = () => {
        const chosen = boxes().filter((box) => box.checked).length;
        count.textContent = `${chosen} dari ${boxes().length} dipilih`;
        const shown = visible();
        master.checked = shown.length > 0 && shown.every((box) => box.checked);
        master.indeterminate = !master.checked && shown.some((box) => box.checked);
    };
    master.addEventListener('change', () => { visible().forEach((box) => { box.checked = master.checked; }); scope.dispatchEvent(new Event('change', { bubbles: true })); update(); });
    search?.addEventListener('input', () => { const needle = search.value.trim().toLowerCase(); boxes().forEach((box) => { rowOf(box).hidden = !rowOf(box).textContent.toLowerCase().includes(needle); }); update(); });
    search?.addEventListener('keydown', (event) => { if (event.key === 'Enter') event.preventDefault(); });
    scope.addEventListener('change', update);
    update();
});
document.querySelectorAll('[data-bulk-assign]').forEach((form) => {
    const selects = [...form.querySelectorAll('select[name^="items["]')];
    if (selects.length < 2) return;
    const bar = el('div', { class: 'list-tools-bar' });
    const picker = selects[0].cloneNode(true);
    picker.removeAttribute('name'); picker.id = nextId('bulk'); picker.value = '';
    picker.options[0].textContent = 'Pilih pemeriksa';
    const label = el('label', { for: picker.id, text: 'Tugaskan baris yang belum dipilih ke' });
    const apply = el('button', { type: 'button', class: 'btn secondary', text: 'Terapkan ke halaman ini' });
    const clear = el('button', { type: 'button', class: 'btn icon-text', text: 'Kosongkan pilihan' });
    const count = el('span', { class: 'count', 'aria-live': 'polite' });
    const update = () => { count.textContent = `${selects.filter((select) => select.value).length} dari ${selects.length} item dipilih`; };
    apply.addEventListener('click', () => { if (!picker.value) { picker.focus(); return; } selects.forEach((select) => { if (!select.value) select.value = picker.value; }); update(); });
    clear.addEventListener('click', () => { selects.forEach((select) => { select.value = ''; }); update(); });
    form.addEventListener('change', update);
    bar.append(label, picker, apply, clear, count);
    form.prepend(bar);
    update();
});

/* ---------- Menu baris ---------- */
document.addEventListener('click', (event) => {
    document.querySelectorAll('details.row-menu[open]').forEach((menu) => { if (!menu.contains(event.target)) menu.open = false; });
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const menu = document.querySelector('details.row-menu[open]');
    if (menu) { menu.open = false; menu.querySelector('summary').focus(); }
});
