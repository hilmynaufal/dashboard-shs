<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · SHS/SBU</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>document.documentElement.classList.add('js')</script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $me = auth()->user();
    $codes = $me->permissionCodes();
    $any = fn (array $wanted): bool => (bool) array_intersect($codes, $wanted);
    $reviewer = $me->hasPermission('proposals.view_assigned');
    // [label, route, parameter rute, pola aktif, ikon, tampil]
    $navGroups = [
        'Ruang kerja' => [
            ['Beranda', 'workspace', [], request()->routeIs('workspace'), 'home', true],
            ['Usulan', 'proposals.index', [], request()->routeIs('proposals.*'), 'folder', $any(['proposals.view_own', 'proposals.view_all', 'proposals.view_assigned'])],
            ['Catatan & tanggapan', 'notes.index', [], request()->routeIs('notes.*'), 'message', $any(['notes.respond_own', 'notes.administrative', 'proposals.view_assigned'])],
            ['Pemantauan', 'progress.index', [], request()->routeIs('progress.*'), 'chart', $any(['monitoring.own', 'monitoring.all', 'monitoring.assigned'])],
        ],
        'Zona Integritas' => [
            ['Penilaian ZI', 'zi.index', [], request()->routeIs('zi.*'), 'clipboard', $any(['zi.manage', 'zi.template', 'zi.write', 'zi.submit', 'zi.review', 'zi.return', 'zi.finalize'])],
        ],
        'Reviu' => [
            ['Impor Inspektorat', 'inspectorate.imports.index', [], request()->routeIs('inspectorate.imports.*'), 'upload', $me->can('inspectorate.import')],
            ['Verifikasi kelengkapan', 'verification.index', [], request()->routeIs('verification.*'), 'shield', $me->can('verification.perform') && $me->can('proposals.view_all')],
            ['Penugasan reviu', 'assignments.index', [], request()->routeIs('assignments.*') && ! request()->routeIs('assignments.work'), 'user-check', $reviewer && ($me->hasRole('team_lead') || $me->hasRole('technical_controller') || $me->hasRole('examiner'))],
            ['Pekerjaan pemeriksa', 'assignments.work', [], request()->routeIs('assignments.work'), 'clipboard', $reviewer && $me->hasRole('examiner')],
            ['KKR & supervisi', 'kkr.index', [], request()->routeIs('kkr.*'), 'file-check', $me->can('kkr.view')],
        ],
        'Arsip & laporan' => [
            ['Rekap usulan', 'reports.index', [], request()->routeIs('reports.*'), 'table', $any(['reports.own', 'reports.all', 'reports.assigned'])],
            ['Arsip KKR', 'archives.index', [], request()->routeIs('archives.*'), 'archive', $me->can('archives.assigned')],
            ['Riwayat aktivitas', 'activity.index', [], request()->routeIs('activity.*'), 'history', $any(['audit.technical', 'audit.assigned'])],
        ],
        'Administrasi' => [
            ['Kelola akun', 'accounts.index', [], request()->routeIs('accounts.*'), 'users', $me->can('accounts.manage')],
            ['Periode pengusulan', 'periods.index', [], request()->routeIs('periods.*'), 'calendar', $me->can('periods.manage')],
            ['Data referensi', 'references.index', ['barang'], request()->routeIs('references.*'), 'database', $me->can('references.manage')],
        ],
    ];
@endphp
<body class="operator-app account-app {{ request()->routeIs('proposals.*','verification.*') ? 'proposal-workspace' : '' }} {{ request()->routeIs('kkr.*','archives.*') ? 'kkr-workspace' : '' }}">
<a class="skip-link" href="#main-content">Lewati navigasi</a>
<aside class="sidebar" id="workspace-sidebar" aria-label="Navigasi utama">
    <a class="brand" href="{{ route('workspace') }}"><x-brand-mark/><span><strong>SHS <span class="brand-slash">/</span> SBU</strong><small>Ruang kerja</small></span></a>
    <div class="org-switch"><span class="org-avatar"><x-ui-icon name="building"/></span><span><strong>{{ $shellOrganization }}</strong><small>{{ $shellRoles ?: 'Sistem Informasi SHS New' }}</small></span></div>
    <nav>
        @foreach($navGroups as $group => $links)
            @php($links = array_filter($links, fn ($link) => $link[5]))
            @continue(! $links)
            <p class="nav-section">{{ mb_strtoupper($group) }}</p>
            @foreach($links as [$label, $routeName, $parameters, $active, $icon])
                <a href="{{ route($routeName, $parameters) }}" class="{{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif><x-ui-icon :name="$icon"/>{{ $label }}</a>
            @endforeach
        @endforeach
    </nav>
    <div class="sidebar-bottom">
        @if($shellPeriod)
        <div class="period-card"><x-ui-icon name="calendar"/><div><span>Periode pengusulan</span><strong>{{ $shellPeriod->name }}</strong><small><i></i>Terbuka · {{ $shellPeriodDays > 0 ? 'tutup '.$shellPeriodDays.' hari lagi' : 'tutup hari ini' }}</small></div></div>
        @endif
        <a class="user-button {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif><span class="avatar">{{ mb_strtoupper(mb_substr($me->name,0,1)) }}</span><span><strong>{{ $me->name }}</strong><small>Profil saya</small></span><x-ui-icon name="arrow"/></a>
        <form method="POST" action="{{ route('session.destroy') }}">@csrf<button class="sidebar-help" type="submit"><x-ui-icon name="logout"/>Keluar</button></form>
    </div>
</aside>
<button class="drawer-scrim" data-nav-close aria-label="Tutup navigasi" tabindex="-1"></button>
<div class="app-main">
    <header class="topbar">
        <button class="icon-btn menu-toggle" data-nav-toggle aria-label="Buka navigasi" aria-expanded="false" aria-controls="workspace-sidebar"><x-ui-icon name="menu"/></button>
        <nav class="breadcrumb" aria-label="Jejak halaman"><a href="{{ route('workspace') }}">Ruang kerja</a>@hasSection('crumbs')@yield('crumbs')@endif<x-ui-icon name="arrow"/><strong>@yield('title')</strong></nav>
        <a class="account-top-profile" href="{{ route('profile.edit') }}" aria-label="Profil saya"><span class="avatar small">{{ mb_strtoupper(mb_substr($me->name,0,1)) }}</span><span>{{ $me->name }}</span></a>
    </header>
    <main id="main-content" tabindex="-1">
        <div class="page-heading"><div><h1>@yield('title')</h1><p>@yield('subtitle')</p></div><div class="heading-actions">@yield('actions')</div></div>
        @if(session('status'))<div class="account-message success" role="status" data-toast><x-ui-icon name="check-circle"/><span>{{ session('status') }}</span></div>@endif
        @if($errors->any())<div class="account-message error" role="alert"><x-ui-icon name="alert"/><div><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div></div>@endif
        @yield('content')
    </main>
    <footer class="app-footer"><span>Inspektorat Daerah</span><span>Sistem Informasi SHS New</span></footer>
</div>
<dialog class="confirm-dialog" data-confirm-dialog aria-labelledby="confirm-title">
    <form method="dialog">
        <div class="confirm-icon"><x-ui-icon name="alert"/></div>
        <h2 id="confirm-title">Lanjutkan tindakan ini?</h2>
        <p data-confirm-text></p>
        <div class="dialog-actions"><button class="btn secondary" value="cancel" autofocus>Batal</button><button class="btn primary" value="ok">Ya, lanjutkan</button></div>
    </form>
</dialog>
</body>
</html>
