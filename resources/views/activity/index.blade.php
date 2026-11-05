@extends('layouts.workspace')
@section('title','Riwayat aktivitas')
@section('subtitle','Pelaku, waktu, objek, dan tindakan sesuai kewenangan Anda.')
@section('content')
<section class="table-panel"><form class="table-toolbar" method="GET" action="{{ route('activity.index') }}"><div class="form-field"><label for="activity-action">Tindakan</label><select id="activity-action" name="action"><option value="">Semua tindakan yang boleh dilihat</option>@foreach($actions as $code=>$label)<option value="{{ $code }}" @selected(($filters['action']??'')===$code)>{{ $label }}</option>@endforeach</select><x-field-error field="action"/></div><div class="activity-filter-actions"><button class="btn secondary" type="submit">Terapkan</button><a class="btn icon-text" href="{{ route('activity.index') }}">Hapus filter</a></div></form>
<div class="table-scroll" tabindex="0" role="region" aria-label="Daftar riwayat aktivitas"><table class="item-table activity-table"><thead><tr><th scope="col">Waktu</th><th scope="col">Pelaku</th><th scope="col">Tindakan</th><th scope="col">Objek</th></tr></thead><tbody>
@forelse($events as $event)<tr><td>{{ \Carbon\CarbonImmutable::parse($event->occurred_at,'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</td><td>{{ $event->actor_name??'Pelaku tidak tercatat' }}@if($event->actor_id)<small class="cell-detail">Akun #{{ $event->actor_id }}</small>@endif</td><td>{{ \App\Support\ActivityTrail::label($event) }}</td><td>{{ \App\Support\ActivityTrail::TYPES[$event->subject_type] }} #{{ $event->subject_id }}</td></tr>@empty<tr><td colspan="4">Belum ada aktivitas yang sesuai pilihan dan kewenangan Anda.</td></tr>@endforelse
</tbody></table></div><x-catalog-pagination :rows="$events" noun="aktivitas"/></section>
<p class="account-body-copy">Riwayat hanya-baca. Akses aktivitas teknis tidak memberikan akses ke isi reviu atau KKR.</p>
@endsection
