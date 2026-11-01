@extends('layouts.workspace')
@section('title', 'Periode pengusulan')
@section('subtitle', 'Atur tahun anggaran dan waktu pengiriman usulan.')
@section('actions')<a class="btn primary" href="{{ route('periods.create') }}"><x-ui-icon name="plus"/>Buat periode</a>@endsection
@section('content')
<section class="table-panel" aria-label="Daftar periode pengusulan">
    <form class="table-toolbar" method="GET" action="{{ route('periods.index') }}">
        <div class="search-field"><x-ui-icon name="search"/><label class="sr-only" for="period-search">Cari nama atau tahun anggaran</label><input id="period-search" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau tahun anggaran…" maxlength="100"></div>
        <div class="toolbar-end"><span class="select-wrap"><label class="sr-only" for="period-status">Status pengaturan</label><select id="period-status" name="status"><option value="">Semua status</option>@foreach(['draft'=>'Draf','open'=>'Dibuka','closed'=>'Ditutup'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></span><button class="btn secondary" type="submit">Terapkan</button>@if(request()->filled('q')||request()->filled('status'))<a class="btn icon-text" href="{{ route('periods.index') }}">Reset</a>@endif</div>
    </form>
    <div class="table-scroll"><table class="catalog-table {{ $periods->isEmpty() ? 'catalog-empty-table' : '' }}"><thead><tr><th>Periode</th><th>Tahun anggaran</th><th>Rentang pengusulan (WIB)</th><th>Ketersediaan</th><th><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
    @forelse($periods as $period)
        <tr><td><strong>{{ $period->name }}</strong><small class="cell-detail">Pengaturan: {{ ['draft'=>'Draf','open'=>'Dibuka','closed'=>'Ditutup'][$period->status] }}</small></td><td>{{ $period->budget_year }}</td><td class="nowrap">{{ $period->opens_at->setTimezone(\App\Models\SubmissionPeriod::DISPLAY_TIMEZONE)->format('d/m/Y') }} – {{ $period->closes_at->setTimezone(\App\Models\SubmissionPeriod::DISPLAY_TIMEZONE)->format('d/m/Y') }}</td><td><span class="badge {{ $period->acceptsSubmissions() ? 'green' : 'neutral' }}"><span class="status-dot"></span>{{ $period->availability() }}</span></td><td class="number"><a class="btn icon-text" href="{{ route('periods.edit',$period) }}" aria-label="Ubah periode {{ $period->name }}">Ubah</a></td></tr>
    @empty<tr><td colspan="5"><div class="empty-state"><x-ui-icon name="calendar"/><h3>Belum ada periode yang sesuai</h3><p>Buat periode pengusulan atau ubah pencarian dan filter.</p><a class="btn secondary" href="{{ route('periods.create') }}">Buat periode</a></div></td></tr>@endforelse
    </tbody></table></div><x-catalog-pagination :rows="$periods" noun="periode"/>
</section>
<p class="quiet-note"><x-ui-icon name="calendar"/>Tanggal menggunakan WIB. Periode berstatus Dibuka hanya menerima pengiriman selama rentang pengusulan.</p>
@endsection
