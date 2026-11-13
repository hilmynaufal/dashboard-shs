@extends('layouts.workspace')
@section('title', 'Atur akses pengguna')
@section('subtitle', 'Tetapkan peran dan lingkup SKPD akun pengguna.')
@section('actions')<a class="btn secondary" href="{{ route('accounts.index') }}">Kembali ke daftar</a>@endsection
@section('content')
<section class="form-panel account-create-panel">
    <div class="section-heading"><span class="section-icon"><x-ui-icon name="user"/></span><div><h2>{{ $account->name }}</h2><p class="account-identity-email">{{ $account->email }}</p></div></div>
    @if($account->id === auth()->id())<div class="account-message">Anda sedang mengatur akun sendiri. Peran Admin harus tetap dipertahankan.</div>@endif
    <form method="POST" action="{{ route('accounts.access.update', $account) }}" data-submit-form>
        @csrf @method('PATCH')
        <input type="hidden" name="access_version" value="{{ old('access_version', $accessVersion) }}">
        @php($roleError = $errors->first('roles') ?: $errors->first('roles.*'))
        <fieldset class="access-role-fieldset" aria-describedby="roles-help{{ $roleError ? ' roles-error' : '' }}" @if($roleError) aria-invalid="true" @endif>
            <legend>Peran pengguna <span class="required">*</span></legend>
            <p class="access-help" id="roles-help">Pilih peran sesuai tugas pengguna. Peran internal dapat digabung; Operator SKPD digunakan sebagai peran tersendiri agar lingkup organisasi tetap terpisah.</p>
            @php($selectedRoles = session()->hasOldInput('access_version') ? old('roles', []) : $access['roles'])
            @php($selectedRoles = is_array($selectedRoles) ? $selectedRoles : [])
            <div class="access-role-options">
                @foreach($roles as $code => $name)
                <label class="access-role-option" for="role-{{ $code }}"><input id="role-{{ $code }}" type="checkbox" name="roles[]" value="{{ $code }}" aria-describedby="roles-help{{ $roleError ? ' roles-error' : '' }}" @if($roleError) aria-invalid="true" @endif @checked(in_array($code,$selectedRoles,true))><span>{{ $name }}</span></label>
                @endforeach
            </div>
            @if($roleError)<p class="field-error" id="roles-error">{{ $roleError }}</p>@endif
        </fieldset>
        <div class="form-field">
            <label for="organization_id">SKPD <span class="optional">(wajib untuk Operator SKPD)</span></label>
            <select id="organization_id" name="organization_id" aria-describedby="organization-help" data-combobox><option value="">Tanpa SKPD</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)old('organization_id',$access['organization_id']) === (string)$organization->id)>{{ $organization->name }}{{ $organization->is_active ? '' : ' (nonaktif — pilih SKPD aktif)' }}</option>@endforeach</select>
            <small id="organization-help">Operator hanya mengakses usulan organisasi yang ditetapkan. Mengganti SKPD tidak memindahkan data usulan lama.</small>
            <x-field-error field="organization_id"/>
        </div>
        <p class="form-bottom-note"><x-ui-icon name="shield"/>Perubahan berlaku saat pengguna mengakses aplikasi kembali dan tercatat dalam riwayat aktivitas.</p>
        @error('access_version')<a class="btn secondary access-reload" href="{{ route('accounts.access.edit',$account) }}">Muat ulang data</a>@enderror
        <div class="form-actionbar"><a class="btn secondary" href="{{ route('accounts.index') }}">Batal</a><button class="btn primary" type="submit">Simpan hak akses</button></div>
    </form>
</section>
@endsection
