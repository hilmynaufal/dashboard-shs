@extends('layouts.workspace')
@section('title','Verifikasi kelengkapan')
@section('subtitle','Periksa item yang diajukan SKPD, lalu nyatakan siap atau kembalikan untuk dilengkapi.')
@section('content')
<section class="table-panel" aria-label="Antrean verifikasi item">
    <form class="table-toolbar" method="GET">
        <div class="search-field"><x-ui-icon name="search"/><label class="sr-only" for="verification-search">Cari item, nomor paket atau SKPD</label><input id="verification-search" name="q" type="search" value="{{ request('q') }}" maxlength="100" placeholder="Cari item, nomor paket atau SKPD…"></div>
        <div class="toolbar-end">
            <span class="select-wrap"><label class="sr-only" for="verification-status">Status item</label><select id="verification-status" name="status">@foreach(['submitted','needs_completion','ready'] as $key)<option value="{{ $key }}" @selected($status===$key)>{{ \App\Models\ProposalItem::LABELS[$key] }}</option>@endforeach</select></span>
            <span class="select-wrap"><label class="sr-only" for="verification-type">Jenis usulan</label><select id="verification-type" name="type"><option value="">Semua jenis</option>@foreach(['SHS','SBU'] as $type)<option @selected(request('type')===$type)>{{ $type }}</option>@endforeach</select></span>
            <button class="btn secondary" type="submit">Terapkan</button>
        </div>
    </form>
    @if($items->count())<div class="table-scroll"><table class="item-table"><thead><tr><th>Item dan paket</th><th>SKPD</th><th>Jenis</th><th>Versi terkirim</th><th>Status</th><th>Dikirim (WIB)</th><th><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
    @foreach($items as $item)<tr><td><strong>{{ $item->description }}</strong><small class="cell-detail">{{ $item->number }}</small></td><td>{{ $item->organization_name }}</td><td>{{ $item->type }}</td><td>{{ $item->version_number }}</td><td><span class="badge {{ $item->status==='ready'?'green':($item->status==='needs_completion'?'warning':'blue') }}">{{ \App\Models\ProposalItem::LABELS[$item->status] }}</span></td><td class="nowrap">{{ \Carbon\CarbonImmutable::parse($item->submitted_at,'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td><td><a class="btn secondary" href="{{ route('verification.show',$item->id) }}" aria-label="Periksa {{ $item->description }}">Periksa</a></td></tr>@endforeach
    </tbody></table></div>@else<div class="empty-state"><x-ui-icon name="shield"/><h3>Tidak ada item yang sesuai</h3><p>Ubah pencarian atau status untuk melihat item lainnya.</p></div>@endif
    <x-catalog-pagination :rows="$items" noun="item"/>
</section>
<p class="quiet-note">Pemeriksaan dilakukan per item. Item siap direviu tidak menunggu kelengkapan item lain dalam paket.</p>
@endsection
