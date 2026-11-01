@extends('layouts.workspace')
@section('title','Buat usulan')
@section('subtitle','Mulai dari identitas paket, lalu lengkapi item dan bukti.')
@section('content')
<section class="form-panel catalog-form-panel"><form method="POST" action="{{ route('proposals.store') }}" data-submit-form>@csrf
<div class="form-field"><label for="title">Nama paket <span class="required">*</span></label><input id="title" name="title" maxlength="255" value="{{ old('title') }}" required><x-field-error field="title"/></div>
<div class="form-grid"><div class="form-field"><label for="type">Jenis usulan</label><select id="type" name="type">@foreach(['SHS','SBU'] as $type)<option @selected(old('type')===$type)>{{ $type }}</option>@endforeach</select></div><div class="form-field"><label for="submission_period_id">Periode pengusulan <span class="required">*</span></label><select id="submission_period_id" name="submission_period_id" required><option value="">Pilih periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected((string)old('submission_period_id')===(string)$period->id)>{{ $period->budget_year }} · {{ $period->name }} · {{ $period->availability() }}</option>@endforeach</select><x-field-error field="submission_period_id"/></div></div>
@if($periods->isEmpty())<p class="account-message">Belum ada periode berstatus Dibuka. Hubungi Admin/BKAD.</p>@endif
<p class="form-bottom-note">Draf dapat disiapkan sebelum tanggal buka. Pengiriman hanya tersedia pada rentang pengusulan yang terbuka.</p><div class="form-actionbar"><a class="btn secondary" href="{{ route('proposals.index') }}">Batal</a><button class="btn primary" type="submit" @disabled($periods->isEmpty())>Simpan draf</button></div>
</form></section>
@endsection
