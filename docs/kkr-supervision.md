# Supervisi dan persetujuan berjenjang — K-17

Pemeriksa menyiapkan salinan versi KKR dari hasil reviu selesai melalui penugasan.
`/kkr` menyediakan daftar milik penyusun, antrean Ketua Tim, antrean Pengendali
Teknis, dan semua dokumen yang boleh diakses. Persiapan ini diperlukan untuk
supervisi K-17; tata letak Word/template instansi dan unduhan tetap lingkup K-18.

## Transisi dan versi

1. Pada penyusunan awal, penyusun memilih maksimal 200 hasil selesai yang masih
   menjadi tugasnya dan belum dimuat dalam KKR final, serta mengisi nomor kerja, judul, kesimpulan dan
   rekomendasi. Daftar persiapan menampilkan maksimal 200 hasil siap pertama.
2. Menyimpan selalu menambah `kkr_versions` dan `kkr_entries`, tidak memperbarui
   isi versi sebelumnya. Versi menyimpan lingkup/tim, hasil/aspek/harga, versi item
   yang diperiksa, usulan terkirim terbaru, bukti, serta catatan dan tanggapan.
3. Pengajuan draf terbaru menetapkan waktu pengajuan dan antrean Ketua Tim.
   Pengendali Teknis belum mendapat antrean keputusan sebelum persetujuan Ketua
   Tim pada versi yang sama. Akses baca tim tetap ditentukan policy, bukan antrean.
4. Masing-masing supervisor menyetujui atau mengembalikan dengan alasan wajib.
   Keputusan hanya satu kali per tingkat per versi, dengan pelaku dan waktu.
   Penyusun tidak boleh menyetujui sendiri; orang yang sama tidak boleh mengisi
   kedua tingkat persetujuan, termasuk konfigurasi anggota/peran rangkap lama.
5. Pengembalian membawa dokumen ke `returned`. Pengajuan ulang langsung ditolak:
   penyusun membuat versi baru dengan alasan perubahan, lalu mengajukan draf itu.
   Keputusan versi sebelumnya tetap tersimpan dan tidak dipindah ke versi baru.
6. Persetujuan teknis setelah persetujuan Ketua Tim mengunci versi final dalam
   transaksi yang sama. Tidak ada tombol/jalur finalisasi yang melewati urutan.
   Final hanya-baca dan tidak menutup seluruh penugasan yang mungkin punya KKR lain.

## Isi yang disetujui

Snapshot `supervision-v1` dibuat server dari data yang diizinkan; isian snapshot,
status, penyusun dan finalisasi dari pengguna ditolak. Identitas tim hanya nama,
peran dan identitas internal yang diperlukan; tidak memuat email atau rahasia.
Harga usulan, pembanding dan rekomendasi terpisah. Penilaian tetap jelas merujuk
versi yang diperiksa; versi usulan setelah tanggapan tidak diklaim sebagai hasil
penilaian aspek baru.

Catatan administratif item dan catatan substansi dalam cakupan penugasan yang
sama ikut direkam; draf internal tim tidak otomatis dikirim kepada SKPD. Semua
catatan yang belum `resolved` dihitung terbuka, termasuk catatan internal.
Pengajuan dan setiap persetujuan wajib mengakui keberadaan catatan terbuka yang
ditampilkan lengkap dalam versi. Pengembalian hanya memerlukan alasan; tidak
memaksa pengakuan persetujuan. Finalisasi tidak otomatis menyelesaikan catatan.

Hash kanonik menangani perbedaan urutan kunci JSON MySQL tanpa memakai uang
floating point. Pemeriksaan integritas menyandingkan snapshot dengan seluruh
entri dan penanda catatan terbuka. Pengajuan/persetujuan menghitung ulang sumber:
perubahan hasil, catatan/tanggapan, item, anggota atau identitas yang dimuat
memerlukan versi baru. Versi usang masih dapat dikembalikan dengan alasan.
Identitas supervisor pada keputusan ditampilkan dari tim dalam versi tersimpan;
perubahan nama setelah final tidak mengubah isi atau nama yang direkam.

## Otorisasi dan transaksi

Pembacaan daftar dan detail memakai query pembatasan yang sama. Penyusun harus
anggota Pemeriksa aktif, dengan izin menyusun/melihat dan lingkup item saat ini.
Ketua Tim/Pengendali Teknis harus berperan global dan menjadi anggota penugasan
yang sesuai; Admin teknis, BKAD, SKPD, dan tim lain tidak mendapat isi KKR secara
otomatis. Pemeriksa kehilangan pembacaan dokumen bila entri historisnya sudah
berada pada pemeriksa lain. Penugasan tertutup mematikan seluruh tindakan.

Mutasi mengunci penugasan, paket dan item terurut, lalu dokumen, serta membaca
ulang izin. Token dokumen mencakup versi/keputusan/penugasan. Persiapan memakai
token hasil siap. Keunikan versi dan keputusan tetap dijaga database. Narasi atau
sumber tidak diubah saat persetujuan; hanya waktu pengajuan/finalisasi serta
status dokumen yang bergerak. Snapshot dibatasi 4 MiB, dengan pesan pembagian
KKR bila terlalu besar. Tidak ada tabel, dependensi, izin global atau skema baru.

## Koreksi setelah final — K-21

Versi final tetap hanya-baca. Penyusun awal dapat menyiapkan koreksi narasi
sebagai versi baru pada dokumen yang sama bila penugasan aktif, masih menjadi
pemeriksa utama untuk seluruh item, serta memiliki izin menyusun, melihat,
membaca usulan penugasan dan arsip. Alasan wajib, token dokumen dan pengecekan
ulang izin melindungi permintaan usang. Integritas sumber dan arsip Word final
diperiksa sebelum versi koreksi pertama ditambahkan.

Koreksi hanya mengisi judul, tujuan, langkah kerja, kesimpulan dan rekomendasi
KKR. Daftar ID reviu harus tepat sama dengan versi final terakhir; hasil reviu
selesai tidak dibuka ulang dan cakupan tidak dapat ditambah/dikurangi. Pemeriksaan
sumber tetap berlaku. Versi baru menyimpan snapshot sumber saat disiapkan,
`change_reason` dan `supersedes_version_id` ke versi sebelumnya; snapshot final
lama, keputusan dan berkas tetap tersimpan. Pengembalian koreksi memerlukan
versi baru berikutnya sebelum pengajuan ulang. Kedua tingkat wajib memutuskan
ulang pada versi yang sama; keputusan lama tidak diwariskan.

Final dengan nomor versi tertinggi berlaku sampai koreksi mendapatkan kedua
persetujuan dan menjadi final baru. Seluruh versi final tetap dapat dibuka
dalam lingkup akses, meskipun status dokumen saat ini draf atau dikembalikan.
Lihat `archive-activity.md`; K-21 tidak menambahkan migrasi atau izin baru.

## Bukti dan batas

`KkrSupervisionTest` memeriksa antrean bertingkat, alasan pengembalian, versi
ulang, penolakan persetujuan lama, catatan terbuka, perubahan sumber, lingkup
peran/SKPD/anggota, pemisahan penyusun/supervisor/tingkat, formulir usang, data
palsu, integritas entri, final immutable, status tertutup, validasi HTML dan CSRF.
UI mengikuti Hearth/Jakarta compact. Pemeriksaan visual berakun nyata, keyboard
desktop/mobile dan UAT pemohon belum dijalankan. Dokumen teknis/layar tetap draf
agen di PSE; aplikasi tidak menyetujui atau meluluskan UAT pemohon.
