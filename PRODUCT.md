# Sistem Informasi SHS New

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Operator SKPD menyusun paket usulan SHS/SBU, melampirkan bukti survei per item,
  mengirim usulan, serta menanggapi catatan dan memperbaiki usulan organisasinya.
- BKAD mengelola referensi/periode, memeriksa administrasi dan kelengkapan usulan,
  serta memantau dan merekap usulan lintas SKPD sesuai kewenangannya.
- Pemeriksa mereviu item dalam penugasannya dan menyusun hasil/rekomendasi serta KKR.
- Ketua Tim mengatur penugasan, menyampaikan catatan resmi, dan melakukan supervisi.
- Pengendali Teknis melakukan supervisi tingkat teknis setelah persetujuan Ketua Tim
  pada versi yang sama.
- Admin mengelola akun, akses, referensi/periode, dan log teknis; peran Admin tidak
  otomatis membuka isi substantif reviu atau KKR.

## Product Purpose

Menampung pengusulan SHS dan SBU dari SKPD serta mendukung pemeriksaan kelengkapan,
reviu Inspektorat, tanggapan/perbaikan, supervisi berjenjang, dan penyusunan KKR.
Proses yang sebelumnya dikerjakan melalui Excel dan Word perlu dapat ditelusuri
berdasarkan usulan, item, dokumen, versi, pelaku, dan keputusan.

## Operating Context

- Klien adalah Inspektorat Daerah; permintaan tercatat sebagai
  REQ/004/DISKOMINFO/2026 di Panel PSE.
- Kebutuhan, kriteria penerimaan, matriks akses, dan kartu pengerjaan resmi dibaca
  dari PSE. PRODUCT.md tidak menggantikan sumber tersebut.
- Alur yang disepakati: Operator SKPD → BKAD → Pemeriksa → Ketua Tim →
  Pengendali Teknis → KKR sesuai hasil dan kewenangan masing-masing.
- Laptop/desktop merupakan perangkat kerja utama; ponsel digunakan untuk
  memantau dan tindakan singkat. Ini dikonfirmasi pengembang pada 1 November 2026.
- Alur UI pertama yang dipilih: daftar usulan Operator SKPD → isi/impor item →
  kirim usulan.
- Target prototipe 30 November 2026; penyelesaian keseluruhan 1 Februari 2027.

## Capabilities and Constraints

- Laravel 13 dan MySQL; lingkungan lokal menggunakan PHP 8.4.
- Target prototipe fungsional menggunakan database dan unggah berkas sungguhan.
  Rancangan UI K-3 pada /prototype/operator merupakan demonstrasi terpisah:
  data sintetis berada di memori tab, file dibaca lokal, dan kirim disimulasikan.
  K-10/K-11 menyediakan alur nyata terpisah pada /usulan: draf MySQL, pratinjau
  XLSX, versi item, bukti privat, pemeriksaan dan pengiriman sesuai periode.
- Usulan dapat diisi melalui formulir atau diimpor dari Excel, dengan pratinjau
  serta penjelasan baris bermasalah sebelum disimpan.
- Bukti dan dokumen pendukung terhubung dengan item dan versinya.
- Usulan dan KKR berversi; arsip final tidak ditimpa.
- Keluaran KKR mencakup pratinjau dan unduhan Word (.docx) mengikuti template
  instansi. PDF KKR bukan keluaran yang disepakati.
- Akses dibatasi menurut peran, organisasi, penugasan, tindakan, dan versi;
  catatan internal harus dibedakan dari catatan resmi kepada SKPD.
- Berkas bukti disimpan privat. Identitas akun/tim dan jejak aktivitas memuat
  data pribadi dan mengikuti klasifikasi resmi di PSE.
- Sistem awal berdiri sendiri; integrasi dengan sistem BKAD yang telah ada
  belum disepakati dengan klien.
- Hosting disiapkan pengembang. Staging ditunda atas arahan pengembang.
- Backend sesi, pengaturan akun/akses, periode/referensi, usulan dan bukti sudah
  tersedia. K-12 menyediakan verifikasi BKAD dan kelengkapan ulang per item
  berversi; K-13 menyediakan tim, penugasan item, daftar Pemeriksa dan pengalihan
  utama dengan riwayat. K-14 menyediakan hasil aspek, harga/rekomendasi terpisah,
  beberapa catatan internal, dan penyelesaian reviu tervalidasi. Lima aspek awal
  disetujui pengembang sebagai prototipe, bukan SOP final. K-15 menyediakan
  catatan resmi, tanggapan, revisi satu item, perbandingan versi dan keputusan
  tindak lanjut beralasan; supervisi dan KKR dilanjutkan pada kartu masing-masing.

## Brand Commitments

- Nama kerja: Sistem Informasi SHS New; instansi: Inspektorat Daerah.
- Bahasa antarmuka Indonesia dengan istilah kerja yang konsisten.
- Pengembang meminta keseluruhan nuansa referensi Hearth dipertahankan:
  sidebar gelap, bidang kerja krem, panel terang, aksen oranye, dan tabel rapi.
  Referensi visual ini dikonfirmasi pada 1 November 2026.
- Revisi UI yang dikonfirmasi pengembang: font Plus Jakarta Sans, dengan jarak
  antarelemen lebih rapat untuk pemakaian desktop dan mobile.
- Identitas/logo instansi akan diberikan kemudian; jangan membuat atau
  menyatakan identitas resmi yang belum tersedia.

## Evidence on Hand

- Contoh struktur Excel: KKR REVIU SHS TA 2026.xlsx.
- Contoh Word: KKR Reviu SHS 2026_Herlin.docx dan
  KKR Reviu SHS 2026 - Lalacute.docx.
- Enam screenshot Hearth dari pengembang: Applications, Documents, Calendar,
  Analytics, Dashboard, dan Leads. Screenshot merupakan referensi tampilan,
  bukan permintaan menambahkan fitur CRM, kalender, atau analitik baru.
- Snapshot PSE diperiksa pada 1 November 2026: Peta layar dan dokumen Rancangan UI
  masih kosong; rancangan awal termasuk lingkup K-3.
- Untuk demonstrasi dan pengujian gunakan data sintetis, tanpa memasukkan
  baris data klien atau rahasia ke kode, dokumen, gambar, atau commit.

## Product Principles

- Pengguna melihat data dan tindakan sesuai kewenangan serta lingkup kerjanya.
- Hubungan item, bukti, catatan, versi, dan keputusan harus dapat ditelusuri.
- Validasi menjelaskan letak masalah dan cara memperbaikinya sebelum pengiriman.
- Status dan hasil tindakan harus terlihat jelas bagi pelaku berikutnya.

## Open Decisions

- Identitas resmi, logo, dan penyajian nama aplikasi pada header/login.
- Rincian komposisi layar, pedoman komponen, serta gambar rancangan K-3.
- Kebutuhan dan ketersediaan integrasi dengan sistem yang telah dipakai BKAD.
