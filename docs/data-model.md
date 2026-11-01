# Model data K-4

Skema dibuat melalui empat migrasi domain dan tiga migrasi infrastruktur bawaan
Laravel. ERD (`erd.mmd`), kamus (`schema-dictionary.md`), dan metadata
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
| Reviu | review_aspects, item_reviews, review_results, review_notes, note_responses |
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
Tanggapan dapat mengacu versi perbaikan tanpa menimpa catatan awal.

## KKR dan supervisi

Dokumen KKR memiliki versi dengan snapshot narasi, identitas, lingkup, hasil, serta
template. Entri menjaga keterlacakan ke reviu dan urutan tabel. Hash snapshot dan
berkas ekspor disimpan terpisah. Persetujuan terikat pada versi KKR, dengan satu
keputusan per tingkat pada versi tersebut; revisi baru memerlukan keputusan baru.
Relasi yang telah dipakai tidak dapat dihapus melalui penghapusan induk biasa.

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
