<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · SHS/SBU</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="operator-app account-app {{ request()->routeIs('proposals.*','verification.*') ? 'proposal-workspace' : '' }} {{ request()->routeIs('kkr.*') ? 'kkr-workspace' : '' }}">
<a class="skip-link" href="#main-content">Lewati navigasi</a>
<aside class="sidebar" id="workspace-sidebar" aria-label="Navigasi utama">
    <a class="brand" href="{{ route('workspace') }}"><span class="brand-mark"><x-ui-icon name="building"/></span><span><strong>SHS <span class="brand-slash">/</span> SBU</strong><small>Ruang kerja</small></span></a>
    <div class="org-switch"><span class="org-avatar"><x-ui-icon name="building"/></span><span><strong>{{ auth()->user()->organization_id ? \Illuminate\Support\Facades\DB::table('organizations')->where('id',auth()->user()->organization_id)->value('name') : 'Inspektorat Daerah' }}</strong><small>Sistem Informasi SHS New</small></span></div>
    <p class="nav-section">RUANG KERJA</p>
    <nav>
        <a href="{{ route('workspace') }}" class="{{ request()->routeIs('workspace') ? 'active' : '' }}" @if(request()->routeIs('workspace')) aria-current="page" @endif><x-ui-icon name="home"/>Beranda</a>
        @if(array_intersect(auth()->user()->permissionCodes(),['monitoring.own','monitoring.all','monitoring.assigned']))<a href="{{ route('progress.index') }}" class="{{ request()->routeIs('progress.*') ? 'active' : '' }}" @if(request()->routeIs('progress.*')) aria-current="page" @endif><x-ui-icon name="catalog"/>Pemantauan</a>@endif
        @if(array_intersect(auth()->user()->permissionCodes(),['reports.own','reports.all','reports.assigned']))<a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif><x-ui-icon name="catalog"/>Rekap usulan</a>@endif
        @if(array_intersect(auth()->user()->permissionCodes(),['proposals.view_own','proposals.view_all','proposals.view_assigned']))<a href="{{ route('proposals.index') }}" class="{{ request()->routeIs('proposals.*') ? 'active' : '' }}" @if(request()->routeIs('proposals.*')) aria-current="page" @endif><x-ui-icon name="catalog"/>Usulan</a>@endif
        @can('verification.perform')@can('proposals.view_all')<a href="{{ route('verification.index') }}" class="{{ request()->routeIs('verification.*') ? 'active' : '' }}" @if(request()->routeIs('verification.*')) aria-current="page" @endif><x-ui-icon name="shield"/>Verifikasi kelengkapan</a>@endcan @endcan
        @if(auth()->user()->hasPermission('proposals.view_assigned') && (auth()->user()->hasRole('team_lead') || auth()->user()->hasRole('technical_controller') || auth()->user()->hasRole('examiner')))<a href="{{ route('assignments.index') }}" class="{{ request()->routeIs('assignments.*') && !request()->routeIs('assignments.work') ? 'active' : '' }}" @if(request()->routeIs('assignments.*') && !request()->routeIs('assignments.work')) aria-current="page" @endif><x-ui-icon name="users"/>Penugasan reviu</a>@endif
        @if(auth()->user()->hasRole('examiner') && auth()->user()->hasPermission('proposals.view_assigned'))<a href="{{ route('assignments.work') }}" class="{{ request()->routeIs('assignments.work') ? 'active' : '' }}" @if(request()->routeIs('assignments.work')) aria-current="page" @endif><x-ui-icon name="catalog"/>Pekerjaan pemeriksa</a>@endif
        @if(array_intersect(auth()->user()->permissionCodes(),['notes.respond_own','notes.administrative','proposals.view_assigned']))<a href="{{ route('notes.index') }}" class="{{ request()->routeIs('notes.*') ? 'active' : '' }}" @if(request()->routeIs('notes.*')) aria-current="page" @endif><x-ui-icon name="catalog"/>Catatan &amp; tanggapan</a>@endif
        @can('kkr.view')<a href="{{ route('kkr.index') }}" class="{{ request()->routeIs('kkr.*') ? 'active' : '' }}" @if(request()->routeIs('kkr.*')) aria-current="page" @endif><x-ui-icon name="catalog"/>KKR &amp; supervisi</a>@endcan
        @can('accounts.manage')<a href="{{ route('accounts.index') }}" class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}" @if(request()->routeIs('accounts.*')) aria-current="page" @endif><x-ui-icon name="users"/>Kelola akun</a>@endcan
        @can('periods.manage')<a href="{{ route('periods.index') }}" class="{{ request()->routeIs('periods.*') ? 'active' : '' }}" @if(request()->routeIs('periods.*')) aria-current="page" @endif><x-ui-icon name="calendar"/>Periode pengusulan</a>@endcan
        @can('references.manage')<a href="{{ route('references.index','barang') }}" class="{{ request()->routeIs('references.*') ? 'active' : '' }}" @if(request()->routeIs('references.*')) aria-current="page" @endif><x-ui-icon name="catalog"/>Data referensi</a>@endcan
        <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif><x-ui-icon name="user"/>Profil saya</a>
    </nav>
    <div class="sidebar-bottom">
        <div class="user-button"><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>Akun aktif</small></span></div>
        <form method="POST" action="{{ route('session.destroy') }}">@csrf<button class="sidebar-help" type="submit"><x-ui-icon name="logout"/>Keluar</button></form>
    </div>
</aside>
<button class="drawer-scrim" data-nav-close aria-label="Tutup navigasi" tabindex="-1"></button>
<div class="app-main">
    <header class="topbar">
        <button class="icon-btn menu-toggle" data-nav-toggle aria-label="Buka navigasi" aria-expanded="false" aria-controls="workspace-sidebar"><x-ui-icon name="menu"/></button>
        <div class="breadcrumb"><a href="{{ route('workspace') }}">Ruang kerja</a><x-ui-icon name="arrow"/><strong>@yield('title')</strong></div>
        <a class="account-top-profile" href="{{ route('profile.edit') }}" aria-label="Profil saya"><x-ui-icon name="user"/><span>{{ auth()->user()->name }}</span></a>
    </header>
    <main id="main-content" tabindex="-1">
        <div class="page-heading"><div><h1>@yield('title')</h1><p>@yield('subtitle')</p></div><div class="heading-actions">@yield('actions')</div></div>
        @if(session('status'))<div class="account-message success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="account-message error" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
    <footer class="app-footer"><span>Inspektorat Daerah</span><span>Sistem Informasi SHS New</span></footer>
</div>
</body>
</html>
