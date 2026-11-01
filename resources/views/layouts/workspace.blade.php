<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · SHS/SBU</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="operator-app account-app">
<a class="skip-link" href="#main-content">Lewati navigasi</a>
<aside class="sidebar" id="workspace-sidebar" aria-label="Navigasi utama">
    <a class="brand" href="{{ route('workspace') }}"><span class="brand-mark"><x-ui-icon name="building"/></span><span><strong>SHS <span class="brand-slash">/</span> SBU</strong><small>Ruang kerja</small></span></a>
    <div class="org-switch"><span class="org-avatar"><x-ui-icon name="building"/></span><span><strong>{{ auth()->user()->organization_id ? \Illuminate\Support\Facades\DB::table('organizations')->where('id',auth()->user()->organization_id)->value('name') : 'Inspektorat Daerah' }}</strong><small>Sistem Informasi SHS New</small></span></div>
    <p class="nav-section">RUANG KERJA</p>
    <nav>
        <a href="{{ route('workspace') }}" class="{{ request()->routeIs('workspace') ? 'active' : '' }}" @if(request()->routeIs('workspace')) aria-current="page" @endif><x-ui-icon name="home"/>Beranda</a>
        @can('accounts.manage')<a href="{{ route('accounts.index') }}" class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}" @if(request()->routeIs('accounts.*')) aria-current="page" @endif><x-ui-icon name="users"/>Kelola akun</a>@endcan
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
