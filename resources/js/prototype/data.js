export const statuses = {
    draft: { label: 'Draf', tone: 'neutral' },
    correction: { label: 'Perlu perbaikan', tone: 'warning' },
    submitted: { label: 'Diajukan', tone: 'blue' },
    review: { label: 'Dalam reviu', tone: 'purple' },
    done: { label: 'Selesai', tone: 'green' },
};

export const rupiah = value => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
export const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
export const editable = pkg => ['draft', 'correction'].includes(pkg.status);
export const ready = item => itemProblems(item).length === 0;

export function itemProblems(item) {
    const errors = [];
    if (!String(item.name || '').trim()) errors.push('Uraian belum diisi');
    if (!String(item.spec || '').trim()) errors.push('Spesifikasi belum diisi');
    if (!String(item.code || '').trim()) errors.push('Kode barang belum diisi');
    if (!String(item.account || '').trim()) errors.push('Rekening belum diisi');
    if (!String(item.unit || '').trim()) errors.push('Satuan belum diisi');
    if (!Number.isFinite(Number(item.price)) || Number(item.price) <= 0) errors.push('Harga harus lebih dari nol');
    if (!item.evidence?.length) errors.push('Bukti pendukung belum dilampirkan');
    return errors;
}

const samples = [
    ['Kertas HVS A4', '80 gsm, isi 500 lembar, putih', 'Rim', 65000],
    ['Tinta printer hitam', 'Botol 135 ml, kompatibel printer ink tank', 'Botol', 125000],
    ['Map arsip', 'Bahan karton, ukuran folio, bertali', 'Buah', 8500],
    ['Pulpen gel', 'Tinta hitam, ujung 0,5 mm, isi 12', 'Kotak', 54000],
    ['Kursi kerja', 'Sandaran ergonomis, tinggi dapat disesuaikan', 'Unit', 1750000],
    ['Meja kerja', 'Ukuran 120 × 60 cm, dua laci', 'Unit', 2400000],
    ['Stapler sedang', 'Kapasitas 30 lembar, isi nomor 3', 'Buah', 46000],
    ['Lemari arsip', 'Baja, dua pintu, empat susun', 'Unit', 3200000],
    ['Papan tulis', 'Whiteboard magnetik 120 × 90 cm', 'Unit', 875000],
    ['Kertas label', 'Ukuran A4, perekat permanen, 100 lembar', 'Pak', 110000],
    ['Ordner folio', 'Punggung 7 cm, mekanik tuas', 'Buah', 38000],
    ['Kalkulator meja', '12 digit, layar lebar', 'Unit', 165000],
];

export function makePackages() {
    const list = [
        ['001', 'Kebutuhan operasional perkantoran', 'SHS', 'draft', 12, '1 Nov 2026', '16.1'],
        ['002', 'Peralatan penunjang pelayanan', 'SHS', 'correction', 8, '31 Okt 2026', '14.2'],
        ['003', 'Biaya kegiatan koordinasi', 'SBU', 'submitted', 6, '30 Okt 2026', '1'],
        ['004', 'Pemeliharaan gedung dan ruangan', 'SBU', 'review', 4, '29 Okt 2026', '1'],
        ['005', 'Perlengkapan ruang rapat', 'SHS', 'draft', 5, '28 Okt 2026', '1'],
        ['006', 'Dukungan kegiatan lapangan', 'SBU', 'done', 7, '26 Okt 2026', '1'],
        ['007', 'Perlengkapan administrasi umum', 'SHS', 'draft', 3, '25 Okt 2026', '1'],
    ];
    return list.map(([id, title, type, status, count, updated]) => ({
        id, number: `USL/2027/${id}`, title, type, status, year: '2027', updated,
        description: 'Usulan kebutuhan pendukung pelaksanaan tugas perangkat daerah untuk tahun anggaran 2027.',
        version: status === 'correction' ? 2 : 1,
        history: [{ title: 'Draf paket dibuat', description: 'Operator Demo · data contoh', date: '25 Okt 2026' }],
        items: Array.from({ length: count }, (_, index) => ({
            id: `${id}-${index + 1}`, name: samples[index % 12][0], spec: samples[index % 12][1],
            code: `1.3.2.01.${String(index + 1).padStart(3, '0')}`, account: '5.1.02.01.01.0024',
            unit: samples[index % 12][2], price: samples[index % 12][3],
            evidence: status === 'draft' && index >= count - 3 ? [] : [{ name: `Survei harga ${index + 1}.pdf`, sample: true }],
            note: status === 'correction' && index < 2 ? 'Mohon lengkapi spesifikasi dan bukti pembanding harga terbaru.' : '',
        })),
    }));
}

export const importFields = [
    ['name', 'Uraian'], ['spec', 'Spesifikasi'], ['code', 'Kode barang'],
    ['account', 'Rekening'], ['unit', 'Satuan'], ['price', 'Harga'],
];

export function mapHeaders(headers) {
    const aliases = {
        name: ['uraian', 'nama barang', 'nama item', 'nama'], spec: ['spesifikasi', 'spesifikasi barang'],
        code: ['kode barang', 'kode'], account: ['rekening', 'kode rekening'], unit: ['satuan'],
        price: ['harga', 'harga usulan', 'harga satuan'],
    };
    return Object.fromEntries(importFields.map(([key]) => [key, headers.findIndex(header => aliases[key].includes(String(header ?? '').trim().toLowerCase()))]));
}

export function parsePrice(value) {
    if (typeof value === 'number') return value;
    const str = String(value ?? '').trim().replace(/^Rp\.?\s*/i, '').replace(/\s/g, '');
    if (!str) return NaN;
    if (/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/.test(str)) return Number(str.replace(/\./g, '').replace(',', '.'));
    if (/^\d+(,\d{1,2})?$/.test(str)) return Number(str.replace(',', '.'));
    if (/^\d+\.\d{1,2}$/.test(str)) return Number(str);
    return NaN;
}

export function importRows(rows, mapping, firstRow = 2) {
    return rows.map((row, index) => {
        const item = { row: firstRow + index, evidence: [], note: '' };
        for (const [key] of importFields) item[key] = mapping[key] >= 0 ? row[mapping[key]] ?? '' : '';
        item.price = parsePrice(item.price);
        item.errors = itemProblems({ ...item, evidence: [{}] });
        return item;
    });
}

export function csvCell(value) {
    let text = String(value ?? '');
    if (/^[\s]*[=+\-@]/.test(text)) text = `'${text}`;
    return `"${text.replace(/"/g, '""')}"`;
}
