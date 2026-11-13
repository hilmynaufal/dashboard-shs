@extends('layouts.workspace')
@section('title', 'Beranda')
@section('subtitle', 'Selamat datang, '.auth()->user()->name.'. Berikut pekerjaan dalam lingkup akses Anda.')
@section('content')
@php
    $me = auth()->user();
    $codes = $me->permissionCodes();
    $shortcuts = array_filter([
        $me->can('create', \App\Models\Proposal::class) ? ['Buat usulan baru', route('proposals.create'), 'plus'] : null,
        array_intersect($codes, ['proposals.view_own', 'proposals.view_all', 'proposals.view_assigned']) ? ['Daftar usulan', route('proposals.index'), 'folder'] : null,
        array_intersect($codes, ['reports.own', 'reports.all', 'reports.assigned']) ? ['Rekap dan unduh Excel', route('reports.index'), 'table'] : null,
        $me->can('accounts.manage') ? ['Kelola akun', route('accounts.index'), 'users'] : null,
        $me->can('periods.manage') ? ['Periode pengusulan', route('periods.index'), 'calendar'] : null,
        ['Profil dan kata sandi', route('profile.edit'), 'user'],
    ]);
@endphp
@if(empty($roles))
<div class="account-message" role="status"><x-ui-icon name="info"/><div><strong>Peran belum ditetapkan.</strong> Hubungi Admin untuk menetapkan peran sebelum menggunakan modul kerja.</div></div>
@endif
@if($board)
<div class="home-stats">
    <a class="home-stat" href="{{ route('progress.index',['view'=>'packages']) }}"><span class="stat-icon"><x-ui-icon name="folder"/></span><span><strong>{{ array_sum($board['groups']['packages']??[]) }}</strong><small>Paket usulan</small></span></a>
    <a class="home-stat" href="{{ route('progress.index',['view'=>'items']) }}"><span class="stat-icon"><x-ui-icon name="file"/></span><span><strong>{{ array_sum($board['groups']['items']??[]) }}</strong><small>Item terlihat</small></span></a>
    <a class="home-stat" href="{{ route('progress.index',['view'=>'notes','status'=>'open']) }}"><span class="stat-icon"><x-ui-icon name="message"/></span><span><strong>{{ $board['openNotes'] }}</strong><small>Catatan terbuka</small></span></a>
    @if($board['canKkr'])<a class="home-stat" href="{{ route('progress.index',['view'=>'kkr']) }}"><span class="stat-icon"><x-ui-icon name="file-check"/></span><span><strong>{{ array_sum($board['groups']['kkr']??[]) }}</strong><small>KKR</small></span></a>@endif
</div>
@endif
<div class="home-grid">
    <section class="home-panel" aria-labelledby="queue-title">
        <header><div><h2 id="queue-title">Perlu tindakan Anda</h2><p>Dihitung ulang saat halaman dimuat, sesuai peran dan lingkup akses.</p></div>@if($board)<a class="btn icon-text" href="{{ route('progress.index') }}">Pemantauan<x-ui-icon name="arrow"/></a>@endif</header>
        @forelse($queue as [$label, $detail, $total, $icon, $tone, $url])
            <a class="queue-row" href="{{ $url }}"><span class="queue-icon {{ $tone }}"><x-ui-icon :name="$icon"/></span><div><strong>{{ $label }}</strong><small>{{ $detail }}</small></div><span class="queue-count {{ $tone==='warning'?'hot':'' }}">{{ $total }}</span><x-ui-icon name="arrow"/></a>
        @empty
            <div class="home-empty"><span class="queue-icon"><x-ui-icon name="check"/></span><span>{{ $board ? 'Tidak ada antrean yang menunggu Anda saat ini.' : 'Pilih modul melalui menu sesuai akses Anda.' }}</span></div>
        @endforelse
    </section>
    <div class="home-side">
        <section class="home-panel period-panel" aria-label="Periode pengusulan">
            <span class="summary-label">Periode pengusulan</span>
            @if($period)
                <h2>{{ $period->name }}</h2>
                <div class="period-meter" role="img" aria-label="{{ $periodProgress }}% masa pengusulan telah berjalan"><i style="width: {{ $periodProgress }}%"></i></div>
                <div class="period-dates"><span>Buka {{ $period->opens_at->timezone(\App\Models\SubmissionPeriod::DISPLAY_TIMEZONE)->format('d/m/Y') }}</span><span>Tutup {{ $period->closes_at->timezone(\App\Models\SubmissionPeriod::DISPLAY_TIMEZONE)->format('d/m/Y') }}</span></div>
            @else
                <h2>Tidak ada periode terbuka</h2><p class="account-body-copy">Draf tetap dapat disiapkan; pengiriman menunggu periode dibuka.</p>
            @endif
        </section>
        <section class="home-panel" aria-labelledby="shortcut-title">
            <header><h2 id="shortcut-title">Pintasan</h2></header>
            <div class="shortcut-list">@foreach($shortcuts as [$label, $url, $icon])<a href="{{ $url }}"><x-ui-icon :name="$icon"/>{{ $label }}</a>@endforeach</div>
            <div class="account-role-list">@forelse($roles as $code => $name)<span class="badge neutral plain">{{ $name }}</span>@empty<span class="badge warning">Peran belum ditetapkan</span>@endforelse</div>
        </section>
    </div>
</div>
@endsection
