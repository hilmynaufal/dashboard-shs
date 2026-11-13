# Brief: Finalisasi tampilan dan kemudahan input

Status: dikonfirmasi pengembang 12 November 2026 ("lanjut") dan dikerjakan
lokal pada hari yang sama. PSE tidak terjangkau; butir untuk PSE dicatat di
`docs/catatan-pse-tertunda.md`. Hasil pengerjaan ada di bagian 12.

## 1. Ringkasan

Aplikasi sudah lengkap secara fungsi (K-2 sampai K-21) tetapi tampil sebagai
kerangka: satu ikon dipakai untuk sembilan menu, panel seragam, hampir tanpa
umpan balik gerak, dan isian referensi masih diketik manual. Pekerjaan ini
menaikkan mutu ke taraf aplikasi kerja instansi: fondasi tampilan bersama
diperbaiki sehingga sekitar 50 layar ikut naik, lalu alur prioritas dipoles
khusus, dan isian yang rawan salah ketik diganti pemilih yang dapat dicari.

## 2. Tindakan utama pengguna

Menyelesaikan langkah kerjanya (mengisi item, memutuskan kelengkapan, menilai
aspek, menyetujui KKR) tanpa mengetik ulang kode dan tanpa ragu apakah
tindakannya tersimpan.

## 3. Arah desain

- **Strategi warna:** Restrained. Netral hangat ditambah aksen terakota untuk
  tindakan utama, pilihan aktif, dan indikator saja. Warna semantik hanya pada
  status, selalu dengan teks.
- **Tema:** terang. Operator SKPD dan pemeriksa bekerja di laptop kantor pada
  jam kerja, ruang terang, sambil mencocokkan dengan Excel dan dokumen cetak.
  Sidebar arang dipertahankan sebagai jangkar navigasi.
- **Acuan:** Hearth (arah yang sudah dikonfirmasi 1 November 2026), kepadatan
  tabel ala Linear, ketelitian form ala dasbor Stripe. Screenshot Hearth akan
  dikirim ulang pengembang; detail kartu dan latar disesuaikan setelah diterima.
- **Token DESIGN.md tetap berlaku.** Font, palet, radius, dan kepadatan tidak
  diganti. Yang ditambah: skala elevasi, token gerak, set ikon, varian panel.
- Probe gambar dilewati: ini penyempurnaan sistem yang ada dan harness tidak
  memiliki pembuat gambar bawaan.

### Yang sengaja dihindari

Teks gradien, kaca buram dekoratif, garis aksen samping pada kartu, grid kartu
identik berikon, angka besar bergaya "hero metric", animasi masuk halaman yang
berurutan, pantulan/elastis, dan modal sebagai jawaban pertama.

## 4. Lingkup

- **Fidelitas:** siap produksi, di atas Blade dan CSS yang ada.
- **Luas:** sistem dulu untuk semua layar, lalu poles khusus: login, beranda,
  daftar dan detail usulan, form item, pemeriksaan kirim, reviu, form KKR.
- **Stack:** tanpa pustaka baru. Blade, CSS kustom, JavaScript modul kecil.
  Tidak ada perubahan skema, izin, atau aturan validasi server.
- **Prototipe `/prototype/operator`** tidak disentuh.

## 5. Bagian A: Fondasi tampilan

### A1. Ikon dan penanda produk

- Set ikon garis diperluas dari 12 menjadi sekitar 30, satu gaya (24px, garis
  1.6). Tiap menu mendapat ikon sendiri: pemantauan, rekap, usulan, catatan,
  KKR, arsip, aktivitas, referensi kini semuanya memakai ikon `catalog`.
- Ikon tindakan pada tombol dan baris: ubah, riwayat, unduh, unggah, impor,
  kirim, lampiran, peringatan, centang, tutup.
- Penanda produk sementara "SHS/SBU" sebagai SVG (bukan lambang instansi),
  dipakai di sidebar, login, dan favicon; satu berkas agar mudah diganti.

### A2. Kerangka aplikasi

- Sidebar dikelompokkan: Ruang kerja, Reviu, Arsip dan laporan, Administrasi.
  Sekarang 14 tautan dalam satu daftar datar.
- Blok organisasi menampilkan peran aktif; blok bawah menampilkan periode
  pengusulan yang berjalan dan sisa harinya.
- Topbar: breadcrumb bertingkat (paket, item), bukan hanya judul halaman.
- Query nama organisasi di layout dipindah ke view composer (kerapian, bukan
  perubahan perilaku).

### A3. Varian panel dan latar

Sekarang hanya `form-panel` dan `table-panel`. Ditambah secara terbatas:

- Panel ringkasan tanpa bingkai untuk fakta paket (tahun, periode, jumlah).
- Panel samping "lengket" untuk keputusan dan bukti saat konten bergulir.
- Blok keterangan bernada (info, peringatan, galat, sukses) dengan ikon dan
  latar tipis penuh, menggantikan `account-message` yang polos.
- Kanvas mendapat lapisan halus di area kepala halaman agar judul dan konten
  terpisah tanpa garis. Login mendapat bidang kiri bertekstur garis tipis,
  bukan gradien.
- Elevasi tiga tingkat yang diberi nama: datar, panel, melayang.

### A4. Tabel dan status

- Badge status memakai peta warna tunggal untuk seluruh aplikasi. Sekarang
  banyak daftar memakai `neutral` atau `blue` untuk semua status.
- Kolom tindakan yang berisi 4 sampai 5 tautan teks diringkas: satu tindakan
  utama terlihat, sisanya di menu baris.
- Kepala tabel lengket, baris bereaksi saat disorot, seluruh baris dapat diklik
  bila hanya ada satu tujuan.
- Kelengkapan paket (item siap dari total) tampil sebagai bilah kecil di daftar
  usulan, mengikuti rancangan K-3.
- Keadaan kosong mengajarkan langkah berikutnya dan memuat tombolnya.

### A5. Gerak

Semua 150 sampai 220 ms, ease-out, hanya `opacity` dan `transform`, dimatikan
oleh `prefers-reduced-motion`.

- Tombol: tekan, sibuk (pemutar kecil menggantikan teks "Menyimpan…" saja).
- Pesan hasil tindakan muncul sebagai toast yang masuk halus dan dapat ditutup;
  galat validasi tetap sebaris.
- Daftar pilihan, menu baris, dan dialog: muncul dari titik pemicu.
- Bilah kelengkapan dan penghitung beranda mengisi sekali saat dimuat.
- Kerangka abu (skeleton) untuk pratinjau impor dan hasil pencarian referensi.
- Tidak ada animasi masuk halaman.

### A6. Layar prioritas

- **Login:** panel kiri memuat penanda, alur kerja tiga langkah berikon, dan
  nama instansi; panel kanan form dengan ikon tampil/sembunyi kata sandi.
- **Beranda:** ringkasan kerja per peran. Antrean "perlu tindakan Anda",
  periode aktif, dan pintasan sesuai izin, memakai hitungan `ProgressBoard`
  yang sudah ada. Butir tambahan untuk PSE.
- **Detail usulan:** kepala paket dengan status, kelengkapan, dan satu tombol
  utama sesuai keadaan (tambah item, periksa dan kirim, lihat hasil).
- **Form item, reviu, KKR:** lihat Bagian B.

## 6. Bagian B: Kemudahan input

Temuan dari seluruh view, diurutkan menurut dampak.

| # | Lokasi | Sekarang | Usulan |
|---|---|---|---|
| B1 | Form item, koreksi baris impor: kode barang, satuan | Teks bebas dengan `datalist`; hanya kode yang terisi, nama tidak terlihat setelah dipilih | Combobox pencarian (kode atau nama), menampilkan kode dan nama, pilihan terkunci dengan tombol hapus |
| B2 | Form item, koreksi impor: rekening | Satu kolom teks, banyak kode dipisah titik koma | Pemilih banyak: tiap rekening jadi chip berisi kode dan nama; nilai kirim tetap string bertitik koma |
| B3 | Harga usulan, harga pembanding, rekomendasi harga | Teks polos tanpa pemisah ribuan | Awalan "Rp", pemisah ribuan saat mengetik, nilai kirim tetap desimal polos |
| B4 | SKPD pada buat akun, atur akses, buat penugasan, filter rekap | `select` panjang tanpa pencarian | Combobox pencarian yang sama dengan B1 |
| B5 | Pemeriksa dalam tim (buat penugasan) | Daftar kotak centang tanpa pencarian | Pencarian nama dan hitungan terpilih |
| B6 | Pilih item siap reviu | Satu `select` pemeriksa per baris | Pilih banyak baris lalu "Tugaskan ke…" sekaligus; pilihan per baris tetap ada |
| B7 | Hasil per aspek reviu | `select` untuk 3 sampai 4 pilihan | Tombol segmen; kolom alasan menandai wajib otomatis saat "Tidak berlaku" |
| B8 | Keputusan kelengkapan, keputusan tindak lanjut | `select` dua pilihan | Dua pilihan radio berbentuk kartu; alasan wajib mengikuti pilihan |
| B9 | Kategori catatan (reviu, administrasi) | Teks bebas | Combobox dengan saran dari kategori yang pernah dipakai, tetap boleh mengetik baru |
| B10 | Unggah bukti, unggah Excel | Input berkas bawaan, satu berkas per kirim | Area seret-lepas dengan nama, ukuran, dan pemeriksaan tipe sebelum kirim; bukti boleh beberapa berkas |
| B11 | Pemetaan kolom impor | Enam `select` "Kolom n" | Tebakan otomatis dari judul kolom ditandai, contoh nilai baris pertama di samping tiap pilihan |
| B12 | Form KKR: hasil reviu yang dimuat | Kotak centang per baris, hingga 200 | Pilih semua, cari, dan hitungan terpilih |
| B13 | Daftar dengan filter (usulan, rekap, penugasan) | Harus menekan "Terapkan" | Filter `select` langsung menerapkan; pencarian tetap dengan Enter; chip filter aktif dapat dihapus |
| B14 | Konfirmasi (lepas bukti, batal impor) | `window.confirm` peramban | Dialog aplikasi dengan penjelasan akibat |
| B15 | Form panjang (item, reviu, KKR) | Tombol simpan di ujung bawah, tanpa peringatan keluar | Bilah simpan lengket dan peringatan bila ada perubahan belum disimpan |
| B16 | Area teks panjang | Tinggi tetap, tanpa hitungan | Tumbuh mengikuti isi, penghitung muncul mendekati batas |
| B17 | Tahun anggaran periode | Input angka 1000 sampai 9999 | Bawaan tahun depan, pilihan sekitar tahun berjalan |

Aturan bersama untuk semua kontrol baru:

- Peningkatan progresif: tanpa JavaScript, isian kembali ke input/select biasa
  dan form tetap dapat dikirim. Nama field dan format nilai tidak berubah,
  sehingga validasi server dan tes yang ada tetap berlaku.
- Satu komponen combobox dipakai ulang (B1, B2, B4, B5, B9, B12) dengan pola
  ARIA combobox, navigasi panah, Enter, Escape, dan target 40px.
- B1 dan B2 memakai endpoint `/usulan/referensi/{kind}` yang ada (25 hasil,
  cari kode atau nama). Menampilkan nama pilihan tersimpan saat form dibuka
  ulang memerlukan pencarian nama menurut kode; ini satu-satunya tambahan sisi
  server untuk Bagian B.

## 7. Keadaan utama

- **Combobox:** kosong, mengetik, memuat, ada hasil, tanpa hasil ("Tidak ada
  referensi aktif yang cocok. Hubungi BKAD bila kode belum terdaftar."),
  terpilih, galat jaringan (ketik kode manual tetap diterima), nonaktif.
- **Unggah:** diam, berkas diseret, terpilih, tipe/ukuran ditolak, mengirim.
- **Form:** bersih, berubah, mengirim, galat validasi, konflik versi (tombol
  "Muat ulang data" yang ada dipertahankan).
- **Daftar:** memuat, berisi, kosong pertama kali, kosong karena filter.
- **Beranda:** ada pekerjaan, tidak ada pekerjaan, peran belum ditetapkan.

## 8. Isi dan teks

Bahasa Indonesia dengan istilah yang sudah dipakai (usulan, item, bukti,
kelengkapan, reviu, KKR). Label tetap terlihat, bantuan di dekat isian, pesan
galat menyebut letak dan perbaikannya. Catatan batas demo tetap ditampilkan.

## 9. Urutan pengerjaan yang disarankan

1. Token, ikon, penanda, kerangka sidebar/topbar (A1, A2, sebagian A3).
2. Komponen bersama: badge, blok keterangan, toast, dialog, tombol sibuk (A4, A5, B14).
3. Combobox dan pemilih banyak, dipasang pada form item (B1, B2, B3).
4. Form reviu, verifikasi, KKR, penugasan (B4 sampai B9, B12, B15, B16).
5. Unggah dan impor (B10, B11).
6. Login, beranda, detail usulan, daftar (A6, B13).
7. Pemeriksaan: `php artisan test`, `npm run build`, `npm run test:ui`,
   keyboard, lebar 375/768/1280, reduced-motion.

Tiap langkah dapat menjadi commit tersendiri.

## 10. Acuan impeccable saat implementasi

`interaction-design.md` (combobox, form), `motion-design.md`,
`spatial-design.md`, serta perintah `polish` dan `audit` di akhir.

## 11. Pertanyaan terbuka

- Screenshot Hearth: menunggu kiriman ulang untuk detail kartu dan latar.
- Kode kartu PSE untuk branch/commit pekerjaan ini belum ada.
- Beranda per peran dan pencarian nama menurut kode menambah lingkup; menunggu
  keputusan sebagai butir tambahan.
- B10 beberapa berkas sekaligus: endpoint sekarang menerima satu berkas;
  dikirim berurutan dari sisi klien atau endpoint diperluas?
- Perubahan kerja yang belum di-commit (mode demo bukti) ada di pohon kerja;
  dikerjakan di atasnya atau di-commit lebih dulu?

## 12. Hasil pengerjaan (12 November 2026)

Komponen Hearth yang diambil dari screenshot: sidebar berkelompok, ubin ikon
statistik, pil status bertitik, kontrol segmen, alur tahap, baris dokumen
berikon, tombol "..." per baris, dan fokus isian oranye. Tidak diambil: papan
kanban, grafik, heatmap, mode gelap, dan pencarian global.

Selesai dan diperiksa di browser lokal (1366px dan 375px):
A1, A2, A3, A5; A4 kecuali bilah kelengkapan paket; login, beranda per peran,
daftar dan detail usulan; B1, B2, B3, B4, B5, B6 (versi "terapkan ke baris
kosong"), B7, B8, B10 (satu berkas), B12, B13, B14, B15, B16, B17.

Belum dikerjakan:
- B9 saran kategori catatan dan B11 tebakan pemetaan kolom impor.
- B10 beberapa berkas sekaligus (endpoint masih satu berkas).
- Bilah kelengkapan per paket di daftar usulan (perlu hitungan baru di query).
- Kepala tabel lengket hanya di dalam wadah gulir tabel.
- Poles khusus layar KKR, arsip, aktivitas, dan akun: baru ikut lapisan bersama.

Belum diperiksa: form reviu dan KKR dengan akun pemeriksa (akun uji tidak
menjadi anggota tim), pembaca layar, dan UAT pemohon.
