# Penugasan reviu — K-13

## Membuat tim dan lingkup

Ketua Tim dengan izin `assignments.manage` dan akses penugasan membuka
`/penugasan/buat`. Nomor unik, judul, periode, SKPD objek, serta minimal satu
Pemeriksa aktif wajib dipilih. Dasar dan ruang lingkup dapat dicatat. Pembuat
menjadi Ketua Tim; pemeriksa dipilih dari akun berperan Pemeriksa, sedangkan
Pengendali Teknis dapat dipilih saat pembuatan. Ketiga tingkat harus diisi
orang berbeda. Tidak ada pembuatan akun atau pemberian peran global otomatis.

Penugasan langsung berstatus Aktif. Tanggal pengusulan tidak membatasi proses
penugasan, sehingga item yang sudah diverifikasi dapat ditugaskan setelah
periode pengusulan ditutup. SKPD objek harus aktif. Pengendali Teknis yang belum
ditetapkan tidak memperoleh akses supervisi; pengaturan tim setelah pembuatan
belum disediakan oleh kartu ini.

Daftar `/penugasan` hanya memuat penugasan tempat pengguna menjadi anggota aktif
dengan peran global yang sesuai. Ketua Tim lain, Admin, BKAD, dan Operator tidak
otomatis membuka isi penugasan. Pemeriksa hanya melihat item yang saat ini
dibagikan kepadanya; Ketua Tim/Pengendali Teknis anggota melihat lingkup timnya.

## Memilih item dan pemeriksa

Ketua Tim anggota membuka `/penugasan/{id}/pilih-item`. Calon item dibatasi pada
SKPD objek dan periode penugasan, status Siap direviu, versi terakhir yang sudah
dikirim, serta keputusan BKAD siap untuk versi itu. Item yang sudah memiliki
penugasan aktif tidak dapat dimasukkan kembali. Hanya metadata untuk memilih
item ditampilkan sebelum cakupan dibuat; izin ini tidak membuka dokumen item
yang belum ditugaskan melalui URL usulan/bukti biasa.

Pilihan dikirim sebagai peta **ID item → ID anggota Pemeriksa**, bukan urutan
baris atau nama. Pilihan kosong diabaikan; maksimal 50 item dalam satu transaksi.
Daftar dipaginasi per 25 item dan UI menjelaskan agar pilihan disimpan sebelum
mengubah pencarian/halaman. Server memeriksa ulang lingkup, kesiapan, versi,
dan anggota tim aktif. Bila satu pilihan tidak sah, seluruh batch dibatalkan.

`assignment_items` menyimpan item, versi awal yang ditugaskan, dan satu anggota
Pemeriksa utama. Foreign key komposit menjaga versi milik item dan anggota
milik penugasan; batas unik mencegah cakupan item ganda pada penugasan yang sama.
Transaksi mengunci penugasan, paket dan item secara berurutan, sehingga dua
permintaan aplikasi tidak dapat menugaskan item aktif yang sama secara ganda.
Item menjadi Ditugaskan dan paket menjadi Dalam reviu; item saudara yang perlu
dilengkapi tetap dalam keadaan tersebut.

`/pekerjaan` menampilkan item Pemeriksa beserta nomor/judul penugasan, paket,
status dan versi yang ditugaskan. Tautan data/bukti tetap melewati policy usulan,
versi, dan bukti privat. Pembagian tidak langsung membuat hasil reviu; pencatatan
substantif berada pada K-14.

## Pengalihan dan riwayat

Ketua Tim anggota mengalihkan lewat `/penugasan/{id}/item/{scope}/alihkan`,
memilih Pemeriksa aktif lain dalam tim dan mengisi alasan wajib. Pengalihan
tidak mengganti item atau versi awal. Pemeriksa sebelumnya kehilangan akses
yang berasal dari cakupan ini; pemeriksa baru melihat item pada daftar kerja.
Peran/penugasan lain yang sah tetap mengikuti policy masing-masing.

`assignment_transfers` mencatat asal, tujuan, alasan, pelaku, dan waktu WIB pada
tampilan. Riwayat tidak ditimpa; penulis hasil reviu sebelumnya tidak diganti
oleh pengalihan. Hasil reviu yang sudah selesai, item selesai, serta penugasan
selesai tidak dapat dialihkan. Penutupan penugasan bukan tindakan K-13.

Formulir memakai penanda versi seluruh pembagian. Perubahan pada tab lain
ditolak dengan 409 dan tindakan muat ulang. Penanda mencakup riwayat pengalihan
agar perubahan A → B → A tetap membatalkan formulir lama walau terjadi pada detik
yang sama. Izin/keanggotaan diperiksa kembali di transaksi dan target diperiksa
aktif serta berperan sesuai saat penulisan. Mutasi memakai CSRF dan audit ID;
alasan pengalihan tidak disalin ke log teknis.

## Bukti dan batas

Pengujian endpoint MySQL mencakup alur nyata Operator → BKAD → Ketua Tim → daftar
Pemeriksa: item siap berhasil ditugaskan meskipun item saudara masih dikembalikan.
Ini melengkapi kriteria keempat K-12. Pengujian K-13 juga mencakup pembatasan
calon item, anggota luar tim, batch atomik, duplikasi, formulir lama, pengalihan,
pencabutan akses lama, perubahan urutan/pencarian, peran nonaktif, CSRF, dan
pemulihan HTML setelah masukan tidak sah. Tidak ada perubahan skema pada K-13.

UI mempertahankan Hearth, Plus Jakarta Sans, sidebar biasa/aktif 400/500, dan
jarak compact. Pemeriksaan browser/keyboard memakai akun Ketua Tim/Pemeriksa
belum dilakukan. Pemeriksaan kode dan pengujian otomatis bukan kelulusan UAT.
Pencatatan reviu, catatan resmi, supervisi dan keluaran KKR tetap pada kartu
berikutnya; penugasan ini belum menyatakan pekerjaan reviu selesai.

Verifikasi lokal 2 November 2026: seluruh 107 pengujian PHP/1.229 asersi lulus
pada MySQL khusus `shs_new_testing`, termasuk sepuluh pengujian K-13. Build Vite
berhasil. Pemeriksaan kode dan dokumentasi independen selesai pada lingkup
sumber; pemeriksaan visual/keyboard terautentikasi tetap tertunda.

Pembaruan K-14: tautan **Buka reviu** pada tiap baris kini membuka pencatatan
hasil per aspek dan catatan internal, sebagaimana `docs/item-reviews.md`.
Pengiriman catatan resmi, supervisi dan KKR belum termasuk pembaruan tersebut.
