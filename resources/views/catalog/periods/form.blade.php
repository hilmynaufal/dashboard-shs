@extends('layouts.workspace')
@section('title', $period ? 'Ubah periode' : 'Buat periode')
@section('subtitle', 'Tentukan jadwal pengusulan untuk satu tahun anggaran.')
@section('actions')<a class="btn secondary" href="{{ route('periods.index') }}">Kembali ke daftar</a>@endsection
@section('content')
<section class="form-panel catalog-form-panel"><form method="POST" action="{{ $period ? route('periods.update',$period) : route('periods.store') }}" data-submit-form>
    @csrf @if($period) @method('PATCH')<input type="hidden" name="record_version" value="{{ old('record_version',$recordVersion) }}">@endif
    <div class="section-heading"><div><h2>Identitas dan jadwal</h2><p>Tanggal pengusulan menggunakan Waktu Indonesia Barat (WIB).</p></div></div>
    <div class="form-field"><label for="name">Nama periode <span class="required">*</span></label><input id="name" name="name" value="{{ old('name',$period?->name) }}" required maxlength="255"><x-field-error field="name"/></div>
    <div class="form-grid">
        <div class="form-field"><label for="budget_year">Tahun anggaran <span class="required">*</span></label><input id="budget_year" name="budget_year" type="number" min="1000" max="9999" step="1" value="{{ old('budget_year',$period?->budget_year ?? now()->timezone(\App\Models\SubmissionPeriod::DISPLAY_TIMEZONE)->year+1) }}" required><x-field-error field="budget_year"/></div>
        <div class="form-field"><label for="status">Status periode <span class="required">*</span></label><select id="status" name="status" required>@foreach(['draft'=>'Draf','open'=>'Dibuka','closed'=>'Ditutup'] as $key=>$label)<option value="{{ $key }}" @selected(old('status',$period?->status ?? 'draft')===$key)>{{ $label }}</option>@endforeach</select><x-field-error field="status"/></div>
        <div class="form-field"><label for="opens_on">Tanggal buka <span class="required">*</span></label><input id="opens_on" name="opens_on" type="date" min="1000-01-02" max="9999-12-31" value="{{ old('opens_on',$period?->opens_at?->setTimezone(\App\Models\SubmissionPeriod::DISPLAY_TIMEZONE)->format('Y-m-d')) }}" required aria-describedby="opens-help"><small id="opens-help">Mulai pukul 00.00 WIB.</small><x-field-error field="opens_on"/></div>
        <div class="form-field"><label for="closes_on">Tanggal tutup <span class="required">*</span></label><input id="closes_on" name="closes_on" type="date" min="1000-01-02" max="9999-12-31" value="{{ old('closes_on',$period?->closes_at?->setTimezone(\App\Models\SubmissionPeriod::DISPLAY_TIMEZONE)->format('Y-m-d')) }}" required aria-describedby="closes-help"><small id="closes-help">Sampai pukul 23.59.59 WIB.</small><x-field-error field="closes_on"/></div>
    </div>
    <p class="form-bottom-note"><x-ui-icon name="calendar"/>Status Dibuka tetap mengikuti rentang tanggal. Tahun anggaran tidak dapat diubah setelah periode digunakan.</p>
    @if($period && $errors->has('record_version'))<a class="btn secondary access-reload" href="{{ route('periods.edit',$period) }}">Muat ulang data</a>@endif
    <div class="form-actionbar"><a class="btn secondary" href="{{ route('periods.index') }}">Batal</a><button class="btn primary" type="submit">Simpan periode</button></div>
</form></section>
@endsection
