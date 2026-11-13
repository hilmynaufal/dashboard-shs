# Brief modul Penilaian Zona Integritas

**Status dokumen** — Usulan lingkup untuk ditinjau pengembang di Panel PSE. Dua keputusan yang sudah disepakati adalah rumus Penyusunan Tim Kerja dan mekanisme perbaikan per butir. Alur pengajuan, izin finalisasi, dan rincian layar di bawah merupakan rancangan.

## Tujuan dan ruang lingkup

Unit kerja mengisi Lembar Kerja Evaluasi (LKE). Tim Penilai Internal (TPI) memeriksa bukti dan menilai setiap butir. Hasil akhirnya adalah rekap evaluasi internal yang dapat ditelusuri dari total ke rumus, jawaban TPI, dan bukti versi yang diperiksa. Penetapan predikat resmi berada di luar alur modul.

Satu berkas penilaian mengacu pada satu unit kerja, tahun, target WBK atau WBBM, dan versi instrumen. Acuan awal adalah dua contoh LKE yang dibahas, yaitu Disdukcapil dengan target WBK dan UPTD Rumah Sakit Jiwa dengan target WBBM. Gunakan kode tetap untuk butir yang redaksinya mirip. Simpan struktur, redaksi, kriteria, skala, bobot, dan aturan perhitungan pada versi instrumen agar penilaian lama dapat dibaca kembali.

## Perjalanan satu penilaian

| Tahap dan pelaku | Tindakan di aplikasi | Hasil yang terlihat |
|---|---|---|
| 1. Pengelola ZI membuka penilaian | Pilih unit, tahun, target, versi instrumen, dan anggota TPI. | Unit melihat LKE yang harus diisi. TPI melihat penugasan sesuai lingkupnya. |
| 2. Unit menyusun dan mengajukan LKE | Buka area, baca kriteria, isi jawaban dan penjelasan, sertakan bukti sesuai ketentuan butir. Simpan draf, periksa kelengkapan, lalu ajukan satu LKE. | Sistem menyimpan versi pengajuan. TPI dapat memeriksa jawaban beserta bukti yang menyertainya. |
| 3. TPI memeriksa satu butir | Bandingkan jawaban unit dengan kriteria dan bukti. Pilih **Selesaikan penilaian** jika bukti cukup untuk mengambil keputusan. Catat alasan bila jawaban TPI berbeda dari jawaban unit. | Jawaban unit dan keputusan TPI terlihat berdampingan. Butir yang selesai dapat memperoleh skor rendah. |
| 4. TPI meminta perbaikan bila diperlukan | Pilih **Minta perbaikan** pada butir terkait. Sebutkan hal yang perlu dijelaskan atau bukti yang kurang. | Butir masuk daftar **Perlu perbaikan** milik unit. TPI tetap dapat memeriksa butir lain. |
| 5. Unit mengirim ulang butir | Baca permintaan, perbarui jawaban, penjelasan, atau bukti, lalu pilih **Kirim ulang**. Unit dapat mempertahankan jawaban awal saat yang dibutuhkan hanya tambahan lampiran. | TPI menerima butir berstatus **Menunggu reviu ulang**. Versi awal, kiriman baru, dan permintaan perbaikan dapat dibandingkan. |
| 6. TPI menutup pemeriksaan | Periksa kiriman terbaru. Selesaikan penilaian atau tulis permintaan perbaikan berikutnya. | Riwayat setiap putaran memuat pelaku, waktu, permintaan, kiriman, dan keputusan. Skor terkait dihitung ulang. |
| 7. Petugas berizin memfinalkan rekap | Periksa hasil tiap area dan syarat target. Finalisasi tersedia setelah semua butir wajib selesai dinilai dan semua permintaan perbaikan ditutup. | Rekap final menyimpan versi instrumen, keputusan, skor, dan rujukan versi bukti. Skor di bawah ambang tetap dapat menjadi hasil evaluasi final. |

## Layar dan akses

| Layar | Informasi atau tindakan utama |
|---|---|
| Daftar penilaian ZI | Unit, tahun, target, status LKE, progres unit, progres TPI, dan jumlah permintaan perbaikan. Pengelola membuka penilaian dan menetapkan tim. |
| LKE unit kerja | Navigasi per area, kriteria dan pilihan jawaban, angka sumber untuk butir hitung, penjelasan, bukti dukung, pemeriksaan kelengkapan, serta daftar butir yang perlu diperbaiki. Bukti dukung punya dua mode: **Unggah berkas** (PDF/JPG/PNG, maks. 5 MB, storage privat) atau **Tautan Google Drive** (HTTPS ke drive.google.com/docs.google.com; disimpan di `zi_entries.evidence_url`). Satu butir memakai salah satu per versi; tanpa pilihan baru, bukti sebelumnya dipakai. Sistem tidak memeriksa izin akses tautan, jadi unit wajib memastikan TPI dapat membukanya. |
| Meja reviu TPI | Antrean awal dan kiriman ulang. Detail memperlihatkan jawaban unit, keputusan TPI, bukti versi terkait, dan perbedaan antarversi. |
| Rekap dan riwayat | Pemenuhan, Reform, Hasil, progres reviu, nilai sementara atau final, ambang target, dan selisih per syarat. Dari subtotal, pengguna dapat membuka butir penyusunnya. |

Gunakan akun dan identitas SKPD yang sudah tersedia. Tetapkan izin ZI tersendiri untuk mengelola penilaian, mengisi, mengirim, menilai, meminta perbaikan, memfinalkan, dan membaca hasil. Batasi unit pada penilaiannya sendiri dan TPI pada penugasan aktif. Hak baca lampiran mengikuti lingkup penilaian dan versi yang sudah dikirim. Simpan unggahan pada storage privat.

## Perhitungan dan keterlacakan

- **Struktur.** Pengungkit bernilai maksimum 60 poin, terdiri atas Pemenuhan 30 dan Reform 30. Komponen Hasil maksimum 40. Kedua contoh memuat 139 baris bertipe jawaban, termasuk angka sumber dan hasil turunan. Progres pemeriksaan perlu membedakan butir yang membutuhkan keputusan dari baris yang dihitung otomatis.
- **Sumber skor.** Simpan jawaban unit dan TPI secara terpisah. Gunakan jawaban TPI untuk skor reviu. Dukung Ya/Tidak, skala huruf sesuai rubrik, jumlah, persentase, dan indeks 0–4. Simpan angka sumber untuk persentase turunan. Tandai data belum lengkap atau penyebut nol untuk ditindaklanjuti sebelum finalisasi.
- **Keputusan khusus Penyusunan Tim Kerja.** Gunakan padanan rumus Disdukcapil `Jawaban!O5 = AVERAGE(O6:O8) × H5`. Tiga masukannya adalah skor pembentukan tim, skor pemilihan anggota, dan **subtotal berbobot Rencana Pembangunan Zona Integritas**. Subtotal Rencana Pembangunan juga masuk ke agregasi area sesuai struktur LKE. Perubahan subtotal tersebut memicu penghitungan ulang kedua bagian yang bergantung padanya. Tampilkan ketiga masukan dalam rincian skor. Aturan ini khusus untuk kelompok tersebut.
- **Ambang.** Simpan ambang sebagai bagian dari versi instrumen. Berikut ambang yang dibaca dari dua LKE contoh. Bandingkan nilai sebelum pembulatan, kemudian tampilkan nilai dan selisih terhadap ambang.

| Pemeriksaan minimum | WBK | WBBM |
|---|---:|---:|
| Setiap area Pengungkit terhadap bobotnya | 60% | 75% |
| Total Pengungkit | 40 | 48 |
| Birokrasi bersih dan akuntabel | 18,25 | 19,50 |
| Indeks Persepsi Anti Korupsi, setelah pembobotan | 15,75 | 15,75 |
| Capaian kinerja | 2,50 | 3,75 |
| Indeks Persepsi Kualitas Pelayanan Publik, setelah pembobotan | 14,00 | 15,75 |
| Nilai evaluasi total | 75 | 85 |

Rekap sementara menampilkan jumlah butir wajib yang selesai dibandingkan dengan jumlah yang harus diperiksa. Rekap final menampilkan hasil tiap syarat, termasuk syarat yang belum terpenuhi meskipun nilai total melampaui minimum.

## Kriteria penerimaan yang diusulkan

| Situasi uji dengan data sintetis | Hasil yang diharapkan |
|---|---|
| Dua skor butir tim masing-masing 1, subtotal Rencana Pembangunan 0,4 dan bobot tim 0,5. Subtotal kemudian berubah menjadi 0,7. | Skor tim berubah dari 0,40 menjadi 0,45. Rincian menampilkan perubahan subtotal sebagai penyebab. |
| Unit menjawab A. TPI menetapkan B dengan bukti yang cukup dan mencatat alasan. | Butir selesai dinilai dengan skor B. Jawaban A tetap tersedia dan tidak muncul permintaan perbaikan otomatis. |
| TPI meminta lampiran untuk satu butir. Unit mengirim bukti baru dengan jawaban yang sama. | Hanya butir terkait masuk antrean reviu ulang. TPI dapat melihat kedua versi bukti, sementara keputusan pada butir lain tetap tersimpan. |
| Nilai sementara telah melampaui minimum, tetapi satu butir wajib masih menunggu reviu ulang. | Rekap menyebutkan pekerjaan yang tersisa. Finalisasi tersedia setelah pemeriksaan selesai. |
| Capaian kinerja bernilai 3,75 pada dua penilaian dengan target berbeda. | Rekap WBK menampilkan selisih +1,25. Rekap WBBM menampilkan tepat di ambang. |
| Operator unit lain atau TPI di luar penugasan mencoba membuka berkas bukti melalui tautan langsung. | Akses ditolak. Pengguna berwenang tetap dapat membaca versi yang menjadi dasar penilaiannya. |

## Keputusan yang masih diperlukan

1. Tentukan siapa yang menetapkan instrumen dan siapa yang mendapat izin finalisasi. Usulan awal adalah pengelola menetapkan versi instrumen dan anggota TPI yang diberi izin menyelesaikan evaluasi.
2. Sepakati apakah bukti tautan eksternal boleh diterima dan bagaimana salinan yang diperiksa dipertahankan. Bukti unggahan dapat langsung diikat pada versi kiriman.
3. Tinjau kamus butir sebelum implementasi. Prioritaskan redaksi keterbukaan informasi publik yang berbeda pada baris 33 dan sel skor tetap pada baris 63 di contoh RSJ. Gunakan kedua workbook untuk membandingkan hasil, sambil mencatat selisih yang memang diakibatkan keputusan rumus.

Langkah pengembangan pertama yang diusulkan adalah satu alur lengkap untuk area Manajemen Perubahan, dari pengajuan sampai reviu ulang dan rincian skor. Lingkup modul dan kriteria penerimaan perlu disepakati melalui kartu Panel PSE sebelum implementasi.
