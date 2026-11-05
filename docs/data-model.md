# Model data K-4

Skema awal dibuat melalui empat migrasi domain dan tiga migrasi infrastruktur
bawaan Laravel. K-14 menambahkan migrasi perubahan kolom dan aspek prototipe.
K-15 menambahkan tabel `note_response_decisions` dari migrasi yang telah diterapkan.
Tambahan dokumentasi K-15 diselaraskan dari kode migrasi; metadata lama tidak
diekspor ulang seluruhnya. K-18 menambah kolom dan keunikan pada tabel ekspor
yang sudah ada; K-19 memakai skema yang sudah ada tanpa migrasi. Jumlah tabel
dan foreign key tetap 45 tabel dan 68 foreign key. Angka ini tidak menyatakan
bahwa metadata/ERD telah diekspor ulang setelah K-18.
ERD (`erd.mmd`), kamus (`schema-dictionary.md`), dan metadata
(`schema-metadata.json`) diekspor dari MySQL yang telah dimigrasikan melalui:

```sh
php artisan app:document-schema
```

Ekspor dibatasi pada database koneksi aktif, tanpa membaca baris data pengguna.
Untuk pemeriksaan silang, jumlah entitas dan foreign key dalam Mermaid harus sama
dengan tabel dan foreign key metadata. Nama database lingkungan tidak diekspor.

## Kelompok data

| Kelompok | Tabel inti |
| --- | --- |
| Akses | organizations, users, roles, permissions, role_user, permission_role |
| Referensi | submission_periods, item_classifications, account_codes, units, reference_documents |
| Usulan | proposals, proposal_items, proposal_item_versions, item_version_accounts |
| Bukti | price_sources, evidence_files, item_version_evidence, verification_results |
| Impor | import_batches, import_rows |
| Penugasan | review_assignments, assignment_members, assignment_items, assignment_transfers |
| Reviu | review_aspects, item_reviews, review_results, review_notes, note_responses, note_response_decisions |
| KKR | kkr_documents, kkr_versions, kkr_entries, kkr_approvals, kkr_exports |
| Aktivitas | activity_logs |

Tabel migrasi, sesi, cache, token reset, dan antrean Laravel turut ditampilkan dalam
ERD lengkap supaya dokumentasi cocok dengan seluruh skema yang dibuat.

## Identitas, versi, dan nilai

- Kode klasifikasi tidak menjadi identitas unik barang; item memakai ID internal dan
  public_id tetap. Nomor versi unik di dalam item yang sama.
- Harga memakai DECIMAL(20,2); harga usulan, pembanding, rekomendasi, dan komponen
  sumber harga disimpan terpisah. Persentase TKDN bersifat opsional.
- Data referensi disalin pada versi item dan rekening; perubahan master tidak
  mengganti snapshot lama. Dokumen regulasi menyimpan versi dan masa berlaku.
- Satu versi dapat memakai banyak rekening dan banyak bukti. Berkas menyimpan
  lokasi storage privat, ukuran, jenis, hash, organisasi pemilik, dan pengunggah.
- Identitas batch impor unik per paket, hash berkas, dan nama sheet; nomor baris
  unik dalam batch. Nilai mentah dan kesalahan pemetaan tetap dapat ditelusuri.

## Penugasan dan reviu

Satu cakupan penugasan hanya memiliki satu pemeriksa utama. Riwayat pengalihan
disimpan tersendiri. Foreign key gabungan memastikan pemeriksa tercatat sebagai
anggota penugasan tersebut dan versi yang ditunjuk memang milik item yang dipilih.
Reviu atas versi berikutnya tetap terhubung ke item dalam cakupan yang sama.

Hasil aspek, proses pemeriksaan, dan status catatan disimpan terpisah. Nilai
`not_assessed` tidak sama dengan `compliant`. Catatan administratif dapat berdiri
tanpa item_review, sedangkan catatan substansi dapat mengacu pemeriksaan terkait.

K-14 menambahkan rekomendasi per aspek pada `review_results.recommendation` serta
penanda perubahan pada `item_reviews.lock_version`. Definisi aspek dan status
wajib disalin ke snapshot saat mulai; lima aspek `PROTO_*` merupakan definisi
prototipe yang telah disetujui pengembang. Jumlah tabel/foreign key tidak berubah.
Tanggapan mengacu versi terkirim per item tanpa menimpa catatan awal. K-15
menyimpan keputusan beralasan pada `note_response_decisions`, satu per tanggapan;
putaran perbaikan berikutnya membuat tanggapan baru. Lihat `review-notes.md`.

## KKR dan supervisi

Dokumen KKR memiliki versi dengan snapshot narasi, identitas, lingkup, hasil, serta
template. Entri menjaga keterlacakan ke reviu dan urutan tabel. Hash snapshot dan
berkas ekspor disimpan terpisah. Persetujuan terikat pada versi KKR, dengan satu
keputusan per tingkat pada versi tersebut; revisi baru memerlukan keputusan baru.
Relasi yang telah dipakai tidak dapat dihapus melalui penghapusan induk biasa.

K-17 menggunakan tabel KKR yang sudah ada tanpa perubahan skema. Snapshot
`supervision-v1` dan entri dibuat server, di-hash secara kanonik dan dibandingkan
dengan sumber sebelum pengajuan/persetujuan. Pengembalian mewajibkan penyusun menyiapkan versi baru;
persetujuan teknis setelah Ketua Tim mengunci final. Catatan terbuka disertakan
serta diakui pada pengajuan dan tiap persetujuan. Lihat `kkr-supervision.md`.
K-18 menyimpan template `instansi-shs-v1`, tujuan, dan langkah kerja bersama
snapshot versi. Preview dan Word menggunakan model presentasi yang sama.
Migrasi `2026_11_05_000800_freeze_kkr_word_presentations` menambah
`kkr_exports.render_snapshot` (JSON nullable), `render_sha256` (CHAR(64) nullable),
dan unique pada `kkr_version_id`, tanpa tabel atau foreign key baru. Ketika
persetujuan teknis mengunci final, model presentasi beserta hash dan berkas DOCX
disimpan bersama. Berkas memakai disk privat `local`; byte size dan SHA-256
diverifikasi saat dibaca. Arsip yang hilang atau rusak ditolak tanpa dibuat
ulang atau ditimpa. Final lama tanpa arsip memperoleh satu arsip melalui kunci
versi saat akses pertama yang berwenang. Lihat `kkr-word-export.md`.

K-19 menghitung paket/item/catatan/KKR dari tabel yang sama pada tiap pemuatan
halaman, dengan query ringkasan dan rincian dalam satu transaksi. Tidak ada
tabel penghitung, cache agregat, atau perubahan status bisnis oleh pemantauan.
Reviu selesai memakai reviu terlihat terbaru per item; hasil sesuai dan catatan
terbuka dihitung terpisah. Lihat `progress-monitoring.md`.

## Batas tanggung jawab skema

Foreign key, keunikan, tipe desimal, dan enum menjaga integritas struktural. Skema
belum mengimplementasikan autentikasi, policy akses, validasi organisasi lintas
relasi, urutan persetujuan, kewajiban alasan, validasi tanggal, batas TKDN, atau
penguncian perubahan versi final. Aturan tersebut diterapkan serta diuji pada
kartu fitur terkait. Kolom snapshot/hash sendiri tidak menjamin imutabilitas.

Relasi umum subject_type/subject_id pada activity_logs bersifat polimorfik dan
tidak memiliki foreign key tunggal; aplikasi harus menentukan objek dan hanya
mencatat atribut yang diperlukan, tanpa kata sandi atau rahasia.

## Pengujian

```sh
php artisan test
```

DomainSchemaTest menolak pengosongan database selain MySQL `shs_new_testing`
sebelum menjalankan RefreshDatabase. Data uji sintetis dibuat di dalam transaksi.
Database uji harus terpisah dan memang boleh dikosongkan.

Pengujian memeriksa presisi uang/snapshot, keunikan versi, perlindungan master
terpakai, banyak rekening, identitas impor, ketepatan versi/anggota penugasan,
dan keterikatan persetujuan pada versi KKR. Ini uji pengembang, bukan kelulusan UAT.
