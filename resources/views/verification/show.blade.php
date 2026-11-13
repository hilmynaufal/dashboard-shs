@extends('layouts.workspace')
@section('title','Pemeriksaan item')
@section('subtitle',$proposal->number.' · '.$organization.' · Versi terkirim '.$version->version_number)
@section('actions')@can('viewHistory',$item)<a class="btn secondary" href="{{ route('proposals.items.history',[$proposal,$item]) }}">Riwayat item</a>@endcan <a class="btn secondary" href="{{ route('verification.index') }}">Kembali ke antrean</a><a class="btn secondary" href="{{ route('proposals.show',$proposal) }}">Lihat paket</a>@endsection
@section('content')
<x-demo-evidence-notice/>
<div class="check-layout">
    <section class="form-panel">@include('verification.item-content')</section>
    <aside class="submit-panel"><h2>Hasil kelengkapan</h2><p class="verification-current-status"><span class="badge {{ \App\Support\StatusTone::of($item->status) }}">{{ \App\Models\ProposalItem::LABELS[$item->status] }}</span></p>
    @can('verify',$item)
    <form method="POST" action="{{ route('verification.decide',$item) }}" data-submit-form>@csrf
        <input type="hidden" name="record_version" value="{{ old('record_version',$recordVersion) }}">
        <div class="form-field"><label for="result">Keputusan</label><select id="result" name="result" required data-choices="cards" data-requires-reason="needs_completion" data-reason-field="reason"><option value="">Pilih hasil pemeriksaan</option><option value="ready" @selected(old('result')==='ready')>Siap direviu</option><option value="needs_completion" @selected(old('result')==='needs_completion')>Perlu dilengkapi</option></select><x-field-error field="result"/></div>
        <div class="form-field"><label for="reason">Alasan atau catatan</label><textarea id="reason" name="reason" rows="5" maxlength="5000" aria-describedby="reason-help">{{ old('reason') }}</textarea><small id="reason-help">Wajib untuk pengembalian. Alasan ini dapat dibaca Operator SKPD.</small><x-field-error field="reason"/></div>
        <p class="form-bottom-note">Keputusan berlaku untuk versi terkirim yang ditampilkan dan dicatat dalam riwayat.</p>
        @if($errors->has('record_version'))<a class="btn secondary access-reload" href="{{ route('verification.show',$item) }}">Muat ulang pemeriksaan</a>@endif
        <div class="form-actionbar"><button class="btn primary" type="submit">Simpan hasil pemeriksaan</button></div>
    </form>
    @else<p class="account-body-copy">{{ $item->status==='needs_completion'?'Menunggu SKPD mengirim kelengkapan. Versi perbaikan yang belum dikirim tidak ditampilkan di sini.':($item->status==='ready'?'Item ini sudah siap untuk penugasan reviu, terlepas dari status item lain dalam paket.':'Item tidak berada dalam antrean verifikasi.') }}</p>@endcan
    </aside>
</div>
@include('verification.history')
<section class="form-panel proposal-followup"><h2>Catatan administrasi</h2><a class="btn secondary" href="{{ route('notes.index',['item'=>$item->id]) }}">Lihat catatan item</a>
@can('administrative',[\App\Models\ReviewNote::class,$item])
<p class="account-body-copy">Catatan ini dikirim resmi ke SKPD untuk item sebelum penugasan reviu. Keputusan kelengkapan tetap dicatat melalui hasil pemeriksaan.</p>
<form method="POST" action="{{ route('notes.administrative',$item) }}" class="proposal-followup" data-submit-form>@csrf<input type="hidden" name="record_version" value="{{ \App\Support\FormInput::oldText('record_version',$recordVersion) }}"><div class="form-field"><label for="note-category">Kategori catatan</label><input id="note-category" name="category" required maxlength="100" value="{{ \App\Support\FormInput::oldText('category') }}"><x-field-error field="category"/></div><div class="form-field"><label for="note-description">Kelengkapan yang diperlukan</label><textarea id="note-description" name="description" rows="3" required maxlength="10000">{{ \App\Support\FormInput::oldText('description') }}</textarea><x-field-error field="description"/></div><div class="form-field"><label for="note-recommendation">Rekomendasi</label><textarea id="note-recommendation" name="recommendation" rows="3" maxlength="10000">{{ \App\Support\FormInput::oldText('recommendation') }}</textarea><x-field-error field="recommendation"/></div><div class="form-actionbar"><button class="btn primary" type="submit">Kirim catatan administrasi</button></div></form>
@endcan</section>
@endsection
