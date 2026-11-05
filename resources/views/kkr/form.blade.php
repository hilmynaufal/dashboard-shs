@extends('layouts.workspace')
@section('title',$document?'Siapkan versi baru':'Siapkan KKR')
@section('subtitle',$assignment->number.' · '.$assignment->title)
@section('actions')<a class="btn secondary" href="{{ $document?route('kkr.show',$document):route('assignments.show',$assignment) }}">Kembali</a>@endsection
@section('content')
@php
    $saved=$version?->snapshot??[];
    $selected=old('reviews',array_column($saved['source']['entries']??[],'review_id'));
    $selected=is_array($selected)?array_map('strval',array_filter($selected,'is_scalar')):[];
@endphp
<section class="form-panel"><h2>Isi versi yang akan disupervisi</h2><p class="account-body-copy">Salinan hasil, identitas tim, dan catatan disimpan bersama versi ini. Sumber yang berubah memerlukan versi baru sebelum disetujui.</p>
<form method="POST" action="{{ $document?route('kkr.revise',$document):route('kkr.store',$assignment) }}" data-submit-form>@csrf<input type="hidden" name="record_version" value="{{ \App\Support\FormInput::oldText('record_version',$recordVersion) }}">
@if(!$document)<div class="form-field"><label for="number">Nomor kerja KKR</label><input id="number" name="number" required maxlength="100" value="{{ \App\Support\FormInput::oldText('number') }}"><x-field-error field="number"/></div>@else<p class="account-body-copy">{{ $document->number }} · Versi sebelumnya {{ $version?->version_number }}</p>@endif
<div class="form-field"><label for="title">Judul KKR</label><input id="title" name="title" required maxlength="255" value="{{ \App\Support\FormInput::oldText('title',$saved['title']??'') }}"><x-field-error field="title"/></div>
<div class="form-field"><label for="template">Template Word</label><select id="template" name="template"><option value="{{ \App\Support\KkrPrint::TEMPLATE }}">{{ \App\Support\KkrPrint::LABEL }}</option></select><small>Mengikuti struktur contoh instansi. Nama dan identitas tim diambil dari penugasan saat versi disimpan.</small><x-field-error field="template"/></div>
<div class="form-field"><label for="purpose">Tujuan reviu</label><textarea id="purpose" name="purpose" rows="3" required maxlength="10000">{{ \App\Support\FormInput::oldText('purpose',$saved['purpose']??'') }}</textarea><x-field-error field="purpose"/></div>
<div class="form-field"><label for="work-steps">Langkah-langkah kerja</label><textarea id="work-steps" name="work_steps" rows="4" required maxlength="10000">{{ \App\Support\FormInput::oldText('work_steps',$saved['work_steps']??'') }}</textarea><x-field-error field="work_steps"/></div>
<h3 class="proposal-followup">Hasil reviu yang dimuat</h3><p class="account-body-copy">Pilih hasil selesai yang masih menjadi tugas Anda dan belum masuk KKR final. Maksimal 200 hasil pada satu KKR; daftar menampilkan hingga 200 hasil pertama yang siap.</p>
<div class="table-scroll"><table class="item-table"><thead><tr><th>Pilih</th><th>Item &amp; paket</th><th>Versi diperiksa</th></tr></thead><tbody>@forelse($candidates as $candidate)<tr><td><label class="checkbox-label"><input type="checkbox" name="reviews[]" value="{{ $candidate->id }}" @checked(in_array((string)$candidate->id,$selected,true)) aria-label="Muat hasil {{ $candidate->description }}">Muat</label></td><td><strong>{{ $candidate->description }}</strong><small class="cell-detail">{{ $candidate->number }}</small></td><td>{{ $candidate->version_number }}</td></tr>@empty<tr><td colspan="3">Belum ada hasil reviu selesai yang siap dimuat.</td></tr>@endforelse</tbody></table></div><x-field-error field="reviews"/>
<div class="form-field proposal-followup"><label for="conclusion">Kesimpulan</label><textarea id="conclusion" name="conclusion" rows="4" required maxlength="10000">{{ \App\Support\FormInput::oldText('conclusion',$saved['conclusion']??'') }}</textarea><x-field-error field="conclusion"/></div>
<div class="form-field"><label for="recommendation">Rekomendasi KKR</label><textarea id="recommendation" name="recommendation" rows="4" required maxlength="10000">{{ \App\Support\FormInput::oldText('recommendation',$saved['recommendation']??'') }}</textarea><x-field-error field="recommendation"/></div>
@if($document)<div class="form-field"><label for="change-reason">Alasan versi baru</label><textarea id="change-reason" name="change_reason" rows="3" required maxlength="10000">{{ \App\Support\FormInput::oldText('change_reason') }}</textarea><x-field-error field="change_reason"/></div><p class="form-bottom-note">Versi sebelumnya tetap tersimpan. Versi baru harus diajukan dan disetujui ulang pada kedua tingkat.</p>@endif
@if($errors->has('record_version'))<a class="btn secondary access-reload" href="{{ $document?route('kkr.edit',$document):route('kkr.create',$assignment) }}">Muat ulang sumber</a>@endif
<div class="form-actionbar"><button class="btn primary" type="submit" @disabled($candidates->isEmpty())>Simpan versi KKR</button></div></form></section>
@endsection
