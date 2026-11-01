# Operator SKPD — ruang usulan

Mode: Operate. Target: /prototype/operator. Kartu: K-3.

Pengguna mengelola banyak item dari spreadsheet, mengaitkan bukti per item, lalu
mengajukan paket yang lengkap. Desktop utama; ponsel untuk pemantauan singkat.
Nuansa Hearth dipertahankan sebagaimana DESIGN.md; bukan eksplorasi identitas baru.

First viewport: sidebar arang 240px, breadcrumb tipis, judul Usulan SKPD, aksi
Buat usulan, ringkasan inline, perhatian perbaikan, filter status, tabel paket.
Data paket menonjol; kelengkapan dan status terpisah. Hindari dashboard dekoratif.

Interaksi utama: buka paket → pilih item → lengkapi data dan bukti → kembali
ke paket → lihat pemeriksaan kelengkapan → simulasi kirim. Impor XLSX mempunyai
pratinjau, pemetaan kolom, galat per baris, dan penyimpanan eksplisit.

Kondisi penting: hasil kosong, data panjang, file invalid/terlalu besar, baris
impor invalid, paket belum lengkap, paket sudah terkirim/read-only. Simulasi
ditandai selalu terlihat. Tidak ada akun nyata atau data klien pada rancangan.

Ponsel: daftar paket menjadi baris grid dengan kelengkapan dan status langsung
terlihat; metadata sekunder disembunyikan. Tabel item dan pratinjau impor tetap
bergulir di wadahnya. Editor dan pemeriksaan memakai satu kolom.

Layar pendamping: tanggapan/catatan, ringkasan, dan rekap CSV sintetis. Semua
perubahan hanya berada di memori tab; refresh mereset demo. File dibaca di
browser dan pengiriman disimulasikan, tanpa persistensi bisnis.

Motion: perubahan keadaan 160ms, drawer 180ms; tidak ada entrance halaman.
Pengujian visual dua putaran maksimum, desktop dan mobile dalam satu batch.

Revisi pengembang: Plus Jakarta Sans dan jarak compact; judul/subjudul 2px,
heading bawah 12px desktop/mobile, tabel desktop 8px vertikal per sel,
panel 16px dan antar-isian 12px. Label sidebar biasa, pilihan aktif 500.
