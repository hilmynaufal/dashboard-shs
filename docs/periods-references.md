# Periode dan referensi — K-9

Admin dan BKAD mengelola data melalui menu **Periode pengusulan** dan **Data
referensi**. Setiap endpoint memeriksa sesi aktif dan izin `periods.manage` atau
`references.manage`; empat peran lain ditolak, termasuk melalui URL langsung.
Tidak ada perubahan skema atau data awal bawaan pada kartu ini.

## Periode pengusulan

`/periode` menyediakan daftar, pencarian nama/tahun, filter status dan paginasi.
`/periode/buat` serta `/periode/{id}/ubah` memuat nama, tahun anggaran, tanggal
buka/tutup, dan status Draf/Dibuka/Ditutup. POST/PATCH menolak tanggal tidak sah
atau tanggal tutup sebelum tanggal buka. Periode tidak dihapus; status dapat
ditutup. Tahun anggaran dibekukan setelah ada proposal atau penugasan terkait.

Tanggal di formulir ditafsirkan sebagai **Asia/Jakarta (WIB)**, kemudian disimpan
sebagai waktu UTC. Buka mulai 00.00.00 WIB, tutup sampai seluruh detik 23.59.59 WIB
pada tanggal yang dipilih. Batas akhir memperhitungkan waktu pecahan detik meski
kolom SQL berpresisi detik. `SubmissionPeriod::availability()` membedakan Draf,
Ditutup, Belum dibuka, Terbuka, dan Berakhir. Status pengaturan Dibuka belum
menjamin ketersediaan di luar rentang tanggal.

`SubmissionWindow::assertOpen()` dipakai controller pengiriman K-10 setelah
memeriksa izin, dengan periode terkunci dalam transaksi. Pengujian endpoint
nyata membuktikan pengiriman ditolak untuk periode Draf/Ditutup, belum dibuka,
dan berakhir, lalu diterima dalam rentang terbuka.

## Kode barang, rekening dan satuan

`/referensi/barang`, `/referensi/rekening`, `/referensi/satuan` memakai whitelist
tabel tetap. Setiap jenis memiliki daftar/cari/filter/paginasi, formulir tambah
dan ubah, serta penghapusan referensi belum digunakan. Kode bertipe teks dengan
batas 60 karakter; nol awal dan pemisah tetap utuh. Kode unik per jenis, termasuk
penanganan konflik unik oleh MySQL saat penulisan. Nama dan status aktif/nonaktif
dapat diperbarui. Relasi induk kode barang yang sudah ada tidak diubah melalui
formulir ini; pengelolaan hierarki baru tidak ditambahkan diam-diam.

Hapus menggunakan konfirmasi di UI dan batas foreign key `RESTRICT` pada database.
Jika referensi sudah dirujuk versi usulan (termasuk draf), rekening versi item,
atau relasi induk/anak, penghapusan ditolak dengan pesan agar dinonaktifkan bila
sesuai. FK juga melindungi penulisan terkait yang berbarengan. Audit penghapusan
hanya tersimpan bila transaksi benar-benar berhasil.

Formulir menyertakan penanda versi keadaan record; perubahan pada tab lain
menolak penyimpanan/penghapusan lama dengan 409. Semua mutasi memakai transaksi,
CSRF dan catatan pelaku/waktu/nilai sebelum-sesudah. Penulisan tanpa perubahan
tidak menghasilkan audit tambahan. Pencarian memperlakukan `0`, `%` dan `_`
sebagai teks, dan pencarian tahun hanya memakai empat digit yang sah.

## Verifikasi dan status kartu

Pengujian nyata pada MySQL `shs_new_testing` memeriksa izin Admin/BKAD versus empat
peran lain, penambahan/pembaruan, tanggal terbalik/tidak sah, batas WIB dan pecahan
detik, tahun periode terpakai, kode unik dan nol awal, pencarian/filter/paginasi,
konflik versi, CSRF, render HTML galat, serta penghapusan referensi terpakai.
Pembaruan referensi melalui endpoint diperiksa tidak mengubah snapshot versi item,
rekening versi, dan isi JSON versi KKR yang sudah memiliki tanggal finalisasi.

Kriteria pertama, kedua, dan ketiga K-9 dapat dicentang. Kriteria keempat menunggu pembuktian unduh ulang arsip final
pada K-18, meskipun perlindungan relasi dan snapshot tersimpan sudah diuji. K-9
tetap **Berjalan**, tanpa menyatakan UAT selesai.

Sesi browser yang sebelumnya menampilkan beranda ternyata sudah kedaluwarsa saat
halaman baru dibuka. Halaman terautentikasi pada K-9 diperiksa melalui render
server dan pengujian otomatis; belum diklaim sebagai verifikasi visual/keyboard
browser. Tidak ada kata sandi atau data referensi nyata yang disalin ke dokumen.
