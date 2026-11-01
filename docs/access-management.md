# Pengaturan akses — K-8

Admin membuka **Kelola akun → Atur akses** pada akun yang dipilih. Halaman
`/admin/akun/{id}/akses` memuat identitas hanya-baca, peran resmi, dan SKPD.
GET maupun PATCH memeriksa `access.manage`; sesi aktif saja tidak cukup.

## Perilaku yang sudah dibuat

- Peran dipilih dari enam peran resmi, tanpa membuat peran atau izin tindakan
  bebas dari formulir. Peran internal dapat digabung; lingkup penugasan tetap
  diperiksa oleh policy setiap resource.
- Operator SKPD digunakan sebagai peran tersendiri. Penggabungan dengan peran
  lintas SKPD ditolak untuk menjaga kriteria akses organisasi sendiri, karena
  aplikasi belum memiliki pemilihan konteks peran terpisah dalam satu sesi.
- Operator wajib memiliki SKPD aktif. SKPD nonaktif yang sudah tersimpan tetap
  terlihat dengan penanda nonaktif dan tidak diam-diam dihapus oleh formulir.
- Penggantian SKPD mengubah lingkup akses pengguna; proposal, versi, bukti, dan
  penugasan lama tetap pada organisasinya semula dan tidak dipindahkan.
- Peran dan organisasi disimpan dalam satu transaksi. Identitas, kata sandi,
  status aktif, dan grant tindakan bebas tidak dapat diubah melalui endpoint ini.
- Formulir menyertakan penanda versi dari keadaan akses. Jika peran, organisasi,
  atau status akun berubah sejak formulir dibuka, penyimpanan ditolak (409 pada
  JSON) dan pengguna diminta memuat ulang data, bukan menimpa perubahan terbaru.
- Aktor dan target dikunci dengan urutan ID yang sama pada perubahan akses/status,
  lalu izin aktor diperiksa kembali di dalam transaksi. Admin tidak dapat mencabut
  peran Admin miliknya sendiri; perubahan oleh Admin lain tetap diperbolehkan.
- Riwayat mencatat `account.access_updated`, pelaku, waktu, target, peran dan
  organisasi sebelum/sesudah. Penyimpanan tanpa perubahan tidak menambah riwayat.
  Kata sandi maupun hashnya tidak dicatat.

## Berlakunya perubahan

`RequireActiveSession` membaca ulang identitas dan status organisasi pada setiap
permintaan. `User::permissionCodes()` membaca grant dari database tanpa menyimpan
salinan izin dalam sesi. Karena itu sesi pengguna yang sudah masuk memperoleh
izin terbaru saat mengakses aplikasi kembali; perubahan kata sandi tidak diperlukan.

Keanggotaan penugasan saja tidak memberikan akses bila peran globalnya dicabut.
Sebaliknya, peran global Pemeriksa/Ketua Tim/Pengendali Teknis tidak menggantikan
syarat keanggotaan, cakupan item, versi, maupun tingkat persetujuan pada policy.

## Bukti dan batas penyelesaian

Pengujian MySQL `shs_new_testing` mencakup endpoint pengaturan akses yang nyata:
izin lima peran non-Admin, pembaruan sesi setelah perubahan, perubahan lingkup
SKPD tanpa pemindahan proposal, pencabutan peran reviu, formulir kedaluwarsa,
proteksi Admin sendiri, validasi organisasi/peran, masukan HTML rusak, dan CSRF.
Regresi policy K-5 tetap dijalankan untuk item, bukti, catatan, penugasan, dan KKR.

Kriteria pertama K-8 dapat ditandai terpenuhi. Kriteria 2–4 mencakup pembatasan
data/dokumen dan tindakan bisnis melalui endpoint sesungguhnya; controller
usulan, unggah/unduh bukti, reviu, serta supervisi belum dibuat. Pengujian policy
atau route khusus pengujian tidak diperlakukan sebagai pengganti pengujian
controller tersebut. K-8 tetap Berjalan sampai integrasi pada kartu terkait
(terutama K-10/K-11/K-13/K-14/K-17/K-18) selesai dan diuji kembali.

Atas arahan pengembang, belum ada Admin nyata yang diprovisikan untuk browser.
Formulir ini diperiksa melalui render server dan pengujian otomatis; belum
diklaim sebagai verifikasi visual/keyboard browser atau kelulusan UAT.
