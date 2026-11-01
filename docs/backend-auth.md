# Backend autentikasi dan peran K-5

K-5 menyediakan endpoint JSON berbasis sesi, enam peran, grant tindakan, dan policy
resource. Sesuai arahan pengembang, tampilan login serta layar produk menunggu
referensi visual/K-3. Tidak ada registrasi publik atau akun/kata sandi bawaan.

## Endpoint yang tersedia

| Metode | Alamat | Hasil |
| --- | --- | --- |
| GET | /auth/csrf-token | Memulai sesi dan mengembalikan token CSRF untuk klien same-origin. |
| POST | /auth/login | Menerima email/password, menolak kredensial salah/akun nonaktif, merotasi sesi, dan mengembalikan profil terbatas serta izin. |
| GET | /auth/me | Hanya untuk sesi aktif; profil sendiri dan izin terbaru, tanpa hash kata sandi/remember token. |
| POST | /auth/logout | Mengakhiri autentikasi, membatalkan sesi lama, dan merotasi token CSRF. |

Klien menggunakan cookie sesi dan perlindungan CSRF/origin Laravel pada web
middleware. Kirim Accept: application/json. Endpoint profil/token memakai no-store.
Sesi aktif ditolak jika akun atau organisasi dinonaktifkan. Perubahan peran dibaca
dari database pada pemeriksaan izin berikutnya. Login dibatasi lima permintaan per
menit per identitas+IP dan tiga puluh per IP. Respons salah/nonaktif bersifat umum.

## Data peran

```sh
php artisan db:seed --class=AccessControlSeeder
```

Seeder menyinkronkan grant enam peran resmi sesuai RolePermissions dan dapat
dijalankan ulang; perubahan grant manual pada enam peran itu akan diganti dengan
definisi kode. Seeder tidak membuat akun atau mengaitkan peran ke pengguna.
Pembuatan/pengelolaan akun dilanjutkan pada kartu fitur akun, bukan lewat endpoint
registrasi publik. Jangan menambahkan akun/kata sandi contoh ke seeder.

## Batas peran

| Peran | Tindakan utama |
| --- | --- |
| Admin | Akun, akses, periode/master, dan log teknis; tidak memperoleh akses KKR otomatis. |
| BKAD | Periode/master, administrasi usulan yang diajukan, catatan administrasi, rekap lintas SKPD; tidak membaca catatan substantif internal/KKR. |
| Pemeriksa | Item yang ditugaskan, catatan internal, penilaian tanggapan, draft dan unduh KKR sendiri sesuai penugasan; tanpa hak supervisi. |
| Ketua Tim | Penugasan dan catatan resmi, penelaahan KKR serta persetujuan tingkat ketua di timnya. |
| Pengendali Teknis | Penelaahan KKR dan persetujuan teknis di timnya setelah persetujuan ketua pada versi yang sama. |
| Operator SKPD | Usulan, bukti, tanggapan, riwayat, dan rekap organisasi sendiri. |

## Menggunakan otorisasi pada fitur berikutnya

Gate bernama, misalnya `references.manage`, memeriksa grant tindakan saja. Untuk
objek nyata, controller wajib memanggil policy seperti:

```php
Gate::authorize('view', $itemVersion);
Gate::authorize('download', $evidenceFile);
Gate::authorize('update', $itemReview);
Gate::authorize('respond', $reviewNote);
Gate::authorize('approveLead', $kkrVersion);
Gate::authorize('approveTechnical', $kkrVersion);
```

Policy membatasi organisasi dan keanggotaan aktif pada penugasan; peran global
saja tidak cukup. Perubahan pemeriksa utama mencabut hak pemeriksa sebelumnya.
Catatan internal tidak terlihat operator. Bukti hanya terlihat pemilik organisasi
atau pemeriksa cakupan versi yang sudah diajukan; tautan berkas publik tidak dibuat.
Versi draft dan catatan salah relasi ditolak.

Persetujuan ditolak untuk penyusun KKR sendiri, anggota di luar tim, tingkat yang
salah, versi lama/final, atau pengendali teknis tanpa persetujuan ketua pada versi
yang sama. Policy ini memeriksa izin; penyimpanan keputusan dan transisi status
secara atomik tetap dibuat pada kartu supervisi.

Untuk daftar/ringkasan/ekspor gunakan subquery ResourceAccess::visibleItemIds.
Izin membaca header sebuah paket tidak mengizinkan pemeriksa membaca semua item
di dalam paket; izin versi dan bukti tetap diperiksa. Log teknis Admin harus memakai
proyeksi terbatas pada fitur audit, bukan menampilkan muatan catatan/KKR.

Endpoint bisnis untuk usulan, bukti, reviu, laporan, dan KKR belum dibuat pada K-5.
Fitur-fitur tersebut harus mengintegrasikan policy dan diuji melalui endpoint
sebenarnya saat kartunya dikerjakan. Jalur /__test pada pengujian hanya didaftarkan
dalam proses uji dan tidak tersedia pada aplikasi operasional.

## Pengujian

```sh
php artisan test --log-junit storage/app/private/K-5-junit.xml
```

Pengujian memakai shs_new_testing, data sintetis, dan kata sandi acak sementara.
CSRF diuji dengan bypass pengujian Laravel dinonaktifkan. Kasus negatif meliputi
akses lintas organisasi, penugasan, pencabutan peran, catatan internal, unduhan,
versi draft, dan persetujuan yang salah. Ini pengujian pengembang, bukan UAT.
