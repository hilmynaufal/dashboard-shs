# Verifikasi kelengkapan per item — K-12

## Akses dan alur

BKAD membuka **Verifikasi kelengkapan** (`/verifikasi`) untuk melihat antrean
item versi terkirim. Pencarian memakai uraian, nomor paket, atau SKPD; filter
memisahkan Menunggu verifikasi, Perlu dilengkapi, Siap direviu, serta SHS/SBU.
Daftar dan detail hanya membaca versi yang sudah dikirim. Berkas pada revisi
yang belum dikirim tidak terbuka kepada BKAD.

`/verifikasi/{item}` menampilkan snapshot data, bukti privat, dan riwayat hasil
kelengkapan. Keputusan **Siap direviu** atau **Perlu dilengkapi** hanya dapat
dicatat untuk item Menunggu verifikasi pada paket terkirim/verifikasi/reviu.
Alasan pengembalian wajib diisi; alasan serta catatan keputusan dapat dibaca
Operator pemilik SKPD. Catatan ini adalah hasil kelengkapan administratif,
bukan catatan reviu substantif K-14/K-15.

Hasil dicatat pada `verification_results` dengan versi item, pelaku, dan waktu.
Keputusan sebelumnya tidak ditimpa. Status paket berpindah dari Diajukan menjadi
Verifikasi saat keputusan pertama; status setiap item tetap berdiri sendiri.
Tidak ada persetujuan seluruh paket yang membuka semua item untuk diedit.

## Kelengkapan oleh Operator

Detail paket menampilkan status item dan alasan pengembalian. Tindakan Ubah
hanya dibuka untuk item **Perlu dilengkapi** milik SKPD sendiri; item lain tetap
terkunci. Pengembalian tidak membuka penambahan item, impor, perubahan header,
atau pengiriman ulang seluruh paket.

Formulir data dan unggahan bukti memakai layanan versi K-10/K-11. Perubahan
membuat versi baru dan mempertahankan versi, bukti, serta hasil pemeriksaan
sebelumnya. BKAD tetap membaca versi terkirim sebelumnya selama Operator
menyusun perbaikan. Tautan langsung ke versi atau berkas baru juga ditolak bagi
BKAD sampai versi tersebut dikirim.

Operator membuka `/usulan/{paket}/item/{item}/kelengkapan` untuk memeriksa hasil
dan mengirim ulang hanya item itu. Pengiriman mensyaratkan versi perbaikan baru,
isian lengkap, referensi aktif, harga positif, bukti tersedia, konfirmasi, dan
periode pengusulan yang masih terbuka. Guard periode yang sama dengan pengajuan
awal diterapkan kembali; BKAD tetap dapat memeriksa di luar periode tersebut.
Izin kirim kelengkapan terpisah dari izin edit.

Pengiriman memberi waktu kirim pada versi baru dan mengembalikan status item
menjadi Menunggu verifikasi. Waktu kirim awal paket dan versi item lain tidak
diubah. BKAD lalu dapat memberi hasil baru untuk versi baru tersebut.

## Integritas dan keamanan

Mutasi mengunci paket, kemudian item, sebelum memeriksa kembali akun/izin dan
penanda versi halaman. Periode serta referensi dikunci saat diperlukan.
Halaman lama ditolak dengan pesan muat ulang; keputusan ganda dan perubahan
setelah siap ditolak berdasarkan keadaan item. Tautan item dari paket lain
ditolak, demikian pula akses lintas SKPD atau peran tanpa izin BKAD.
Setiap mutasi memakai CSRF dan audit dengan ID/status; alasan tidak disalin
ke log teknis. Unduhan tetap melalui policy bukti privat K-11.

Tidak ada perubahan skema, grant peran, atau data awal. Implementasi memakai
tabel item, versi, bukti, hasil verifikasi, dan audit yang sudah ada pada K-4.
Tidak ada akun nyata atau data klien yang dibuat untuk pengujian.

## Penugasan dan batas penyelesaian

`ItemVerification::readyForAssignment()` menyediakan calon item berdasarkan
status Siap direviu, versi terakhir yang sudah dikirim, dan keputusan siap
untuk versi tersebut. Item yang dikembalikan dalam paket sama tidak menjadi
syarat penghalang. Pengujian MySQL membuktikan hasil query ini.

K-13 kini memakai seleksi tersebut dan memeriksa kembali keadaan item di dalam
transaksi penugasan. Pengujian nyata Operator → BKAD → Ketua Tim → daftar
Pemeriksa membuktikan item siap dapat ditugaskan meskipun item saudara masih
perlu dilengkapi. Keempat kriteria K-12 kini terbukti melalui endpoint nyata;
kartu dapat dibawa ke **Tinjau** dengan bukti, tanpa klaim kelulusan UAT.

UI memperluas workspace Hearth dengan Plus Jakarta Sans dan jarak compact yang
disetujui. Pemeriksaan visual/keyboard menggunakan sesi BKAD dan Operator masih
tertunda; render HTML, pemeriksaan kode independen, serta pengujian otomatis
tidak diperlakukan sebagai pengganti pemeriksaan tersebut.

Verifikasi lokal 2 November 2026: 97 pengujian PHP/1.062 asersi lulus pada MySQL
khusus `shs_new_testing`, termasuk sembilan pengujian K-12; build Vite berhasil.
Pengujian mencakup antrean/versi terkirim, alasan wajib, item siap versus saudara
yang dikembalikan, penolakan edit/impor paket, perbaikan/bukti privat sebelum
kirim, riwayat versi, kirim ulang, izin terpisah, periode, berkas hilang,
referensi nonaktif, tautan langsung lintas peran/SKPD, dan CSRF.
