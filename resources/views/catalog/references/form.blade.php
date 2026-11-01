@extends('layouts.workspace')
@section('title', ($row ? 'Ubah ' : 'Tambah ').mb_strtolower($type['label']))
@section('subtitle', 'Kode tetap utuh, termasuk angka nol di depan dan tanda pemisah.')
@section('actions')<a class="btn secondary" href="{{ route('references.index',$kind) }}">Kembali ke daftar</a>@endsection
@section('content')
<section class="form-panel catalog-form-panel"><form method="POST" action="{{ $row ? route('references.update',[$kind,$row->id]) : route('references.store',$kind) }}" data-submit-form>
    @csrf @if($row) @method('PATCH')<input type="hidden" name="record_version" value="{{ old('record_version',$recordVersion) }}">@endif
    <div class="section-heading"><div><h2>{{ $type['label'] }}</h2><p>Referensi baru dapat dipilih ketika berstatus aktif.</p></div></div>
    <div class="form-field"><label for="code">Kode <span class="required">*</span></label><input id="code" type="text" name="code" value="{{ old('code',$row?->code) }}" required maxlength="60" spellcheck="false" aria-describedby="code-help"><small id="code-help">Masukkan kode sesuai daftar referensi instansi.</small><x-field-error field="code"/></div>
    <div class="form-field"><label for="name">Nama <span class="required">*</span></label><input id="name" name="name" value="{{ old('name',$row?->name) }}" required maxlength="255"><x-field-error field="name"/></div>
    <div class="form-field"><label for="is_active">Status referensi <span class="required">*</span></label><select id="is_active" name="is_active" required><option value="1" @selected((string)old('is_active',$row?->is_active ?? 1)==='1')>Aktif</option><option value="0" @selected((string)old('is_active',$row?->is_active ?? 1)==='0')>Nonaktif</option></select><x-field-error field="is_active"/></div>
    <p class="form-bottom-note"><x-ui-icon name="shield"/>Perubahan referensi tidak menimpa snapshot data pada versi usulan dan KKR yang tersimpan.</p>
    @if($row && $errors->has('record_version'))<a class="btn secondary access-reload" href="{{ route('references.edit',[$kind,$row->id]) }}">Muat ulang data</a>@endif
    <div class="form-actionbar"><a class="btn secondary" href="{{ route('references.index',$kind) }}">Batal</a><button class="btn primary" type="submit">Simpan referensi</button></div>
</form></section>
@endsection
