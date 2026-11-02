# Hasil reviu per item — K-14

## Aspek prototipe yang disetujui

Pada 2 November 2026 pengembang menyetujui lima aspek wajib untuk prototipe:
kelengkapan bukti, kesesuaian spesifikasi/satuan, dasar harga/biaya, perhitungan,
dan kesesuaian data pembanding. Kode `PROTO_*` dan keterangan di UI membedakannya
dari SOP final instansi. Belum ada layar pengubahan master aspek pada K-14.

Migrasi `000600` menambahkan definisi tersebut tanpa mengganti definisi yang
sudah ada. Ketika reviu dimulai, kode, nama dan kewajiban semua aspek aktif
disimpan sebagai `review_results.criterion_snapshot`. Perubahan master setelah
itu tidak mengubah aspek atau kewajiban dalam reviu yang sudah berjalan.

## Mulai dan simpan hasil

Daftar pekerjaan dan detail penugasan menyediakan **Buka reviu** untuk tiap ID
cakupan item. GET `/penugasan/{id}/item/{scope}/reviu` hanya menampilkan sumber
dan persiapan; tidak menulis data. Pemeriksa utama yang masih aktif dan memiliki
izin reviu memulai lewat POST. Sumbernya selalu versi awal terkirim yang terikat
pada cakupan, bukan data draft atau versi lain yang dikirim dari browser.

Reviu baru berisi hasil **Belum dinilai**. Status ini tidak dianggap Sesuai.
Pembuatan ulang untuk cakupan/versi sama mengembalikan reviu yang sudah ada,
tanpa menggandakan hasil. Bila tidak ada aspek aktif, reviu tidak dapat dimulai.

`/reviu/{id}` menampilkan data/bukti versi usulan dan penilaian berdampingan.
Setiap aspek mempunyai hasil, catatan/alasan dan rekomendasi tersendiri. Pilihan
hasil: Belum dinilai, Sesuai, Tidak sesuai, Belum cukup bukti, atau Tidak berlaku.
Alasan Tidak berlaku wajib diisi, termasuk pada aspek opsional.

Harga usulan tetap berasal dari versi usulan. Harga pembanding, rekomendasi
harga, dan rekomendasi item disimpan terpisah pada `item_reviews`. Nilai kosong
berarti belum diisi, berbeda dari angka nol. Harga memakai desimal teks hingga
18 digit utuh dan dua angka pecahan tanpa konversi floating point; masukan
negatif, notasi ilmiah, pecahan berlebih dan float JSON ditolak. Tidak ada
penetapan otomatis bahwa harga tertentu layak atau menjadi standar harga.

Formulir memakai ID hasil aspek yang tepat, bukan urutan tabel. Penyimpanan
memastikan seluruh ID hasil milik reviu ini; ID asing atau daftar tidak lengkap
ditolak. **Simpan draf** menyimpan penilaian yang masih Belum dinilai.
**Simpan & selesai** menyimpan isian yang sedang terlihat dan memvalidasi aspek
wajib dalam transaksi yang sama, sehingga tidak menyelesaikan data lama dengan
mengabaikan perubahan di formulir.

## Catatan masalah dan penyelesaian

Pemeriksa menambah beberapa catatan masalah melalui formulir terpisah, dengan
kategori, uraian masalah, dan rekomendasi catatan. Setiap POST menambahkan baris
`review_notes` baru yang terikat pada reviu dan versi item, dengan pelaku/waktu;
catatan lama tidak ditimpa atau dihapus. UI meminta pengguna menyimpan perubahan
hasil sebelum menambah catatan dan memaginasi catatan per sepuluh rekaman.

Catatan berstatus **Internal tim**. Ketua Tim dan Pengendali Teknis anggota
dapat membaca sesuai lingkupnya, tetapi tidak mengisi hasil sebagai Pemeriksa.
Operator SKPD, BKAD, Admin, dan Pemeriksa di luar cakupan tidak dapat membuka
hasil maupun catatan internal melalui tautan langsung. Pengiriman catatan
resmi, tanggapan, dan perbaikan substantif mengikuti K-15.

Reviu hanya dapat selesai jika seluruh aspek wajib pada snapshot sudah dinilai
dan semua pilihan Tidak berlaku mempunyai alasan. Hasil Tidak sesuai atau
Belum cukup bukti tidak dianggap belum dinilai; itu tetap hasil pemeriksaan.
Reviu menjadi Selesai diperiksa dan item menjadi Selesai direviu. Penugasan
tidak otomatis ditutup, supervisor tidak otomatis menyetujui, dan KKR tidak
difinalkan. Rekaman selesai hanya-baca dan tidak dapat dialihkan lewat K-13.

## Integritas dan perubahan skema

Penulisan mengunci penugasan, paket, item, cakupan dan reviu dengan urutan yang
selaras dengan pengalihan K-13, lalu memeriksa ulang kewenangan. Pemeriksa lama
tidak lagi dapat membaca/menulis setelah pengalihan cakupan. Pemeriksa baru
melanjutkan data dan catatan yang ada tanpa mengganti penulis awal reviu.

`item_reviews.lock_version` naik pada simpan/penyelesaian/penambahan catatan.
Penanda halaman juga mencakup keadaan penugasan, sehingga perubahan tab lain
atau pengalihan A → B → A menolak formulir lama. Mutasi memakai CSRF. Audit
mencatat ID/status/revisi, tanpa menyalin isi catatan internal ke log teknis.

Migrasi `000500` menambahkan `review_results.recommendation` (teks nullable)
dan `item_reviews.lock_version` (integer unsigned, awal 0). Tidak ada tabel atau
foreign key baru; model tetap 44 tabel dan 66 foreign key. ERD, kamus dan metadata
kolom diperbarui dari migrasi yang telah dijalankan pada MySQL; pengujian juga
memeriksa tipe dan nullable kedua kolom tersebut. Tidak ada data klien atau akun
nyata yang dibuat untuk demonstrasi.

## Batas bukti

UI mempertahankan Hearth, Plus Jakarta Sans dan rasio compact yang disetujui.
Pemeriksaan kode independen mencakup alur versi, harga, catatan, penyelesaian,
serta tombol dua tindakan. Pemeriksaan browser/keyboard dengan akun Pemeriksa
dan supervisor masih tertunda; render HTML dan pengujian otomatis bukan
kelulusan UAT. Hasil reviu ini belum merupakan persetujuan berjenjang atau KKR.

Verifikasi lokal 2 November 2026: 119 pengujian PHP/1.395 asersi lulus pada MySQL
khusus `shs_new_testing`, termasuk dua belas pengujian K-14. Build Vite berhasil.
Regresi frontend tambahan memeriksa dua tombol kirim, pemeliharaan intent,
penolakan pengiriman ganda, dan pemulihan tombol tanpa membuka aksi nonaktif
dari server; eksekusinya dilaporkan lewat CI.
