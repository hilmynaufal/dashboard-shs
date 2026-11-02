@extends('layouts.workspace')
@section('title','Penugasan reviu')
@section('subtitle','Penugasan yang dapat Anda akses berdasarkan keanggotaan tim.')
@section('actions')@can('create',\App\Models\ReviewAssignment::class)<a class="btn primary" href="{{ route('assignments.create') }}"><x-ui-icon name="plus"/>Buat penugasan</a>@endcan @endsection
@section('content')
<section class="table-panel" aria-label="Daftar penugasan">
<form class="table-toolbar" method="GET"><div class="search-field"><x-ui-icon name="search"/><label class="sr-only" for="assignment-search">Cari nomor atau judul penugasan</label><input id="assignment-search" name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="Cari nomor atau judul penugasan…"></div><div class="toolbar-end"><span class="select-wrap"><label class="sr-only" for="assignment-sort">Urutan</label><select id="assignment-sort" name="sort"><option value="newest">Terbaru</option><option value="number" @selected(request('sort')==='number')>Nomor penugasan</option></select></span><button class="btn secondary" type="submit">Terapkan</button></div></form>
@if($assignments->count())<div class="table-scroll"><table class="account-table"><thead><tr><th>Nomor dan judul</th><th>Status</th><th>Dibuat (WIB)</th><th><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
@foreach($assignments as $assignment)<tr><td><strong>{{ $assignment->number }}</strong><small class="cell-detail">{{ $assignment->title }}</small></td><td><span class="badge {{ $assignment->status==='active'?'blue':'neutral' }}">{{ $statuses[$assignment->status] }}</span></td><td>{{ $assignment->created_at?->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td><td><a class="btn secondary" href="{{ route('assignments.show',$assignment) }}" aria-label="Buka {{ $assignment->number }}">Buka</a></td></tr>@endforeach
</tbody></table></div>@else<div class="empty-state"><h3>Belum ada penugasan yang sesuai</h3><p>Ubah pencarian atau tunggu keanggotaan tim penugasan Anda.</p></div>@endif
<x-catalog-pagination :rows="$assignments" noun="penugasan"/>
</section>
@endsection
