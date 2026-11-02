@extends('layouts.workspace')
@section('title','Catatan & tanggapan')
@section('subtitle','Catatan per item sesuai SKPD dan penugasan Anda.')
@section('content')
<section class="table-panel">
    <form class="table-toolbar" method="GET" action="{{ route('notes.index') }}">
        <div class="form-field"><label for="status">Status catatan</label><select id="status" name="status"><option value="">Semua status</option>@foreach(\App\Models\ReviewNote::LABELS as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></div>
        @if(request('item'))<input type="hidden" name="item" value="{{ request('item') }}">@endif
        <button class="btn secondary" type="submit">Terapkan</button><a class="btn icon-text" href="{{ route('notes.index') }}">Hapus filter</a>
    </form>
    @if($notes->count())<div class="table-scroll"><table class="item-table"><thead><tr><th>Item &amp; paket</th><th>Catatan</th><th>Jenis</th><th>Status</th><th><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
    @foreach($notes as $note)<tr><td><strong>{{ $note->item_name }}</strong><small class="cell-detail">{{ $note->number }} · Versi {{ $note->version_number }}</small></td><td>{{ $note->category }}</td><td>{{ $note->type==='administrative'?'Administrasi':'Reviu' }}</td><td><span class="badge {{ $note->status==='resolved'?'green':'neutral' }}">{{ \App\Models\ReviewNote::LABELS[$note->status] }}</span></td><td><a class="btn icon-text" href="{{ route('notes.show',$note->id) }}" aria-label="Buka catatan {{ $note->category }} untuk {{ $note->item_name }}">Buka catatan</a></td></tr>@endforeach
    </tbody></table></div>@else<div class="empty-state"><h2>Belum ada catatan yang dapat ditampilkan</h2><p>Coba hapus filter; draf internal tidak ditampilkan kepada SKPD.</p></div>@endif
    <x-catalog-pagination :rows="$notes" noun="catatan"/>
</section>
@endsection
