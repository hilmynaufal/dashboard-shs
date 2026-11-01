# Usulan, impor Excel, dan bukti privat — K-10/K-11

## Alur yang tersedia

Operator dengan izin sesuai SKPD membuka `/usulan`, membuat paket SHS/SBU pada
periode berstatus Dibuka, lalu menambahkan item melalui formulir atau impor XLSX.
Draf tersimpan pada MySQL dan dapat dibuka setelah keluar/masuk kembali. Kode
barang, satuan, dan uraian harus sah saat menyimpan item; spesifikasi, rekening,
dan harga boleh dilengkapi kemudian. Harga disimpan sebagai desimal tanpa
perhitungan floating point; kode tetap berupa teks, termasuk nol awal.

Halaman pemeriksaan menampilkan masalah per item. Pengiriman mensyaratkan
spesifikasi, rekening aktif, harga positif, dan minimal satu bukti yang tersedia
secara fisik untuk tiap item. Pratinjau impor aktif harus diselesaikan/dibatalkan.
Pengiriman memeriksa ulang izin, keadaan draf, seluruh referensi, serta rentang
tanggal periode dalam transaksi. Periode dikunci sebelum pemeriksaan tanggal
WIB melalui `SubmissionWindow::assertOpen()`. Pengiriman di luar rentang ditolak
meskipun pengaturan periode masih Dibuka. Status, waktu kirim, dan versi item
terkirim dicatat; versi tersebut tidak dapat diubah melalui endpoint draf.

Izin kirim diperiksa terpisah dari izin edit. Pemilik berizin kirim tetap dapat
memeriksa dan mengirim paket lengkap tanpa memperoleh akses mengedit item.
Penanda versi formulir mencegah tab lama menimpa perubahan pada tab lain.

## Pratinjau dan penyimpanan impor

`/usulan/{id}/impor` menerima XLSX, bukan XLS/CSV. Pemilihan lembar di browser
bersifat bantuan; pembacaan, pemeriksaan, dan hash server tetap menentukan hasil.
Server menyimpan baris sumber beserta nomor, pemetaan, galat, dan koreksi; berkas
XLSX asli tidak diarsipkan setelah unggahan sementara selesai. Koreksi tidak
menimpa nilai sumber asli.

Pemetaan mencakup uraian, spesifikasi, kode barang, rekening, satuan, dan harga.
Kolom yang berisi data tetap dapat dipilih meskipun judulnya kosong. Judul,
pengulangan judul, dan kelompok ditandai agar tidak otomatis menjadi item;
pengguna dapat menjadikan kelompok sebagai item atau melewati baris bermasalah.
Formula tidak dihitung dan sel kode numerik perlu dikoreksi/diformat teks untuk
mencegah hilangnya nol awal. Semua baris item harus valid sebelum satu transaksi
menambahkan item; pratinjau sendiri tidak membuat item.

Kunci paket + hash berkas + lembar mencegah unggahan/penyimpanan ulang berkas yang
sama menggandakan item dalam paket tersebut. Pratinjau yang dibatalkan dapat
dilanjutkan dengan unggahan sama; perubahan pemetaan lama ditolak. Berkas yang
diubah atau paket lain merupakan sumber impor berbeda.

Batas server: unggahan paling tinggi 5 MB dan mengikuti batas PHP yang lebih
kecil; 32 MB isi arsip, 512 entri, 12.000 baris berisi, 50.000 baris fisik,
60 kolom, 250.000 sel, serta 10.000 karakter per sel. Pembaca juga memeriksa
penggunaan memori, struktur XML, dan alamat sel. Tidak mengekstrak arsip,
mengeksekusi formula, atau mengambil entitas/tautan eksternal. Maksimal lima
pratinjau aktif per paket; unggahan dibatasi laju permintaannya.

## Bukti dan versi

Satu item dapat memiliki beberapa PDF/JPG/PNG. Tipe isi diperiksa server dan
ukuran maksimal mengikuti batas PHP, paling tinggi 10 MB. UI menampilkan batas
efektif. Berkas diberi nama penyimpanan acak di disk privat `local`; nama asli,
waktu unggah, pemilik, ukuran, dan hash dicatat. Hash sama pada versi item aktif
ditolak. Penulisan gagal membersihkan berkas yang baru disimpan.

Ubah item, tambah bukti, dan lepas bukti membuat versi item baru. Versi lama dan
tautan buktinya dipertahankan; melepas bukti dari versi baru tidak menghapus
berkas versi lama. Riwayat versi dapat dibuka dengan izin sesuai lingkupnya.
Unduhan `/bukti/{id}` selalu memeriksa sesi, peran, organisasi, dan cakupan
penugasan; tidak ada URL publik penyimpanan. Respons berupa attachment dengan
`no-store`, `nosniff`, dan kebijakan sandbox.

Operator hanya melihat data SKPD-nya. BKAD dapat membaca paket yang sudah
dikirim; Pemeriksa hanya item/bukti dalam penugasannya. Admin tidak otomatis
memperoleh isi usulan. Pratinjau mentah dan riwayat impor hanya untuk pemilik
paket berizin; koreksi tetap hanya tersedia saat paket masih draf.

## Verifikasi dan batas

Pada 2 November 2026 seluruh pengujian lokal lulus: 88 pengujian PHP, 879 asersi
pada MySQL khusus `shs_new_testing`; build Vite berhasil. Di antaranya 15
pengujian alur usulan mencakup keluar/masuk, harga desimal, kirim terblokir,
periode nyata, versi dan konflik tab, XLSX nyata, koreksi/duplikasi, bukti privat,
akses antar-SKPD/penugasan, CSRF, serta regresi izin kirim tanpa izin edit.
Regresi frontend memeriksa tombol nonaktif dari server tidak diaktifkan kembali
oleh pemulihan halaman; hasil eksekusi frontend dibuktikan melalui CI.

Tidak ada perubahan skema pada K-10/K-11; tabel versi, impor, bukti, dan audit
yang digunakan berasal dari migrasi K-4. K-9 kriteria rentang pengiriman kini
dibuktikan pada endpoint sesungguhnya; arsip final KKR masih menunggu K-18.
K-8 lingkup usulan/bukti diuji melalui endpoint nyata, tetapi tindakan reviu dan
supervisi tetap menunggu kartu masing-masing.

Tampilan memakai font, palet, dan rasio compact yang disetujui. Browser
terautentikasi sebagai Operator belum tersedia untuk pemeriksaan visual dan
keyboard; render server/pengujian otomatis bukan kelulusan UAT. Demo terpisah
`/prototype/operator` tetap sintetis dan tidak menjadi sumber data `/usulan`.
Verifikasi BKAD, pengembalian/tanggapan resmi, reviu, supervisi, dan ekspor KKR
belum disediakan oleh kartu ini.
