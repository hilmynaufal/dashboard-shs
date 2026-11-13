@extends('layouts.workspace')
@section('title','Penilaian ZI')
@section('subtitle',$unit.' · '.$a->year.' · '.$a->target.' · '.\App\Support\ZiInstrument::STATES[$a->state])
@section('actions')<a class="btn secondary" href="{{ route('zi.index') }}">Daftar penilaian</a>@endsection
@section('content')
<section class="table-panel">
    <div class="table-toolbar"><strong>Butir LKE</strong><span>Terisi {{ $latest->count() }} / {{ count($items) }} · Selesai dinilai {{ count($result['scores']) }} / {{ count($items) }} · Perlu perbaikan {{ $latest->where('action','returned')->count() }}</span></div>
    <div class="table-scroll"><table class="item-table"><thead><tr><th>Kelompok</th><th>Butir</th><th>Status</th><th>Skor TPI</th></tr></thead><tbody>
    @foreach($items as $id=>$question)
        @php($entry=$latest->get($id))
        <tr><td>{{ $question['group'] }}</td><td><a href="{{ route('zi.item',[$a->id,$id]) }}">{{ $question['title'] }}</a></td>
            <td>{{ $entry ? (\App\Support\ZiInstrument::ACTIONS[$entry->action] ?? 'Menunggu reviu') : ($a->state==='draft'?'Belum diajukan':'Belum diisi') }}</td><td>{{ isset($result['scores'][$id]) ? number_format($result['scores'][$id],3,',','.') : '—' }}</td></tr>
    @endforeach
    </tbody></table></div>
</section>
<section class="form-panel">
    <h2>{{ $a->state==='final'?'Rekap final area':'Rekap sementara area' }}</h2>
    <dl>@foreach($result['groups'] as $group=>$value)<dt>{{ $group }}</dt><dd>{{ $value===null?'Menunggu penilaian terkait':number_format($value,4,',','.') }}</dd>@endforeach</dl>
    <p>Skor Penyusunan Tim Kerja memakai rata-rata dua skor butir tim dan subtotal Rencana Pembangunan, dikalikan bobot 0,5.</p>
    @foreach($result['areas'] ?? [] as $area)
    <p><strong>{{ $area['name'] }} · {{ $area['total']===null?'—':number_format($area['total'],4,',','.') }} / {{ number_format($area['max'],1,',','.') }}</strong> · Minimum {{ number_format($area['minimum'],2,',','.') }} untuk {{ $a->target }}
    @if($area['complete']) · {{ $area['met']?'Memenuhi':'Di bawah ambang' }} · Selisih {{ number_format($area['total']-$area['minimum'],4,',','.') }}@endif</p>
    @endforeach
    <p><strong>Nilai total {{ $result['total']===null?'—':number_format($result['total'],4,',','.') }} / {{ number_format($result['max'] ?? 8,1,',','.') }}</strong>
    @if($result['complete']) · {{ $result['met']?'Semua area memenuhi ambang':'Ada area di bawah ambang' }}@endif</p>
    @if($access['submit'] && $a->state==='draft')
    <form method="post" action="{{ route('zi.submit',$a->id) }}">@csrf<input type="hidden" name="revision" value="{{ $a->revision }}"><button class="btn primary">Ajukan LKE ke TPI</button></form>
    @elseif($access['finalize'] && $a->state==='review')
    <form method="post" action="{{ route('zi.finalize',$a->id) }}">@csrf<input type="hidden" name="revision" value="{{ $a->revision }}"><button class="btn primary" @disabled(!$result['complete'])>Finalkan rekap</button></form>
    @endif
</section>
@endsection
