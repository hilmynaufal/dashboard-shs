<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Masuk · SHS/SBU</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="operator-app account-app">
<main class="login-layout">
    <section class="login-intro" aria-label="Sistem Informasi SHS New">
        <div class="brand"><span class="brand-mark"><x-ui-icon name="building"/></span><span><strong>SHS <span class="brand-slash">/</span> SBU</strong><small>Sistem Informasi SHS New</small></span></div>
        <div class="login-purpose"><h2>Usulan tertata.<br>Reviu lebih terarah.</h2><p>Ruang kerja pengusulan standar harga dan reviu Inspektorat Daerah.</p><div class="login-process"><span>Pengusulan</span><x-ui-icon name="arrow"/><span>Reviu</span><x-ui-icon name="arrow"/><span>Rekomendasi</span></div></div>
        <p class="login-institution">Inspektorat Daerah</p>
    </section>
    <section class="login-form-region">
        <div class="login-form-wrap">
            <div class="page-heading"><div><h1>Masuk ke ruang kerja</h1><p>Gunakan akun yang diberikan pengelola aplikasi.</p></div></div>
            @if(session('status'))<div class="account-message success" role="status">{{ session('status') }}</div>@endif
            @if($errors->any() || isset($loginLimitMessage))<div class="account-message error" role="alert">{{ $loginLimitMessage ?? $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('session.store') }}" data-submit-form>
                @csrf
                <div class="form-field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required maxlength="255" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror><x-field-error field="email"/></div>
                <div class="form-field"><label for="password">Kata sandi</label><div class="password-field"><input id="password" type="password" name="password" autocomplete="current-password" required maxlength="4096"><button type="button" data-password-toggle="password" aria-pressed="false" aria-label="Tampilkan kata sandi">Lihat</button></div></div>
                <button type="submit" class="btn primary full-button">Masuk<x-ui-icon name="arrow"/></button>
            </form>
            <p class="login-help">Belum memiliki akun atau mengalami kendala masuk? Hubungi Admin pengelola aplikasi.</p>
        </div>
    </section>
</main>
</body>
</html>
