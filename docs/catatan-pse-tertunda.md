# Catatan untuk Panel PSE (tertunda)

Panel PSE tidak terjangkau pada 12 November 2026. Pekerjaan berjalan lokal
atas arahan pengembang. Butir di bawah belum tercatat di PSE dan perlu
dimasukkan saat panel dapat diakses. Tidak ada status kartu yang diubah.

## Butir tambahan yang perlu diusulkan

| # | Butir | Alasan | Status lokal |
|---|---|---|---|
| 1 | Finalisasi tampilan: ikon, penanda produk sementara, kerangka sidebar/topbar, varian panel, status seragam, gerak | Permintaan pengembang 12 November 2026; tidak tercakup kartu K-2 sampai K-21 | Dikerjakan lokal 12 November 2026; belum di-commit |
| 2 | Kemudahan input: combobox referensi, pemilih rekening, format rupiah, pemilih SKPD, penugasan massal, unggah seret-lepas (B1 sampai B17) | Permintaan pengembang; isian kode rekening dan sejenisnya masih manual | Dikerjakan lokal 12 November 2026; belum di-commit |
| 3 | Beranda menjadi ringkasan kerja per peran | Dipilih pengembang saat penyusunan brief; menambah isi layar di luar kartu | Dikerjakan lokal memakai hitungan `ProgressBoard` |
| 4 | Pencarian nama referensi menurut kode (pendukung combobox) | Diperlukan agar pilihan tersimpan menampilkan nama saat form dibuka ulang | Tidak diperlukan; endpoint pencarian yang ada sudah cukup |
| 5 | Saran kategori catatan, tebakan pemetaan kolom impor, unggah beberapa bukti, bilah kelengkapan paket | Sisa brief yang memerlukan perubahan sisi server | Belum dikerjakan; menunggu keputusan |

## Modul Penilaian ZI · 13 November 2026

Usulan butir tambahan: Penilaian ZI untuk area Manajemen Perubahan. Pengembang meminta implementasi langsung berdasarkan brief dan kamus 16 butir. Alur mencakup pembukaan LKE, pengajuan unit, reviu dan perbaikan per butir, serta rekap final area. Kode kartu belum tersedia.

Usulan skenario UAT pemohon: buat penilaian sintetis sebagai Admin, ajukan 16 jawaban sebagai Operator, kembalikan satu butir sebagai TPI, ganti bukti sebagai Operator, lalu periksa kiriman ulang dan selesaikan seluruh butir. Harapkan riwayat bukti tetap dapat ditelusuri, subtotal tim ikut berubah bila skor rencana berubah, dan finalisasi mengunci rekap area. Alasan: memeriksa serah terima antarpengguna serta aturan subtotal khusus yang disepakati. Cocokkan dengan skenario Panel sebelum mengusulkan.

## Instrumen ZI berbasis data · 13 November 2026

PSE masih tidak terjangkau; dicatat lokal atas arahan pengembang. Usulan butir tambahan: bobot, skala nilai, batas persen, dan ambang target dipindahkan dari kode `ZiInstrument::result()` ke `resources/instruments/zi-manajemen-v1.rules.json`, sebagai langkah awal sebelum area LKE lain (Tatalaksana, SDM, Akuntabilitas, Pengawasan, Pelayanan Publik, Hasil) ditambahkan. Alasan: analisis terhadap LKE Disdukcapil Jawa Barat 2025 WBK menunjukkan bobot dan skala tertulis di kode, sedangkan LKE memuat 12 area dan tipe jawaban `Jumlah`, `Rupiah`, dan `Nilai (0-4)` yang belum didukung.

Status lokal: dikerjakan 13 November 2026, belum di-commit. Penilaian lama tetap memakai `zi-manajemen-v1`.

Lanjutan 13 November 2026: area Penataan Tatalaksana (Pemenuhan 3,5 dan Reform 3,5; 15 butir dari lembar Jawaban baris 23-33 dan 137-144) ditambahkan sebagai instrumen baru `zi-v2` (31 butir, dua area). Penilaian baru memakai `zi-v2`. Ambang dihitung per area (60% WBK, 75% WBBM dari bobot area), seperti kolom "Pemenuhan Nilai Min" di LKE. Redaksi baris 32 dan 33 identik di LKE; belum dibedakan, perlu ditinjau pemilik instrumen. Lanjutan 13 November 2026: area Penataan Sistem Manajemen SDM Aparatur (Pemenuhan 5 dan Reform 5; 21 butir dari baris 36-58 dan 147-151) ditambahkan ke `zi-v2` yang belum dirilis dan belum dipakai penilaian mana pun (basis data lokal tidak aktif, tidak ada commit), sehingga versi tidak dinaikkan. Butir 151 (penurunan pelanggaran disiplin) memakai rumus LKE tersendiri: tahun ini 0 = 100%, tahun lalu 0 dengan tahun ini lebih dari 0 = 0%, kenaikan = 0%; tiga angka sumber diisi unit (jumlah sanksi opsional, tidak mempengaruhi skor seperti di LKE). Setelah `zi-v2` dirilis, perubahan butir harus lewat versi baru.

Belum dikerjakan: tiga area Pengungkit lain (Akuntabilitas, Pengawasan, Pelayanan Publik), Hasil, tipe jawaban `Jumlah`/`Rupiah`/`Nilai (0-4)`, keputusan batas 100% pada persentase.

## Template instrumen ZI yang dikelola admin · 13 November 2026

Usulan butir tambahan: admin atau pengguna berizin dapat menyusun dan menerbitkan instrumen ZI (area, kelompok, butir, rubrik, bobot, skala, ambang WBK/WBBM) dalam satu modul, tanpa mengubah kode. Alasan: analisis LKE Disdukcapil Jawa Barat 2025 menunjukkan struktur sudah berbentuk data; WBK dan WBBM pada kedua LKE contoh berbagi butir yang sama dan berbeda terutama pada ambang. Pengembang meminta lanjut langsung.

Rencana bertahap: (1) simpan instrumen di basis data dengan skema JSON yang sama dan pemuat yang menggantikan berkas; (2) layar admin dengan siklus draf, validasi, terbit, dan kunci, memakai jenis rumus yang ada, izin tersendiri (`zi.template`), dan jejak audit; (3) impor dari Excel serta tipe jawaban `Jumlah` dan `Nilai (0-4)` untuk Hasil. Batasan yang diketahui: rumus bebas tidak didukung (hanya jenis rumus tetap), sub-kelompok bersarang (LHKPN) belum ada, versi terbit tidak boleh diedit, dan bobot resmi dari pedoman sehingga izin ubah dibatasi.

Status lokal: tahap 1 dan tahap 2 dikerjakan 13 November 2026, belum di-commit. Tahap 3 belum dikerjakan.

Tahap 2 (layar admin, `/penilaian-zi/template`): izin baru `zi.template` untuk Admin; daftar versi; draf dibuat dengan menyalin versi yang ada; penyuntingan ambang WBK/WBBM, area, kelompok, dan butir (redaksi, tipe jawaban, kriteria, rumus persentase); pemeriksaan sebelum terbit (jumlah bobot kelompok sama dengan bobot area, setiap butir masuk satu kelompok, jumlah baris kriteria sesuai tipe); terbit mengunci versi; riwayat perubahan per versi di tabel `zi_instrument_events`; versi instrumen dipilih saat membuka penilaian. Belum ada di layar: pengubahan skala nilai dan batas persen, pengaturan ketergantungan antarkelompok (rumus Penyusunan Tim Kerja hanya ikut tersalin), penimpa redaksi per target, pengurutan ulang, dan impor Excel. Tampilan belum diperiksa di browser karena MySQL lokal mati.

Usulan skenario UAT pemohon: sebagai Admin, salin versi terbit menjadi draf, ubah bobot satu kelompok sehingga jumlahnya tidak sama dengan bobot area, dan pastikan penerbitan ditolak dengan penjelasan. Perbaiki bobot, tambah satu butir dengan kriteria lengkap, terbitkan, lalu buka penilaian baru memakai versi itu. Harapkan versi terbit tidak dapat diubah lagi, versi sumber dan penilaian lama tidak berubah, dan riwayat mencatat setiap perubahan beserta pelakunya. Alasan: memeriksa bahwa perubahan instrumen hanya berlaku lewat versi baru. Cocokkan dengan skenario Panel sebelum mengusulkan.

## Hal lain untuk disinkronkan

- DESIGN.md sudah bertambah bagian "Finalisasi ruang kerja". Perbarui dokumen
  Rancangan UI di PSE dari bagian itu.
- Kode kartu untuk branch/commit belum tersedia; isi setelah butir diterima.
- Skenario UAT untuk kontrol input baru perlu diusulkan setelah implementasi.
- Rincian lengkap: `docs/brief-finalisasi-ui-input.md`.
