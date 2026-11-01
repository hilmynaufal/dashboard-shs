# Sistem Informasi SHS New

Permintaan **REQ/004/DISKOMINFO/2026**, Inspektorat Daerah. Fondasi **K-2**.

## Lingkungan

PHP 8.4 untuk lock dependensi yang telah diverifikasi, Composer 2, Node.js 24 LTS,
npm, dan MySQL 8. Penggunaan PHP 8.3/8.5 perlu pemeriksaan kompatibilitas tersendiri.
Dependensi dikunci dalam composer.lock dan package-lock.json.

## Persiapan dan menjalankan

```sh
composer install
```

Salin .env.example menjadi .env (PowerShell: `Copy-Item .env.example .env`), lalu:

```sh
php artisan key:generate
npm ci --ignore-scripts
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Pada PowerShell gunakan `npm.cmd` bila npm.ps1 dibatasi kebijakan sistem.
Halaman awal: http://127.0.0.1:8000; pemeriksaan hidup aplikasi: /up.
Untuk perubahan aset jalankan `npm run dev` di terminal kedua.

## Database dan storage

Koneksi aplikasi memakai MySQL. Isi DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME,
dan DB_PASSWORD pada .env lokal sesuai lingkungan sendiri. Sesi/cache memakai
berkas dan antrean sinkron agar fondasi bisa dijalankan sebelum database disiapkan.

Setelah database tersedia, perintah migrasi adalah:

```sh
php artisan migrate
```

Migrasi awal Laravel tersedia; koneksi MySQL, skema domain, dan ERD dibuktikan pada
K-4. Jangan menjalankan migrasi destruktif pada database operasional.
Storage local menggunakan direktori privat; tidak dibuat tautan publik otomatis.

## Pemeriksaan

```sh
composer validate --strict
php artisan test
npm run build
```

Uji fondasi memeriksa halaman awal dan /up tanpa memakai database. Uji domain
berikutnya menggunakan MySQL khusus shs_new_testing, terpisah dari operasional.
K-4 menambahkan pengujian integritas skema; pengujian akan mengosongkan database
khusus shs_new_testing. Jangan arahkan database uji ke data penting.

Dokumentasi skema aktual dapat diperbarui setelah migrasi MySQL:

```sh
php artisan app:document-schema
```

Lihat [model data](docs/data-model.md), [ERD](docs/erd.mmd), serta
[kamus skema](docs/schema-dictionary.md). Pengujian fondasi dan skema belum
membuktikan kelulusan fitur, policy akses, atau UAT.

## Ketentuan

- Baca AGENTS.md yang diambil dari Profil teknis PSE.
- Kode kartu pada branch/commit, misalnya feat/K-2-kerangka.
- Jangan simpan .env, token, kunci, data nyata, atau lampiran klien di Git.
- analysis/ dan artefak proposal lokal diabaikan dari repositori aplikasi.
- Layar mengikuti K-3 serta dokumen UI dan Peta layar PSE yang disepakati.
- Halaman awal hanya menandakan fondasi berjalan; fitur mengikuti kartu masing-masing.
- Arahkan hosting ke public/, gunakan HTTPS dan APP_DEBUG=false saat operasional;
  CI dan staging dikerjakan pada K-6.
