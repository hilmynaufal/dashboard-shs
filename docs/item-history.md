# Riwayat item dan dokumen — K-16

`GET /usulan/{proposal}/item/{item}/riwayat` menyajikan urutan versi terbaru
lebih dahulu, pelaku dan waktu simpan WIB, perbandingan dengan versi sebelumnya
yang boleh dilihat, dokumen pada kedua versi, dan peristiwa terkait versi terpilih.
Daftar versi dan peristiwa masing-masing dipaginasi 20 rekaman. Versi dapat
dipilih melalui tautan dari paket, editor, tampilan versi dan verifikasi BKAD.

## Pembatasan akses dan privasi

Izin `history.own`, `history.all`, atau `history.assigned` diperlukan bersama
akses baca item yang bersangkutan. Peran global tim tidak menggantikan
keanggotaan dan pemeriksa utama saat ini. Admin teknis tidak otomatis membaca
substansi. Item dan versi wajib cocok dengan paket pada URL.

SKPD pemilik dengan izin riwayat sendiri dapat melihat versi privat. BKAD dan
tim hanya melihat versi yang sudah dikirim; pembanding dipilih dari versi yang
juga boleh mereka lihat, sehingga draf antara dua pengiriman tidak bocor melalui
nilai sebelumnya, daftar, jumlah atau tautan. Catatan substansi tetap mengikuti
lingkup catatan K-15; BKAD tidak mendapat substansi hanya karena melihat item.

## Sumber riwayat

- Nilai sebelum/sesudah berasal dari versi item dan snapshot referensi, bukan
  master yang mungkin sudah berubah. Harga tetap desimal. Dokumen lama memakai
  relasi bukti per versi serta unduhan privat yang sama dengan K-11.
- Pelaku/waktu simpan berasal dari versi; pelaku pengiriman berasal dari rekaman
  pengiriman paket, pengajuan ulang kelengkapan, atau publikasi revisi bersama
  tanggapan. Pengunggah/penyimpan tidak diasumsikan sebagai pengirim.
- Verifikasi menampilkan pengembalian/siap beserta alasan; catatan resmi,
  tanggapan, keputusan selesai/perlu perbaikan hanya diproyeksikan bila terlihat
  oleh pengguna. Catatan internal dan JSON aktivitas umum tidak dipaparkan.
- K-16 menambah rekaman aktivitas `proposal.item_revision_submitted` saat K-15
  benar-benar memublikasikan revisi. Jawaban teks tidak membuat peristiwa ini.
- Rekaman lama tanpa pengirim menampilkan waktu pengiriman yang tersimpan
  dengan label pelaku tidak tercatat. Tidak membuat identitas atau alasan fiktif.

Riwayat terkait versi asal dapat mencakup tanggapan/keputusan yang menunjuk
versi revisi berikutnya; tautan catatan menjelaskan putaran lengkap. Peristiwa
diurutkan terbaru dahulu dan memakai kunci stabil bila waktunya sama.

## Hanya-baca dan bukti

Halaman riwayat tidak menyediakan mutasi, penghapusan, perubahan penilaian,
persetujuan atau finalisasi. GET tidak menulis aktivitas. Versi/file lama tidak
ditimpa saat membuat versi berikutnya; pelepasan bukti hanya berlaku pada salinan
versi baru. URL riwayat/versi menolak permintaan mutasi yang tidak tersedia.
Ini membuktikan perlakuan melalui aplikasi, bukan proteksi terhadap administrator
database yang mengubah data langsung.

`ProposalHistoryTest` memakai MySQL uji dan endpoint asli untuk penyimpanan,
unggah, kirim, kembali, kirim ulang dan tanggapan; juga mencakup izin, pengalihan,
privasi draf, dokumen lama, pembanding terlihat, pelaku pengiriman berbeda,
rekaman lama, paginasi, validasi ID dan penolakan mutasi. Browser berakun nyata,
desktop/mobile, keyboard dan UAT pemohon tetap belum diverifikasi.
