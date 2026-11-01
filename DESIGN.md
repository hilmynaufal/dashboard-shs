---
name: Sistem Informasi SHS New
description: Ruang kerja usulan SHS/SBU bernuansa Hearth yang hangat dan terstruktur.
colors:
  canvas: "#f4f1eb"
  surface: "#fffdfa"
  surface-muted: "#eeebe5"
  sidebar: "#211e1a"
  sidebar-active: "#353029"
  ink: "#292620"
  muted: "#716b61"
  line: "#e8e3db"
  accent: "#c44920"
  accent-hover: "#aa3815"
  accent-soft: "#fae9df"
  success: "#087b65"
  warning: "#94600a"
  danger: "#b33930"
  info: "#6650ae"
typography:
  headline:
    fontFamily: "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "32px"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-.035em"
  headline-mobile:
    fontFamily: "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "27px"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-.035em"
  title:
    fontFamily: "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "18px"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "-.02em"
  body:
    fontFamily: "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    lineHeight: 1.5
  table:
    fontFamily: "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "12px"
    lineHeight: 1.5
  button:
    fontFamily: "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 500
    lineHeight: 1.4
rounded:
  field: "8px"
  control: "10px"
  panel: "16px"
  pill: "20px"
spacing:
  compact: "8px"
  control-gap: "8px"
  section-gap: "12px"
  desktop-gutter: "28px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "#fff"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "10px 15px"
  button-primary-hover:
    backgroundColor: "{colors.accent-hover}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "10px 15px"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.muted}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "10px 4px 10px 15px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.field}"
    padding: "10px 12px"
  navigation-active:
    backgroundColor: "{colors.sidebar-active}"
    textColor: "#fff9ed"
    fontWeight: 500
    rounded: "{rounded.control}"
    padding: "11px 13px"
  status-neutral:
    backgroundColor: "{colors.surface-muted}"
    textColor: "#696155"
    rounded: "{rounded.pill}"
    padding: "4px 8px"
  panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.panel}"
---

# Design System: Sistem Informasi SHS New

## Overview

**Creative North Star: "Hearth"**

Pertahankan arah yang dikonfirmasi pengembang pada 1 November 2026: sidebar
arang hangat, bidang kerja krem, panel terang, dan aksen oranye. Dunia visual
ini mendukung pekerjaan administrasi yang padat melalui tabel rapi, hierarki
tipografi, jarak, dan pengelompokan. Identitas resmi instansi masih menyusul.

Dokumen ini merekam rancangan K-3 di `/prototype/operator`, dengan sumber
`resources/css/operator.css`. Desktop/laptop adalah perangkat kerja utama;
ponsel mendukung pemantauan dan tindakan singkat. Prototipe lokal memakai data
sintetis dan perubahan dalam memori tab; refresh mereset demo. Berkas dibaca
di browser, pengiriman disimulasikan, dan tidak ada persistensi bisnis atau
pengiriman ke BKAD. Ini bukan bukti penyelesaian fitur backend.

**Key Characteristics:**

- Navigasi arang dengan bidang kerja krem dan panel putih hangat.
- Aksen oranye untuk tindakan utama; status selalu disertai teks.
- Tabel sebagai bidang kerja, dengan kelengkapan terpisah dari status paket.
- Ikon SVG garis, sudut lembut, dan bayangan tipis.

## Colors

Palet hangat memakai satu aksen utama dan warna semantik untuk pemeriksaan.
Frontmatter memuat nilai normatif yang mengikuti custom properties CSS.

### Primary

- **Oranye terakota** (`accent`): tombol utama dan tautan tindakan.
- **Terakota pekat** (`accent-hover`): hover tombol utama.
- **Terakota lembut** (`accent-soft`): bidang aksen pendukung.

### Neutral

- **Krem kanvas** (`canvas`): ruang kerja di luar panel.
- **Putih hangat** (`surface`): tabel, panel, dan isian.
- **Krem teduh** (`surface-muted`): bidang sekunder.
- **Arang hangat** (`sidebar`, `sidebar-active`): navigasi dan pilihan aktif.
- **Tinta hangat** (`ink`), **abu hangat** (`muted`), dan **garis pasir**
  (`line`): teks utama, konteks sekunder, dan pemisah.

Warna semantik `success`, `warning`, `danger`, dan `info` menandai kelengkapan,
perhatian, galat, dan reviu. Badge memiliki pasangan latar lembut tersendiri.

**The Status Text Rule.** Warna tidak menggantikan label status atau penjelasan galat.

## Typography

Plus Jakarta Sans dibundel melalui Vite untuk judul dan isi, dengan fallback
sans-serif pada frontmatter. Angka memakai `tabular-nums`.

- **Headline:** judul halaman; versi mobile mengikuti `headline-mobile`.
- **Title:** judul bagian; subjudul tingkat berikutnya memakai 15px/600.
- **Body:** teks dasar aplikasi.
- **Table:** isi tabel dan label item utama.
- **Button:** tindakan dengan bobot sedang dan teks singkat.

Header tabel saat ini memakai 10px/500; metadata pendamping berkisar 9–11px.
Ukuran metadata ini mencatat kepadatan implementasi, bukan skala isi umum
untuk layar baru. Petunjuk dan galat harus tetap terbaca pada perangkat tujuan.

## Layout

Sidebar desktop tetap selebar 240px. Konten dan topbar memakai gutter desktop
pada frontmatter, meningkat menjadi 36px mulai lebar 1600px. Editor data dan
bukti tersusun dua kolom dengan kolom bukti 310px; pemeriksaan pengiriman
memisahkan hasil dan tindakan. Jarak berulang memakai 8, 12, dan 16px. Jarak judul ke subjudul 4px;
heading ke bagian berikutnya 18px desktop/16px mobile. Topbar desktop 64px
dan mobile 56px. Padding vertikal baris tabel desktop 10px; panel form 20px
desktop/16px mobile. Target interaksi tetap minimal 40px.

Pada lebar maksimal 1000px sidebar menjadi drawer dengan scrim dan konten tanpa
offset. Pada maksimal 720px gutter menjadi 16px, heading dan aksi tersusun,
statistik dua kolom, serta editor/form/pemeriksaan satu kolom. Daftar paket
berubah menjadi baris grid: nama paket di atas, kelengkapan dan status langsung
terlihat di bawah; metadata sekunder disembunyikan. Tabel item dan pratinjau
impor tetap bergulir di wadah tabel sendiri tanpa melebarkan halaman. Wadah
tabel menjadi acuan posisi label tersembunyi pembaca layar.

## Elevation & Depth

Kedalaman berasal dari lapisan warna hangat, pemisah tipis, dan bayangan lembut.
Panel memakai bayangan ambient ringan; tombol utama sedikit terangkat; dialog
dan toast lebih tinggi untuk menunjukkan lapisan interaksi. Nilai bayangan,
focus ring, dan transisi tersimpan pada sidecar.

Transisi keadaan umumnya 160ms; drawer memakai 180ms ease-out. Tidak ada entrance
halaman. Preferensi reduced-motion menonaktifkan transisi.

## Shapes

Panel dan dialog memakai sudut `panel`, tombol memakai `control`, dan isian
form memakai `field`. Badge/filter berbentuk pil. Area unggah memakai garis
putus-putus dan sudut 12px. Ikon garis umumnya 16–19px. Penanda produk sementara
tidak menyatakan logo resmi instansi.

## Components

### Buttons

Tombol utama terakota dengan teks putih; sekunder berupa panel terang dengan
garis inset; ghost transparan. Hover menegaskan keadaan tanpa perubahan tata
letak. Fokus keyboard berupa outline 3px dengan offset 3px. Disabled memakai
opacity .46. Tombol, filter, select pemetaan, sel koreksi impor, ikon, dan
toolbar menyediakan target interaksi minimal 40px.

### Inputs / Fields

Label tetap terlihat dengan bantuan dekat isian. Form memakai stroke hangat,
latar panel, dan tinggi minimal 42px. Galat impor menandai sel, menjelaskan
masalah per baris, dan memperbarui kelayakan simpan saat pengguna mengoreksi.
Isian readonly memakai latar lebih teduh.

### Navigation

Sidebar memuat identitas sementara, organisasi, menu kerja, periode, dan pengguna.
Pilihan aktif memakai bidang arang lebih terang serta tanda oranye tipis.
Pada layar sempit, tombol menu membuka drawer berpenutup.

### Chips / Status

Filter terpilih memakai tinta gelap dengan teks terang. Badge status menggabungkan
teks, titik/ikon, dan warna semantik. Kelengkapan item memakai angka dan progress
bar terpisah dari status proses paket.

### Cards / Containers

Panel tabel menyatukan toolbar, area gulir, dan footer/paginasi. Panel form dan
bukti memakai ruang internal 16px. Editor item memakai halaman penuh;
dialog untuk paket singkat, informasi, preview bukti, dan konfirmasi.

### Import and Submission

Impor XLSX melalui unggah, pemetaan lembar/kolom, pratinjau, koreksi atau lewati
baris, lalu simpan eksplisit. Pemeriksaan pra-kirim menjelaskan kekurangan dan
jalur perbaikan sebelum simulasi. Label prototipe tetap terlihat. Respons,
ringkasan, dan rekap CSV memakai komponen yang sama. Pesan kosong, galat file,
pemuatan, dan keberhasilan memberi tindakan berikutnya yang konkret.

## Do's and Don'ts

### Do:

- **Do** pertahankan sidebar arang, kanvas krem, panel terang, dan aksen oranye.
- **Do** tampilkan kelengkapan dan status sebagai dua informasi berbeda.
- **Do** sediakan fokus terlihat, target interaksi minimal 40px, dan teks untuk status.
- **Do** nyatakan batas simulasi dan keberadaan berkas di browser dengan jujur.

### Don't:

- **Don't** menjadikan grafik dekoratif sebagai pengganti daftar kerja.
- **Don't** menyatakan identitas resmi instansi sebelum asetnya tersedia.
- **Don't** menyebut simulasi lokal sebagai penyimpanan server atau pengiriman nyata.

## Revisi kepadatan

Atas arahan pengembang, gunakan Plus Jakarta Sans bobot 400/500/600 serta
jarak yang lebih rapat pada header, ringkasan, toolbar, tabel, dan formulir.
Ukuran teks dipertahankan; kepadatan berasal dari margin, padding, dan gap.
Label menu sidebar kembali ke bobot biasa (400), dengan pilihan aktif 500.
Percobaan kepadatan kedua memakai gap antarseksi 12px, judul/subjudul 2px,
dan jarak filter ke tabel 8px.
