@extends('layouts.workspace')
@section('title','Pemeriksaan item')
@section('subtitle',$proposal->number.' · '.$organization.' · Versi terkirim '.$version->version_number)
@section('actions')<a class="btn secondary" href="{{ route('verification.index') }}">Kembali ke antrean</a><a class="btn secondary" href="{{ route('proposals.show',$proposal) }}">Lihat paket</a>@endsection
@section('content')
<div class="check-layout">
    <section class="form-panel">@include('verification.item-content')</section>
    <aside class="submit-panel"><h2>Hasil kelengkapan</h2><p class="verification-current-status"><span class="badge {{ $item->status==='ready'?'green':($item->status==='needs_completion'?'warning':'blue') }}">{{ \App\Models\ProposalItem::LABELS[$item->status] }}</span></p>
    @can('verify',$item)
    <form method="POST" action="{{ route('verification.decide',$item) }}" data-submit-form>@csrf
        <input type="hidden" name="record_version" value="{{ old('record_version',$recordVersion) }}">
        <div class="form-field"><label for="result">Keputusan</label><select id="result" name="result" required><option value="">Pilih hasil pemeriksaan</option><option value="ready" @selected(old('result')==='ready')>Siap direviu</option><option value="needs_completion" @selected(old('result')==='needs_completion')>Perlu dilengkapi</option></select><x-field-error field="result"/></div>
        <div class="form-field"><label for="reason">Alasan atau catatan</label><textarea id="reason" name="reason" rows="5" maxlength="5000" aria-describedby="reason-help">{{ old('reason') }}</textarea><small id="reason-help">Wajib untuk pengembalian. Alasan ini dapat dibaca Operator SKPD.</small><x-field-error field="reason"/></div>
        <p class="form-bottom-note">Keputusan berlaku untuk versi terkirim yang ditampilkan dan dicatat dalam riwayat.</p>
        @if($errors->has('record_version'))<a class="btn secondary access-reload" href="{{ route('verification.show',$item) }}">Muat ulang pemeriksaan</a>@endif
        <div class="form-actionbar"><button class="btn primary" type="submit">Simpan hasil pemeriksaan</button></div>
    </form>
    @else<p class="account-body-copy">{{ $item->status==='needs_completion'?'Menunggu SKPD mengirim kelengkapan. Versi perbaikan yang belum dikirim tidak ditampilkan di sini.':($item->status==='ready'?'Item ini sudah siap untuk penugasan reviu, terlepas dari status item lain dalam paket.':'Item tidak berada dalam antrean verifikasi.') }}</p>@endcan
    </aside>
</div>
@include('verification.history')
@endsection
