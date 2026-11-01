@extends('layouts.workspace')
@section('title', 'Kelola akun')
@section('subtitle', 'Atur akun pengguna dan status akses aplikasi.')
@section('actions')<a class="btn primary" href="{{ route('accounts.create') }}"><x-ui-icon name="plus"/>Buat akun</a>@endsection
@section('content')
<section class="table-panel" aria-label="Daftar akun pengguna">
    <form class="table-toolbar" method="GET" action="{{ route('accounts.index') }}">
        <div class="search-field"><x-ui-icon name="search"/><label class="sr-only" for="account-search">Cari nama atau email</label><input id="account-search" name="q" value="{{ request('q') }}" type="search" placeholder="Cari nama atau email…" maxlength="100"></div>
        <div class="toolbar-end"><label class="sr-only" for="status-filter">Status akun</label><span class="select-wrap"><select id="status-filter" name="status"><option value="">Semua status</option><option value="active" @selected(request('status')==='active')>Aktif</option><option value="inactive" @selected(request('status')==='inactive')>Nonaktif</option></select></span><button class="btn secondary" type="submit">Terapkan</button>@if(request()->filled('q') || request()->filled('status'))<a class="btn icon-text" href="{{ route('accounts.index') }}">Reset</a>@endif</div>
    </form>
    <div class="table-scroll"><table class="account-table"><thead><tr><th>Pengguna</th><th>Peran</th><th>SKPD</th><th>Status</th><th><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
    @forelse($users as $account)
        <tr><td><strong>{{ $account->name }}</strong><small class="cell-detail">{{ $account->email }}</small></td><td>{{ ($rolesByUser[$account->id] ?? collect())->pluck('name')->join(', ') ?: 'Belum ditetapkan' }}</td><td>{{ $organizations[$account->organization_id] ?? '—' }}</td><td><span class="badge {{ $account->is_active ? 'green' : 'neutral' }}"><span class="status-dot"></span>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="number">@if($account->id === auth()->id())<small class="muted">Akun Anda</small>@else<form method="POST" action="{{ route('accounts.status',$account) }}" data-status-confirm="{{ $account->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $account->name }}?" data-submit-form>@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $account->is_active ? '0' : '1' }}"><button class="btn icon-text" type="submit">{{ $account->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>@endif</td></tr>
    @empty<tr><td colspan="5"><div class="empty-state"><x-ui-icon name="users"/><h3>Tidak ada akun yang sesuai</h3><p>Ubah pencarian atau filter status untuk melihat akun lain.</p><a class="btn secondary" href="{{ route('accounts.index') }}">Reset pencarian</a></div></td></tr>@endforelse
    </tbody></table></div>
    <div class="table-footer"><span>{{ $users->total() ? 'Menampilkan '.$users->firstItem().'–'.$users->lastItem().' dari '.$users->total().' akun' : '0 akun' }}</span><div class="pagination">@if($users->onFirstPage())<span>Sebelumnya</span>@else<a class="btn icon-text" href="{{ $users->previousPageUrl() }}">Sebelumnya</a>@endif<span class="page-number">{{ $users->currentPage() }}</span>@if($users->hasMorePages())<a class="btn icon-text" href="{{ $users->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif</div></div>
</section>
<p class="quiet-note"><x-ui-icon name="shield"/>Akun nonaktif tidak dapat masuk; riwayat pengguna tetap tersimpan.</p>
@endsection
