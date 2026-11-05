@extends('layouts.workspace')
@section('title','Preview KKR')
@section('subtitle',$print['facts'][0][1].' · Versi '.$version->version_number.' · '.($version->finalized_at?'Final':'Belum final'))
@section('actions')
<a class="btn secondary" href="{{ route('kkr.show',[$document,'version'=>$version->id]) }}">Kembali ke versi {{ $version->version_number }}</a>
@can('download',$document)<a class="btn primary" href="{{ route('kkr.word',[$document,$version]) }}">Unduh Word (.docx)</a>@endcan
@endsection
@section('content')
<p class="account-body-copy">{{ count($print['rows']) }} baris hasil reviu · {{ \App\Support\KkrPrint::LABEL }}. Isi di bawah sama dengan Word versi ini; pembagian halaman mengikuti aplikasi Word.</p>
@if(!$version->finalized_at)<p class="account-message">Versi ini belum final. Keputusan supervisi yang ditambahkan setelah preview dapat mengubah bagian supervisinya.</p>@else<p class="account-message success">Preview dan unduhan memakai arsip versi final yang sama.</p>@endif
<article class="form-panel kkr-print" aria-label="Isi dokumen Word">
    <header><h2>{{ $print['title'] }}</h2><p>{{ $print['institution'] }}</p><h3>{{ $print['subject'] }}</h3></header>
    <dl class="proposal-version-facts">@foreach($print['facts'] as [$label,$value])<div><dt>{{ $label }}</dt><dd class="verification-copy">{{ $value }}</dd></div>@endforeach</dl>
    <h3 class="proposal-followup">TUJUAN</h3><p class="verification-copy">{{ $print['purpose'] }}</p>
    <h3 class="proposal-followup">LANGKAH-LANGKAH KERJA</h3><p class="verification-copy">{{ $print['work_steps'] }}</p>
    <h3 class="proposal-followup">HASIL REVIU</h3>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Tabel delapan kolom hasil reviu; geser untuk melihat seluruh kolom"><table class="item-table kkr-print-table"><caption>{{ count($print['rows']) }} baris hasil reviu versi {{ $version->version_number }}</caption><thead><tr>@foreach($print['headers'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>@foreach($print['rows'] as $row)<tr>@foreach($row as $cell)<td class="verification-copy">{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table></div>
    <h3 class="proposal-followup">KESIMPULAN</h3><p class="verification-copy">{{ $print['conclusion'] }}</p>
    <h3 class="proposal-followup">REKOMENDASI</h3><p class="verification-copy">{{ $print['recommendation'] }}</p>
    @foreach(['Reviu Ketua Tim','Reviu Pengendali Teknis'] as $level)
        @php($decision=collect($print['supervision'])->firstWhere('level',$level))
        <h3 class="proposal-followup">{{ mb_strtoupper($level) }}</h3><p class="verification-copy">{{ $decision['text']??'Belum ada keputusan pada versi ini' }}</p>
    @endforeach
    <h3 class="proposal-followup">IDENTITAS TIM</h3>@foreach($print['team'] as $member)<p class="account-body-copy">{{ $member['role'] }}: {{ $member['name'] }}</p>@endforeach
</article>
@endsection
