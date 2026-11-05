@extends('layouts.workspace')
@section('title','Rekap usulan')
@section('subtitle','Cari item SHS/SBU dan unduh hasil sesuai akses Anda.')
@section('actions')<a class="btn primary" href="{{ route('reports.export',array_diff_key($filters,['page'=>true])) }}">Unduh Excel · {{ $rows->total() }} item</a>@endsection
@section('content')
<section class="form-panel">
    <form method="GET" action="{{ route('reports.index') }}" class="report-filters">
        <div class="form-field report-search"><label for="report-search">Kode barang atau uraian</label><input id="report-search" type="search" name="q" maxlength="100" value="{{ $filters['q']??'' }}" placeholder="Cari kode atau nama item"><x-field-error field="q"/></div>
        <div class="form-field"><label for="report-year">Tahun anggaran</label><select id="report-year" name="year"><option value="">Semua tahun</option>@foreach($years as $year)<option value="{{ $year }}" @selected((string)($filters['year']??'')===(string)$year)>{{ $year }}</option>@endforeach @if(isset($filters['year']) && !in_array((int)$filters['year'],array_map('intval',$years),true))<option value="{{ $filters['year'] }}" selected>{{ $filters['year'] }}</option>@endif</select><x-field-error field="year"/></div>
        <div class="form-field"><label for="report-type">Jenis</label><select id="report-type" name="type"><option value="">SHS dan SBU</option>@foreach(['SHS','SBU'] as $type)<option @selected(($filters['type']??'')===$type)>{{ $type }}</option>@endforeach</select><x-field-error field="type"/></div>
        <div class="form-field"><label for="report-status">Status item</label><select id="report-status" name="status"><option value="">Semua status</option>@foreach(\App\Models\ProposalItem::LABELS as $code=>$name)<option value="{{ $code }}" @selected(($filters['status']??'')===$code)>{{ $name }}</option>@endforeach</select><x-field-error field="status"/></div>
        <div class="form-field report-org"><label for="report-org">SKPD</label><select id="report-org" name="organization"><option value="">Semua SKPD sesuai akses</option>@foreach($organizations as $org)<option value="{{ $org->id }}" @selected((string)($filters['organization']??'')===(string)$org->id)>{{ $org->name }}</option>@endforeach</select><x-field-error field="organization"/></div>
        <div class="report-filter-actions"><button class="btn secondary" type="submit">Terapkan filter</button><a class="btn icon-text" href="{{ route('reports.index') }}">Hapus filter</a></div>
    </form>
</section>
<p class="account-body-copy report-explanation">Excel memuat seluruh hasil filter, termasuk halaman berikutnya. Kode, rekening, dan harga disimpan sebagai teks agar nol awal dan seluruh digit tetap utuh. Harga berupa teks dan tidak langsung dijumlahkan di Excel. Pilih “Terapkan filter” sebelum mengunduh pilihan baru.</p>
<section class="table-panel">
    <div class="table-toolbar"><strong>{{ $rows->total() }} item sesuai pilihan</strong><span class="account-body-copy">Versi terbaru yang boleh Anda lihat</span></div>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Hasil rekap usulan; geser untuk melihat seluruh kolom"><table class="item-table report-table"><thead><tr><th scope="col">Kode barang</th><th scope="col">Uraian &amp; spesifikasi</th><th scope="col">SKPD / paket</th><th scope="col">Tahun / jenis</th><th scope="col">Satuan / rekening</th><th scope="col">Harga usulan</th><th scope="col">Status / versi</th><th scope="col">Tindakan</th></tr></thead><tbody>
    @forelse($rows as $row)@php($values=\App\Support\ItemReport::values($row,0))<tr>
        <td>{{ $values[5]?:'Belum dicatat' }}</td><td><strong>{{ $row->description }}</strong><small class="cell-detail verification-copy">{{ $row->specification }}</small></td>
        <td>{{ $row->organization_name }}<small class="cell-detail">{{ $row->number }}</small></td><td>{{ $row->budget_year }}<small class="cell-detail">{{ $row->type }}</small></td>
        <td>{{ $values[8]?:'Belum dicatat' }}<small class="cell-detail">{{ $values[9]?:'Rekening belum dicatat' }}</small></td>
        <td>{{ \App\Support\ProposalMoney::display($row->proposed_price) }}</td><td><span class="badge neutral">{{ $values[11] }}</span><small class="cell-detail">Versi {{ $row->version_number }}</small></td>
        <td><a class="btn icon-text" href="{{ route('proposals.items.version',[$row->proposal_id,$row->item_id,$row->version_id]) }}">Buka<span class="sr-only"> {{ $row->description }}</span></a></td>
    </tr>@empty<tr><td colspan="8">Tidak ada item yang sesuai dengan pencarian dan filter dalam lingkup akses Anda. Ubah pilihan atau hapus filter untuk mencoba kembali.</td></tr>@endforelse
    </tbody></table></div><x-catalog-pagination :rows="$rows" noun="item"/>
</section>
@endsection
