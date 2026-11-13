# Bukti opsional untuk demo lokal

Atas permintaan pengembang, demonstrasi lokal dapat mengirim usulan dan
kelengkapan ulang tanpa lampiran, serta menjalankan verifikasi BKAD atas item
tersebut. Ini pengecualian demo, bukan perubahan persyaratan operasional.

Aktifkan `DEMO_ALLOW_MISSING_EVIDENCE=true` pada konfigurasi lingkungan lokal.
`DemoMode::allowsMissingEvidence()` mensyaratkan lingkungan aplikasi `local`
dan nilai konfigurasi boolean `true`. Nilai bawaan pada `.env.example` adalah
`false`; flag tidak berlaku pada testing, staging, atau production. Untuk
memulihkan kewajiban bukti lokal, ubah flag menjadi `false`.

Hanya pemeriksaan lampiran kosong di `ProposalDraft::problems()` yang
dikecualikan. Harga, spesifikasi, rekening, referensi aktif, periode, konfirmasi,
otorisasi, versi, dan keberadaan fisik lampiran yang sudah terhubung tetap
diperiksa. Tidak ada bukti palsu, keputusan otomatis, atau pengiriman otomatis.
Aktivitas pengiriman/verifikasi mencatat `evidence_optional_demo`.

Halaman pemeriksaan paket, kelengkapan ulang, dan verifikasi menampilkan
pemberitahuan demo. Judul kesiapan dan pernyataan Operator tidak mengklaim
bahwa bukti telah lengkap saat pengecualian aktif. Token visual tetap mengikuti
DESIGN.md; tidak ada perubahan CSS atau identitas.

Pengujian endpoint MySQL mencakup pengiriman tanpa lampiran, verifikasi BKAD,
kelengkapan ulang, flag nonaktif, penolakan di luar lingkungan lokal, isian dan
periode yang belum memenuhi syarat, serta lampiran fisik yang hilang.
Pengujian otomatis bukan pengesahan UAT oleh pemohon.

Perubahan ini diminta langsung oleh pengembang saat mencoba seed Excel.
PSE masih ditunda sesuai arahan sebelumnya; catatan ini disiapkan untuk
rekonsiliasi ketika layanan tersedia. Tidak ada kartu atau skenario UAT yang
dipindahkan atau diluluskan melalui perubahan ini.
