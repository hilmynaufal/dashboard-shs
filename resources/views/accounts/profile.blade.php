@extends('layouts.workspace')
@section('title', 'Profil saya')
@section('subtitle', 'Perbarui identitas dan kata sandi akun Anda.')
@section('content')
<div class="account-profile-grid">
    <section class="form-panel">
        <div class="section-heading"><div><h2>Identitas pengguna</h2><p>Nama dan jabatan tampil pada identitas akun Anda.</p></div></div>
        <form method="POST" action="{{ route('profile.update') }}" data-submit-form>@csrf @method('PATCH')
            <div class="form-field"><label for="name">Nama lengkap <span class="required">*</span></label><input id="name" name="name" value="{{ old('name',auth()->user()->name) }}" required maxlength="255" autocomplete="name"><x-field-error field="name"/></div>
            <div class="form-field"><label for="position">Jabatan <span class="optional">(opsional)</span></label><input id="position" name="position" value="{{ old('position',auth()->user()->position) }}" maxlength="255" autocomplete="organization-title"><x-field-error field="position"/></div>
            <div class="form-field"><label for="profile-email">Email akun</label><input id="profile-email" value="{{ auth()->user()->email }}" type="email" readonly><small>Email, peran, dan SKPD tidak diubah melalui formulir profil.</small></div>
            <div class="form-actionbar"><button class="btn primary" type="submit">Simpan profil</button></div>
        </form>
    </section>
    <section class="form-panel">
        <div class="section-heading"><div><h2>Ubah kata sandi</h2><p>Sesi lain akan diminta masuk kembali setelah perubahan.</p></div></div>
        <form method="POST" action="{{ route('profile.password') }}" data-submit-form>@csrf @method('PUT')
            <div class="form-field"><label for="current_password">Kata sandi saat ini</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"><x-field-error field="current_password"/></div>
            <div class="form-field"><label for="new-password">Kata sandi baru</label><div class="password-field"><input id="new-password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-hint"><button type="button" data-password-toggle="new-password" aria-pressed="false" aria-label="Tampilkan kata sandi baru">Lihat</button></div><small id="password-hint">Minimal 12 karakter, memuat huruf besar, huruf kecil, dan angka.</small><x-field-error field="password"/></div>
            <div class="form-field"><label for="password_confirmation">Konfirmasi kata sandi baru</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="255" autocomplete="new-password"></div>
            <div class="form-actionbar"><button class="btn primary" type="submit">Perbarui kata sandi</button></div>
        </form>
    </section>
</div>
@endsection
