<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Akses dibatasi · SHS/SBU</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="operator-app account-app account-error-page">
    <main class="account-error-content">
        <div class="page-heading"><div><h1>Akses dibatasi</h1><p>Akun Anda tidak memiliki izin untuk membuka halaman atau melakukan tindakan ini.</p></div></div>
        <p class="account-body-copy">Jika tugas Anda memerlukan akses tersebut, hubungi Admin pengelola aplikasi.</p>
        <a class="btn primary" href="{{ auth()->check() ? route('workspace') : route('login') }}">{{ auth()->check() ? 'Kembali ke beranda' : 'Masuk ke aplikasi' }}</a>
    </main>
</body>
</html>
