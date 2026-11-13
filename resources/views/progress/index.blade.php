@extends('layouts.workspace')
@section('title','Pemantauan')
@section('subtitle','Progres pengajuan, reviu, dan tindak lanjut sesuai akses Anda.')
@section('actions')<a class="btn secondary" href="{{ route('progress.index',array_filter(['view'=>$view,'status'=>$status,'metric'=>$metric])) }}"><x-ui-icon name="refresh"/>Muat ulang</a>@endsection
@section('content')
@php
    $views=\App\Support\ProgressBoard::VIEWS;
    if(!$canKkr){unset($views['kkr']);}
    $selectedLabel=$metric?(\App\Support\ProgressBoard::METRICS[$metric]):($status==='all'?'Semua status':$labels[$status]);
@endphp
<p class="account-body-copy">Dimuat {{ $refreshedAt->format('d/m/Y H:i:s') }} WIB. Angka dihitung ulang saat halaman dimuat; paket, item, dan catatan dihitung terpisah.</p>
<nav class="progress-tabs" aria-label="Jenis data pemantauan">
@foreach($views as $key=>$name)<a class="btn {{ $view===$key?'primary':'secondary' }}" href="{{ route('progress.index',['view'=>$key]) }}" @if($view===$key) aria-current="page" @endif>{{ $name }} <span>{{ array_sum($groups[$key]??[]) }}</span></a>@endforeach
</nav>
@if($canReview)
<section class="progress-indicators" aria-label="Indikator hasil dan tindak lanjut">
@foreach(\App\Support\ProgressBoard::METRICS as $key=>$name)<a class="progress-indicator {{ $metric===$key?'is-current':'' }}" href="{{ route('progress.index',['view'=>'items','metric'=>$key]) }}" @if($metric===$key) aria-current="page" @endif><span>{{ $name }}</span><strong>{{ $metrics[$key] }}</strong></a>@endforeach
<a class="progress-indicator {{ $view==='notes' && $status==='open'?'is-current':'' }}" href="{{ route('progress.index',['view'=>'notes','status'=>'open']) }}" @if($view==='notes' && $status==='open') aria-current="page" @endif><span>Catatan terbuka</span><strong>{{ $openNotes }}</strong></a>
</section>
<p class="account-body-copy">Reviu selesai menghitung reviu terbaru yang dapat Anda lihat per item. Hasil sesuai mensyaratkan reviu selesai, setidaknya satu aspek sesuai, dan aspek lainnya sesuai atau tidak berlaku dengan alasan. Catatan terbuka dapat tetap ada pada item yang selesai diperiksa.</p>
@else
<p class="account-body-copy">{{ $openNotes }} catatan terbuka dapat Anda lihat. <a href="{{ route('progress.index',['view'=>'notes','status'=>'open']) }}">Buka rinciannya</a>. Hasil substantif reviu dan KKR ditampilkan sesuai kewenangan tim.</p>
@endif
<section class="form-panel progress-results">
    <div class="section-heading"><div><h2>{{ $views[$view] }}</h2><p>{{ $selectedLabel }} · {{ $rows->total() }} {{ $view==='packages'?'paket':($view==='items'?'item':($view==='notes'?'catatan':'KKR')) }}</p></div></div>
    <nav class="progress-statuses" aria-label="Status {{ $views[$view] }}">
        <a class="btn {{ $status==='all' && !$metric?'primary':'secondary' }}" href="{{ route('progress.index',['view'=>$view]) }}" @if($status==='all' && !$metric) aria-current="page" @endif>Semua {{ array_sum($groups[$view]??[]) }}</a>
        @foreach($labels as $key=>$name)@if($key!=='open')<a class="btn {{ $status===$key?'primary':'secondary' }}" href="{{ route('progress.index',['view'=>$view,'status'=>$key]) }}" @if($status===$key) aria-current="page" @endif>{{ $name }} {{ $groups[$view][$key]??0 }}</a>@endif @endforeach
    </nav>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Rincian {{ $views[$view] }}"><table class="item-table progress-table"><thead><tr><th scope="col">{{ $view==='notes'?'Catatan':'Identitas' }}</th><th scope="col">{{ $view==='notes'?'Item / paket':'Uraian' }}</th><th scope="col">Status</th>@if($view==='packages')<th scope="col">Item terlihat</th>@endif<th scope="col">Tindakan</th></tr></thead><tbody>
    @forelse($rows as $row)<tr>
        <td>{{ $view==='items'?'Item #'.$row->id:($view==='notes'?$row->category:($row->number??'Nomor belum tersedia')) }}</td>
        <td>{{ $row->title??'Data versi belum tersedia' }}@if(in_array($view,['items','notes'],true))<small class="cell-detail">{{ $row->number }}</small>@endif</td>
        <td><span class="badge {{ \App\Support\StatusTone::of($row->status) }}">{{ $labels[$row->status]??$row->status }}</span></td>
        @if($view==='packages')<td>{{ $row->item_count }}</td>@endif
        <td><a class="btn icon-text" href="{{ match($view){'packages'=>route('proposals.show',$row->id),'notes'=>route('notes.show',$row->id),'kkr'=>route('kkr.show',$row->id),default=>$metric?route('reviews.show',$row->review_id):($row->version_id?route('proposals.items.version',[$row->proposal_id,$row->id,$row->version_id]):route('proposals.show',$row->proposal_id))} }}">{{ $metric?'Buka reviu':'Buka' }} <span class="sr-only">{{ $view==='notes'?$row->category:($row->title??'rincian') }}</span></a></td>
    </tr>@empty<tr><td colspan="{{ $view==='packages'?5:4 }}">Tidak ada {{ mb_strtolower($views[$view]) }} pada pilihan ini dalam lingkup akses Anda.</td></tr>@endforelse
    </tbody></table></div><x-catalog-pagination :rows="$rows" :noun="$view==='packages'?'paket':($view==='items'?'item':($view==='notes'?'catatan':'KKR'))"/>
</section>
@endsection
