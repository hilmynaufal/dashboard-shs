# CI K-6

GitHub Actions menjalankan `.github/workflows/ci.yml` untuk push, pull request,
atau pemicu manual. Dua job berjalan terpisah:

1. PHP 8.4 dan MySQL 8.4: validasi composer.lock, pemasangan dependensi terkunci,
   pemeriksaan platform, migrasi dan seeder definisi akses, lalu seluruh uji PHP.
2. Node 24: pemasangan dari package-lock.json dan build frontend Vite.

Backend tests tidak memerlukan manifest Vite di checkout baru: TestCase menonaktifkan
integrasi aset hanya dalam proses pengujian. Job build terpisah tetap memverifikasi
aset produksi. Aplikasi operasional tetap menggunakan Vite secara normal.

Database shs_new_testing pada CI dibuat dalam container sementara tanpa data nyata.
Akun root tanpa password hanya dipakai container uji sekali pakai tersebut; konfigurasi
ini bukan konfigurasi hosting. Kredensial .env lokal dan kredensial staging tidak
dikirim ke CI. Hak token workflow hanya contents:read dan versi action dikunci ke SHA.

Laporan JUnit disimpan sebagai artifact php-test-report selama tujuh hari. Workflow
tidak mengunggah .env, log aplikasi, bukti usulan, atau dump database.

Staging ditunda atas arahan pengembang. K-6 belum memenuhi kriteria staging sampai
alamatnya tersedia, terisi di PSE, dan benar-benar dapat dibuka. Workflow saat ini
hanya menjalankan pengujian/build; tidak melakukan deployment atau migrasi hosting.
