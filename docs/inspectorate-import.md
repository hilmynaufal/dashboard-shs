# Impor Inspektorat

Menu **Impor Inspektorat** (`/impor-inspektorat`) memasukkan workbook usulan
SHS/SBU yang sudah berada di Inspektorat langsung sebagai item **Siap direviu**,
tanpa pengiriman SKPD dan verifikasi BKAD. Ketua Tim kemudian menunjuk pemeriksa
lewat alur penugasan yang sudah ada.

## Akses

Izin `inspectorate.import` dimiliki **Ketua Tim** dan **Admin** (migrasi
`2026_11_13_001300` memberikannya ke peran yang ada; `RolePermissions` untuk
instalasi baru). Operator SKPD, BKAD, Pemeriksa, dan Pengendali Teknis mendapat
403 dan tidak melihat menunya. Admin hanya mengunggah dan memantau impor; hak
Admin atas isi usulan tidak berubah dan penugasan tetap milik Ketua Tim
(`assignments.manage`).

## Format berkas

Berkas `.xlsx` (maksimal 5 MB). Hanya lembar bernama `Data_SSH` (SHS) dan
`Data_SBU` (SBU) yang dibaca; lembar lain, termasuk lembar `... REVIU` berisi
catatan kerja, diabaikan. Baris judul dicari pada 15 baris pertama dan
mensyaratkan kolom `kode`, `uraian`, `satuan`, `harga`, `pengusul`. Kolom lain
yang dikenali: `spek`, `rekening` … `rekening_10`, `kelompok`, `TKDN`, `Tipe`
(Data lama → existing, Data baru → new), `Keterangan` (disimpan sebagai
justifikasi versi). Baris tanpa harga, spesifikasi, dan satuan dianggap judul
kelompok dan dilewati. Pembaca memakai `ProposalXlsx` (tanpa formula, batas
baris/sel/memori yang sama dengan impor SKPD).

Baris bermasalah (kode/uraian/satuan kosong, kode berformat angka, harga bukan
angka atau nol) tidak ikut diimpor dan ditampilkan di pratinjau.

## Pengusul dan SKPD

Kolom Pengusul dapat berisi beberapa nilai dipisah titik koma. Satu item dibuat
per pengusul; `ASB` dilepas bila ada pengusul lain; kosong menjadi
"Pengusul belum tercantum". Aturan ini sama dengan seed demo (`demo:reset-review`),
sehingga workbook yang sama menghasilkan jumlah item yang sama.

Pratinjau mencocokkan tiap pengusul ke SKPD aktif lewat kode atau nama yang sama
persis. Yang tidak cocok default ke **SKPD baru** (kode = nilai pengusul), dan
dapat diganti ke SKPD yang ada. Beberapa pengusul yang dipetakan ke SKPD sama
masuk paket yang sama.

## Pratinjau dan penyimpanan

Unggahan menyimpan baris ter-parse di `inspectorate_import_rows`; **tidak ada
item dibuat** sebelum Simpan impor. Pratinjau menampilkan ringkasan, pemetaan per
pengusul, referensi baru, dan daftar baris bermasalah. Berkas yang sama (hash)
pada periode yang sama tidak dapat diimpor dua kali setelah tersimpan.

Referensi (kode barang, satuan, rekening) yang belum ada akan **ditambahkan**
saat impor disimpan, dengan konfirmasi eksplisit. Referensi nonaktif yang dipakai
berkas memblokir penyimpanan sampai diaktifkan di menu Referensi.

Simpan impor berjalan dalam satu transaksi dengan baris impor terkunci dan
token halaman (`record_version`): pemetaan yang berubah di tab lain menolak
formulir lama. Hasilnya, per SKPD dan jenis:

- paket `proposals` bernomor `IMP-{impor}-{SHS|SBU}-{skpd}`, status `verification`;
- `proposal_items` berstatus `ready` dengan versi 1 (`submitted_at` terisi,
  TKDN, asal data, rekening, dan snapshot referensi);
- `verification_results` `ready` oleh pengimpor (alasan: diimpor Inspektorat);
- audit `inspectorate_import.previewed/completed/cancelled`.

Baris sementara dihapus setelah impor disimpan atau dibatalkan; ringkasan
(paket, jumlah item, referensi/SKPD baru, baris tidak diimpor) tetap pada
`inspectorate_imports.summary`.

## Setelah impor

Item muncul sebagai kandidat penugasan untuk SKPD dan periode yang sesuai. Halaman
hasil impor menyediakan tautan **Buat penugasan** per SKPD (formulir penugasan
terisi periode dan SKPD) bagi Ketua Tim. Penunjukan pemeriksa memakai halaman
Pilih item siap reviu yang ada (maksimal 50 item per simpan).

## Verifikasi

Pengujian `InspectorateImportTest` mencakup akses per peran, pratinjau tanpa item,
konfirmasi referensi baru, pembuatan paket/item/versi/verifikasi, kandidat
penugasan, impor ganda, formulir basi, pembatalan, referensi nonaktif, dan
berkas tidak dikenali.
