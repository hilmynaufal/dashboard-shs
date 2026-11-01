# Keputusan teknis K-1

Sumber keputusan: profil teknis resmi Panel PSE untuk
REQ/004/DISKOMINFO/2026 dan proposal versi 1 yang telah disetujui pemohon serta
Diskominfo. Dokumen ini mencatat keputusan yang sudah diterapkan; perubahan
profil resmi tetap dilakukan pengembang di PSE.

| Komponen | Keputusan dan alasan |
| --- | --- |
| PHP | Lingkungan terverifikasi PHP 8.4.21. Versi ini sesuai dependensi yang terkunci; penggunaan 8.3/8.5 memerlukan pemeriksaan kompatibilitas. |
| Laravel | Laravel 13, terpasang 13.39.0; kerangka yang dipilih pengembang untuk autentikasi, pembatasan akses, validasi, transaksi, migrasi, dan pengujian aplikasi. Implementasi fitur tetap mengikuti kartunya. |
| MySQL | Relasi usulan, versi, penugasan, dan persetujuan memerlukan foreign key serta batas unik; nilai uang menggunakan tipe desimal. Migrasi dan pengujian integritas MySQL telah dijalankan pada K-4. |
| Hosting | Disiapkan pengembang sesuai profil resmi; kapasitas, konfigurasi server, backup, dan akses deployment diverifikasi pada pekerjaan staging K-6. |
| Berkas | Storage privat dengan akses unduh melalui pemeriksaan kewenangan, sesuai kebutuhan dokumen reviu dan klasifikasi resmi. |
| Dependensi | composer.lock dan package-lock.json disimpan agar versi yang diuji dapat dipasang ulang. |

## Perintah resmi

```sh
php artisan serve --host=127.0.0.1 --port=8000
php artisan test
npm run build
php artisan migrate
```

Pada PowerShell, npm.cmd digunakan bila npm.ps1 dibatasi kebijakan sistem.
AGENTS.md di akar repositori merupakan salinan profil PSE. README memuat langkah
persiapan lingkungan; .env lokal dan kredensial tidak dimasukkan ke Git.

## Bukti yang tersedia

- K-2: aplikasi kosong berjalan, dua uji fondasi lulus, build berhasil, AGENTS.md
  dan panduan perintah masuk commit lokal.
- K-4: migrasi MySQL berhasil, keseluruhan sembilan uji/13 assertions lulus,
  dokumentasi skema diekspor dari 44 tabel dan 66 foreign key aktual.
- Proposal versi 1 di PSE memuat arsitektur dan teknologi yang diusulkan serta
  persetujuan kedua pihak; profil resmi sudah memuat bahasa, framework, database,
  hosting, dan perintah proyek.

Tautan repositori remote, CI yang benar-benar berjalan di remote, dan staging
belum menjadi bukti K-1; pekerjaan tersebut berada pada K-6.
