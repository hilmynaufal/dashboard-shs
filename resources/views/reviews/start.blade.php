@extends('layouts.workspace')
@section('title','Mulai reviu item')
@section('subtitle',$assignment->number.' · '.$assignment->title)
@section('actions')@include('reviews.nav')<a class="btn secondary" href="{{ route('assignments.show',$assignment) }}">Kembali ke penugasan</a>@endsection
@section('content')
<div class="review-layout"><section class="form-panel review-source">@include('reviews.source')</section><section class="form-panel"><h2>Aspek pemeriksaan</h2>
@if($aspects->contains(fn($aspect)=>str_starts_with($aspect->code,'PROTO_')))<p class="account-message">Aspek prototipe yang disetujui pengembang; belum menjadi SOP final instansi.</p>@endif
@forelse($aspects as $aspect)<div class="review-aspect"><strong>{{ $aspect->name }}</strong><small>{{ $aspect->is_required?'Wajib dinilai':'Opsional' }}</small></div>@empty<p class="account-message error">Daftar aspek belum disiapkan. Hubungi pengelola sebelum memulai reviu.</p>@endforelse
<p class="account-body-copy">Daftar aspek dan kewajibannya disimpan ketika reviu dimulai. Hasil awal selalu Belum dinilai.</p>
@can('create',[\App\Models\ItemReview::class,$assignment,$scope->id])<form method="POST" action="{{ route('reviews.start',[$assignment,$scope->id]) }}" data-submit-form>@csrf<input type="hidden" name="record_version" value="{{ \App\Support\FormInput::oldText('record_version',$recordVersion) }}">@if($errors->has('record_version'))<a class="btn secondary access-reload" href="{{ route('reviews.desk',[$assignment,$scope->id]) }}">Muat ulang penugasan</a>@endif<div class="form-actionbar"><button class="btn primary" type="submit" @disabled($aspects->isEmpty() || $aspects->count()>200)>Mulai reviu</button></div></form>@else<p class="account-body-copy">Reviu belum dimulai oleh Pemeriksa utama yang ditugaskan.</p>@endcan
</section></div>
@endsection
