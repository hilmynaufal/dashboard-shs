# K-3 Prototipe UI Operator SKPD

Jalankan `npm run build` dan `php artisan serve --host=127.0.0.1 --port=8000`,
lalu buka <http://127.0.0.1:8000/prototype/operator>.

Rancangan mengikuti referensi Hearth yang disediakan pengembang serta keputusan
di PRODUCT.md, DESIGN.md dan .impeccable/surfaces/operator.md. Jalur ini hanya
tersedia pada lingkungan local/testing; pada production mengembalikan 404.

## Yang dapat dicoba

- Filter/pencarian/paginasi paket dan item, serta ringkasan sesuai data contoh.
- Membuat draf paket dan mengisi/memperbarui item.
- Memilih lampiran PDF/JPG/PNG lokal, memeriksa batas berkas, dan melihat preview
  lampiran yang dipilih. Lampiran contoh awal hanya metadata sintetis.
- Membaca XLSX di browser memakai read-excel-file (diimpor saat diperlukan),
  memilih lembar/baris judul, memetakan kolom, memperbaiki sel dan melewati baris.
- Mencegah penambahan baris invalid dan impor ulang berkas yang identik pada
  paket yang sama selama sesi demo.
- Menyimpan tanggapan contoh, melihat riwayat, dan memeriksa kelengkapan.
- Simulasi pengiriman jika seluruh item/bukti/tanggapan lengkap; versi yang
  telah diajukan tampil baca saja pada prototipe.
- Mengunduh rekap dan contoh format CSV yang bisa dibuka di Excel.

## Batas yang harus dipertahankan

Seluruh perubahan berada di memori tab dan hilang ketika halaman dimuat ulang.
Tidak ada penulisan database, unggah bukti ke server, atau pengiriman ke BKAD.
Label prototipe selalu tampak dan tindakan pengiriman disebut simulasi.
File contoh dapat dipakai sebagai data sintetis, bukan bukti survei sesungguhnya.

Pratinjau dibatasi 5 MB, 2.000 baris, 60 kolom per lembar. Lampiran lokal maksimal
10 MB per berkas, jenis PDF/JPG/PNG. Batas ini hanya untuk rancangan K-3, bukan
keputusan resmi kapasitas produksi. File XLS lama perlu disimpan ulang sebagai
XLSX. Template yang diunduh berupa CSV; simpan sebagai XLSX di Excel sebelum impor.

Fitur bisnis, izin per objek, versi persisten, storage privat, serta ekspor Word
tetap dikerjakan dan diuji melalui kartu fitur masing-masing; interaksi demo
tidak boleh dijadikan bukti penerimaan fitur bisnis tersebut.

## Verifikasi

- `npm run test:ui`: validasi item, format harga, pemetaan kolom/nomor baris,
  perlakuan kolom hilang, escaping teks dan pencegahan formula pada CSV.
- `php artisan test`: regresi backend, akses route prototype pada testing dan
  penolakannya pada production.
- `npm run build`: aset/font lokal dan pembaca Excel yang dimuat saat diperlukan.
- Browser: desktop dan mobile, alur edit/impor/periksa/kirim dengan data sintetis.

Gambar rancangan ada di Peta layar PSE; hasil QA lokal di .impeccable/review/
diabaikan Git. K-3 meliputi rancangan UI; layar peran lain menyusul arahan dan
fitur resmi. Identitas instansi belum final.

Hasil pemeriksaan 1 November 2026: build berhasil; 37 pengujian Laravel lulus
dengan 135 assertion; lima pengujian frontend lulus. Browser memverifikasi XLSX
sintetis, koreksi harga hingga tiga baris dapat ditambahkan, serta pembuatan
paket/item dengan PNG lokal sampai simulasi kirim dan tampilan baca saja.
Penilaian akhir visual menyatakan empat perbaikan terselesaikan: koreksi impor,
penahanan overflow tabel, status/kelengkapan pada mobile, dan area kontrol 40px.
