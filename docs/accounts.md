# Masuk dan kelola akun — K-7

UI menggunakan Plus Jakarta Sans dan rasio compact yang disetujui pengembang.
Data akun tersimpan di MySQL; halaman ini terpisah dari demo `/prototype/operator`.
Modul usulan/reviu belum dihubungkan ke halaman operasional pada kartu ini.

## Halaman dan tindakan

| Halaman | Akses dan perilaku |
| --- | --- |
| `/masuk` | Email/kata sandi; sesi baru setelah login; galat umum untuk salah/nonaktif; batas percobaan K-5 tetap berlaku. |
| `/aplikasi` | Beranda akun aktif, menampilkan peran dari database dan tautan sesuai izin. |
| `/profil` | Nama/jabatan sendiri; email/peran/SKPD tidak dapat diubah melalui profil. |
| `/profil/kata-sandi` PUT | Kata sandi saat ini, baru, dan konfirmasi; minimal 12 karakter dengan huruf besar/kecil serta angka, maksimal 72 byte sesuai bcrypt. |
| `/admin/akun` GET/POST | Hanya Admin; daftar, pencarian, filter aktif/nonaktif, paginasi 15 akun, pembuatan akun. |
| `/admin/akun/buat` | Identitas, peran awal, SKPD aktif (wajib untuk Operator SKPD), kata sandi awal dan konfirmasi. |
| `/admin/akun/{id}/status` PATCH | Hanya Admin; aktif/nonaktif; akun sendiri tidak dapat dinonaktifkan. |

Formulir memakai CSRF, validasi server, dan pesan Indonesia. Endpoint sesi JSON
K-5 tetap didukung. Halaman dilindungi middleware sesi aktif dan `no-store`;
akses langsung ke URL Admin tetap memeriksa `accounts.manage`. Tidak ada hapus
akun atau registrasi publik. Pengelolaan peran/SKPD akun yang sudah ada mengikuti
K-8 dan belum dinyatakan selesai oleh K-7.

Perubahan kata sandi menyimpan hash, memutar sesi/token CSRF, menghapus remember
token, dan membuat sesi lain ditolak saat permintaan berikutnya melalui fingerprint
hash dalam sesi. Penonaktifan akun/organisasi juga menolak sesi aktif berikutnya.
Log aktivitas mencatat pembuatan akun, profil, status dan perubahan kata sandi;
log kata sandi tidak menyimpan nilai lama/baru maupun hashnya.

## Admin pertama

Pengembang dapat menjalankan `php artisan app:create-admin` secara interaktif di
lingkungan yang telah dimigrasikan. Nama/email diminta di terminal dan kata sandi
dimasukkan lewat prompt tersembunyi beserta konfirmasi. Tidak ada kredensial bawaan
atau argumen kata sandi pada command line. Perintah hanya dapat membuat Admin awal
jika belum ada akun berperan Admin; akun selanjutnya dibuat di aplikasi. Perintah
ini tidak dijalankan otomatis pada startup, migrasi atau deploy.

Tidak ada akun nyata yang dibuat oleh implementasi/pengujian ini. Akun sintetis
pengujian berada pada MySQL `shs_new_testing`, yang memang disediakan khusus untuk
pengujian dan boleh dikosongkan.

## Pengujian penerimaan pengembang

`php artisan test` mencakup login/keluar enam peran, halaman sesuai akses, penolakan
tautan Admin bagi kelima peran lain, CSRF, pembuatan akun dan hash, duplikasi email,
SKPD wajib/nonaktif, pencegahan eskalasi profil, validasi kata sandi, sesi kedaluwarsa
akibat perubahan kata sandi/status, filter/paginasi, dan provisioning Admin pertama.
Skenario UAT resmi sudah tersedia di PSE; pengujian ini tidak meluluskan UAT.

Pemeriksaan browser mencakup login pada desktop, ponsel, ukuran browser pengembang,
dan kredensial salah. Halaman setelah login diperiksa melalui render server dan
pengujian otomatis; tampilan dan interaksi keyboard setelah login belum dicoba
pada browser dengan akun yang diprovisikan. Pemeriksaan sumber independen menemukan
dua masalah aksesibilitas mobile (nama tautan profil dan fokus drawer tertutup),
yang diperbaiki melalui label eksplisit, `inert`, dan pengelolaan fokus. Catatan
ini membedakan verifikasi kode dari verifikasi interaksi browser.
