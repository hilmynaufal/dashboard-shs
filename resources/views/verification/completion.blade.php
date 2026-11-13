@extends('layouts.workspace')
@section('title','Periksa kelengkapan item')
@section('subtitle',$proposal->number.' · Versi '.$version->version_number)
@section('actions')<a class="btn secondary" href="{{ route('proposals.show',$proposal) }}">Kembali ke paket</a>@can('update',$item)<a class="btn secondary" href="{{ route('proposals.items.edit',[$proposal,$item]) }}">Ubah data dan bukti</a>@endcan @endsection
@section('content')
<x-demo-evidence-notice/>
<div class="check-layout"><section class="form-panel">@include('verification.item-content')
@foreach($problems as $messages)@foreach($messages as $message)<p class="field-error">{{ $message }}</p>@endforeach @endforeach
</section><aside class="submit-panel"><h2>Kirim kelengkapan</h2>
<p class="account-body-copy">Hanya item ini yang dikirim kembali. Data dan bukti pada versi sebelumnya tetap tersimpan.</p>
@if($version->submitted_at!==null)<p class="account-message error">Simpan perbaikan data atau bukti sebagai versi baru terlebih dahulu.</p>@endif
<form method="POST" action="{{ route('proposals.items.resubmit',[$proposal,$item]) }}" data-submit-form>@csrf<input type="hidden" name="record_version" value="{{ old('record_version',$recordVersion) }}">
<label class="checkbox-label submit-agreement"><input type="checkbox" name="confirm" value="1" required @checked(old('confirm'))>{{ \App\Support\DemoMode::allowsMissingEvidence()?'Saya telah memeriksa data kelengkapan item ini.':'Saya telah memeriksa data dan bukti kelengkapan item ini.' }}</label>
<button class="btn primary full-button" type="submit" @disabled((bool)$problems || $version->submitted_at!==null)>Kirim kelengkapan</button></form>
@if($errors->has('record_version'))<a class="btn secondary access-reload" href="{{ route('proposals.items.completion',[$proposal,$item]) }}">Muat ulang kelengkapan</a>@endif
</aside></div>
@include('verification.history')
@endsection
