@extends('layouts.workspace')
@section('title', 'Beranda')
@section('subtitle', 'Identitas dan akses ruang kerja Anda.')
@section('content')
<section class="form-panel account-welcome">
    <div class="section-heading"><span class="section-icon"><x-ui-icon name="user"/></span><div><h2>Selamat datang, {{ auth()->user()->name }}</h2><p>{{ auth()->user()->email }}</p></div></div>
    <div class="account-role-list">@forelse($roles as $code => $name)<span class="badge neutral">{{ $name }}</span>@empty<span class="badge warning">Peran belum ditetapkan</span>@endforelse</div>
    <p class="account-body-copy">@if(empty($roles))Hubungi Admin untuk menetapkan peran sebelum menggunakan modul kerja.@else Pilih modul melalui menu sesuai akses Anda. Usulan, verifikasi kelengkapan, penugasan, dan pencatatan hasil reviu tersedia sesuai kewenangan.@endif</p>
    <div class="account-links"><a class="btn secondary" href="{{ route('profile.edit') }}"><x-ui-icon name="user"/>Kelola profil</a>@can('accounts.manage')<a class="btn primary" href="{{ route('accounts.index') }}"><x-ui-icon name="users"/>Kelola akun</a>@endcan</div>
</section>
@endsection
