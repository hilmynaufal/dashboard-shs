@extends('layouts.workspace')
@section('title', 'Buat akun')
@section('subtitle', 'Tetapkan identitas, peran awal, dan SKPD pengguna.')
@section('actions')<a class="btn secondary" href="{{ route('accounts.index') }}">Kembali ke daftar</a>@endsection
@section('content')
<section class="form-panel account-create-panel"><form method="POST" action="{{ route('accounts.store') }}" data-submit-form>@csrf
    <div class="section-heading"><div><h2>Identitas dan akses awal</h2><p>Akun baru akan berstatus aktif.</p></div></div>
    <div class="form-grid">
        <div class="form-field"><label for="name">Nama lengkap <span class="required">*</span></label><input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="off"><x-field-error field="name"/></div>
        <div class="form-field"><label for="email">Email <span class="required">*</span></label><input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off"><x-field-error field="email"/></div>
        <div class="form-field"><label for="role">Peran awal <span class="required">*</span></label><select id="role" name="role" required><option value="">Pilih peran</option>@foreach($roles as $code => $name)<option value="{{ $code }}" @selected(old('role')===$code)>{{ $name }}</option>@endforeach</select><x-field-error field="role"/></div>
        <div class="form-field"><label for="organization_id">SKPD <span class="optional">(wajib untuk Operator SKPD)</span></label><select id="organization_id" name="organization_id" data-combobox><option value="">Tanpa SKPD</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)old('organization_id')===(string)$organization->id)>{{ $organization->name }}</option>@endforeach</select><x-field-error field="organization_id"/></div>
        <div class="form-field full-width"><label for="position">Jabatan <span class="optional">(opsional)</span></label><input id="position" name="position" value="{{ old('position') }}" maxlength="255" autocomplete="off"><x-field-error field="position"/></div>
    </div>
    <div class="section-heading"><div><h2>Kata sandi awal</h2><p>Pengguna dapat menggantinya melalui Profil saya.</p></div></div>
    <div class="form-grid">
        <div class="form-field"><label for="password">Kata sandi <span class="required">*</span></label><div class="password-field"><input id="password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-hint"><button type="button" data-password-toggle="password" aria-pressed="false" aria-label="Tampilkan kata sandi">Lihat</button></div><small id="password-hint">Minimal 12 karakter, huruf besar, huruf kecil, dan angka.</small><x-field-error field="password"/></div>
        <div class="form-field"><label for="password_confirmation">Konfirmasi kata sandi <span class="required">*</span></label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="255" autocomplete="new-password"></div>
    </div>
    <div class="form-actionbar"><a class="btn secondary" href="{{ route('accounts.index') }}">Batal</a><button class="btn primary" type="submit">Buat akun</button></div>
</form></section>
@endsection
