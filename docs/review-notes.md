# Catatan resmi dan tanggapan per item — K-15

K-15 melanjutkan catatan internal K-14 tanpa mengubah ikatan reviu ke versi
asal. `/catatan` hanya menampilkan catatan sesuai SKPD dan penugasan; daftar dan
detail memakai query pembatasan yang sama. Admin teknis tidak otomatis melihat
substansi. Pemeriksa lama kehilangan akses setelah item dialihkan.

## Pengiriman dan tanggapan

- Ketua Tim yang menjadi anggota aktif penugasan mengirim catatan substansi
  internal melalui konfirmasi eksplisit; pemeriksa tidak mengirim sendiri.
- BKAD mengirim catatan administrasi dari pemeriksaan item sebelum item mendapat
  penugasan. Catatan ini tidak menggantikan keputusan kelengkapan K-12.
- Operator SKPD pemilik menjawab catatan resmi berstatus `sent`. Tanggapan
  menunggu pemeriksaan sebelum jawaban berikutnya; keputusan `needs_revision`
  membuka kembali catatan untuk SKPD. Teks catatan asal tidak ditimpa.
- Operator dapat memakai editor/bukti K-10/K-11 untuk item dengan catatan yang
  menunggu tanggapan. Perubahan merupakan versi privat baru. Item lain dan impor
  paket tidak ikut dibuka. Hanya versi terbaru item tersebut yang dikirim bila
  pilihan sertakan revisi dicentang. Tanpa centang, draf tetap privat dan
  tanggapan terikat pada versi terkirim terbaru sebelumnya.
- Pengiriman revisi memerlukan periode terbuka, referensi/data lengkap, serta
  bukti yang benar-benar tersedia. Teks jawaban tanpa revisi tidak memerlukan
  pembukaan kembali periode pengusulan. Identitas versi tidak diterima dari
  isian bebas pengguna; server menetapkannya dari item catatan.

## Pemeriksaan tanggapan

Pemeriksa utama saat ini memutuskan catatan substansi; BKAD memutuskan catatan
administrasi. Setiap keputusan wajib beralasan. `resolved` menutup catatan,
sedangkan `needs_revision` mengembalikannya kepada SKPD. Tanggapan dan keputusan
tetap tersedia pada putaran berikutnya, dengan pengguna dan waktu WIB.

Tabel perbandingan menampilkan versi asal catatan dan versi yang dikirim bersama
masing-masing tanggapan: uraian, spesifikasi, kode, satuan, rekening, harga,
dan tautan bukti privat. Perbandingan bukan penilaian aspek baru. Hasil reviu
K-14 dan versi asalnya tetap tersimpan; penutupan catatan tidak menulis ulang
penilaian atau membuat persetujuan/KKR final.

## Integritas

Migrasi K-15 menambahkan `note_response_decisions`: satu keputusan per tanggapan,
FK restrict ke tanggapan/pengambil keputusan. Putaran baru menambah tanggapan
baru. Item `awaiting_response` bila ada catatan `sent`, `recheck` bila semua
catatan terbuka sedang menunggu pemeriksaan, lalu kembali ke alur verifikasi/
reviu setelah semua selesai. Reviu tidak dapat diselesaikan ketika catatan resmi
masih terbuka. Catatan internal yang belum dikirim tetap internal.

Mutasi catatan mengunci penugasan (bila substansi), paket, item, lalu catatan;
izin dibaca ulang setelah penguncian. Token mencakup catatan, item/versi,
tanggapan/keputusan terakhir, dan penugasan untuk menolak formulir usang serta
pengiriman ganda. Transaksi gagal tidak menerbitkan sebagian revisi. Usulan/
penugasan tertutup dan item yang telah masuk KKR final menolak tindak lanjut.

## Bukti dan batas

`NoteWorkflowTest` menguji alur pengiriman, pembatasan lintas peran/SKPD/anggota,
revisi/bukti satu item, privasi draf, perbandingan, putaran beralasan, konflik,
periode/berkas hilang, pengalihan, final, validasi HTML, dan CSRF pada MySQL uji.
UI mengikuti sistem Hearth/Jakarta compact. Pemeriksaan visual/keyboard pada
akun nyata dan UAT pemohon belum dijalankan. Tidak ada email/notifikasi eksternal
atau integrasi yang ditambahkan.
