import test from 'node:test';
import assert from 'node:assert/strict';
import { makePackages, ready, itemProblems, parsePrice, mapHeaders, importRows, escapeHtml, csvCell } from '../../resources/js/prototype/data.js';

test('kelengkapan memeriksa data wajib, harga positif dan bukti per item', () => {
    const item = makePackages()[0].items[0];
    assert.equal(ready(item), true);
    assert.equal(ready({ ...item, evidence: [] }), false);
    assert.equal(ready({ ...item, price: Infinity }), false);
    assert.equal(ready({ ...item, account: ' ' }), false);
    assert.equal(itemProblems({}).length, 7);
});

test('harga dari Excel menerima angka dan format rupiah Indonesia tanpa mengubah kode', () => {
    assert.equal(parsePrice(65000), 65000);
    assert.equal(parsePrice('Rp 1.250.000,50'), 1250000.5);
    assert.equal(parsePrice('65.000'), 65000);
    assert.equal(parsePrice('65000.25'), 65000.25);
    assert.equal(Number.isNaN(parsePrice('abc')), true);
    assert.equal(Number.isNaN(parsePrice('')), true);
});

test('pemetaan kolom dan nomor baris tetap benar saat urutan spreadsheet berubah', () => {
    const mapping = mapHeaders(['Harga Usulan', 'Satuan', 'Kode Barang', 'Rekening', 'Spesifikasi', 'Nama Barang']);
    const rows = importRows([[65000, 'Rim', '001.020', '005.001', '80 gsm', 'Kertas'], ['', '', '', '', '', 'Kelompok ATK']], mapping, 7);
    assert.equal(rows[0].name, 'Kertas');
    assert.equal(rows[0].code, '001.020');
    assert.equal(rows[0].row, 7);
    assert.deepEqual(rows[0].errors, []);
    assert.equal(rows[1].row, 8);
    assert.equal(rows[1].errors.length, 5);
    assert.equal(rows[0].evidence.length, 0);
    assert.equal(ready(rows[0]), false);
});

test('kolom yang tidak dipetakan memunculkan kesalahan dan tidak mengambil kolom terakhir', () => {
    const mapping = mapHeaders(['Uraian', 'Harga']);
    const [item] = importRows([['Kertas', 65000]], mapping);
    assert.equal(item.spec, '');
    assert.ok(item.errors.includes('Spesifikasi belum diisi'));
    assert.ok(item.errors.includes('Rekening belum diisi'));
});

test('konten pengguna di-escape dan unduhan CSV tidak mengeksekusi formula', () => {
    assert.equal(escapeHtml('<img src=x onerror="x">'), '&lt;img src=x onerror=&quot;x&quot;&gt;');
    assert.equal(csvCell('=1+1'), '"\'=1+1"');
    assert.equal(csvCell('a"b'), '"a""b"');
});
