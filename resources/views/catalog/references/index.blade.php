@extends('layouts.workspace')
@section('title', 'Data referensi')
@section('subtitle', 'Kelola kode barang, rekening, dan satuan untuk pengusulan.')
@section('actions')<a class="btn primary" href="{{ route('references.create',$kind) }}"><x-ui-icon name="plus"/>Tambah {{ mb_strtolower($type['label']) }}</a>@endsection
@section('content')
<nav class="catalog-tabs" aria-label="Jenis referensi">@foreach(\App\Support\ReferenceCatalog::TYPES as $key=>$entry)<a href="{{ route('references.index',$key) }}" class="{{ $key===$kind ? 'selected' : '' }}" @if($key===$kind) aria-current="page" @endif>{{ $entry['label'] }}</a>@endforeach</nav>
<section class="table-panel" aria-label="Daftar {{ mb_strtolower($type['label']) }}">
    <form class="table-toolbar" method="GET" action="{{ route('references.index',$kind) }}">
        <div class="search-field"><x-ui-icon name="search"/><label class="sr-only" for="reference-search">Cari kode atau nama</label><input id="reference-search" type="search" name="q" value="{{ request('q') }}" placeholder="Cari kode atau nama…" maxlength="100"></div>
        <div class="toolbar-end"><span class="select-wrap"><label class="sr-only" for="reference-status">Status referensi</label><select id="reference-status" name="status"><option value="">Semua status</option><option value="active" @selected(request('status')==='active')>Aktif</option><option value="inactive" @selected(request('status')==='inactive')>Nonaktif</option></select></span><button class="btn secondary" type="submit">Terapkan</button>@if(request()->filled('q')||request()->filled('status'))<a class="btn icon-text" href="{{ route('references.index',$kind) }}">Reset</a>@endif</div>
    </form>
    <div class="table-scroll"><table class="catalog-table {{ $rows->isEmpty() ? 'catalog-empty-table' : '' }}"><thead><tr><th>Kode</th><th>Nama</th><th>Status</th><th><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
    @forelse($rows as $row)
        <tr><td class="nowrap"><strong>{{ $row->code }}</strong></td><td>{{ $row->name }}</td><td><span class="badge {{ $row->is_active ? 'green' : 'neutral' }}"><span class="status-dot"></span>{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td><div class="account-row-actions"><a class="btn icon-text" href="{{ route('references.edit',[$kind,$row->id]) }}" aria-label="Ubah {{ $row->code }}">Ubah</a><form method="POST" action="{{ route('references.destroy',[$kind,$row->id]) }}" data-submit-form data-status-confirm="Hapus referensi {{ $row->code }}? Referensi yang sudah digunakan akan ditolak.">@csrf @method('DELETE')<input type="hidden" name="record_version" value="{{ \App\Support\RecordVersion::of((array)$row) }}"><button class="btn icon-text catalog-delete" type="submit" aria-label="Hapus {{ $row->code }}">Hapus</button></form></div></td></tr>
    @empty<tr><td colspan="4"><div class="empty-state"><x-ui-icon name="catalog"/><h3>Belum ada referensi yang sesuai</h3><p>Tambah referensi atau ubah pencarian dan filter.</p><a class="btn secondary" href="{{ route('references.create',$kind) }}">Tambah referensi</a></div></td></tr>@endforelse
    </tbody></table></div><x-catalog-pagination :rows="$rows" noun="referensi"/>
</section>
<p class="quiet-note"><x-ui-icon name="shield"/>Kode disimpan sebagai teks. Referensi yang sudah digunakan tidak dapat dihapus; nonaktifkan bila tidak digunakan untuk usulan baru.</p>
@endsection
