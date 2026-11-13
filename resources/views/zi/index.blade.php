@extends('layouts.workspace')
@section('title','Penilaian ZI')
@section('subtitle','Lembar Kerja Evaluasi Zona Integritas · Pemenuhan dan Reform')
@if($template)@section('actions')<a class="btn secondary" href="{{ route('zi.template.index') }}">Template instrumen</a>@endsection @endif
@section('content')
@if($manage)
<section class="form-panel">
    <h2>Buka penilaian</h2>
    <form method="post" action="{{ route('zi.store') }}">@csrf
        <div class="form-grid">
            <label class="form-field">Unit kerja<select name="organization_id" required data-combobox><option value="">Pilih unit</option>@foreach($units as $org)<option value="{{ $org->id }}" @selected(old('organization_id')==$org->id)>{{ $org->name }}</option>@endforeach</select></label>
            <label class="form-field">Tahun<input type="number" name="year" min="2000" max="2199" value="{{ old('year',now()->year) }}" required></label>
            <label class="form-field">Target predikat<select name="target"><option>WBK</option><option @selected(old('target')==='WBBM')>WBBM</option></select></label>
            <label class="form-field">Versi instrumen<select name="instrument">@foreach(array_reverse($versions) as $version)<option @selected(old('instrument')===$version)>{{ $version }}</option>@endforeach</select></label>
            <label class="form-field">Penilai TPI<select name="reviewer_id" required><option value="">Pilih Pemeriksa</option>@foreach($reviewers as $person)<option value="{{ $person->id }}" @selected(old('reviewer_id')==$person->id)>{{ $person->name }}</option>@endforeach</select></label>
        </div>
        <button class="btn primary">Buka penilaian</button>
    </form>
</section>
@endif
<section class="table-panel">
    <div class="table-toolbar"><h2>Daftar penilaian</h2></div>
    <div class="table-scroll"><table class="item-table"><thead><tr><th>Unit kerja</th><th>Tahun / target</th><th>TPI</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($list as $row)
        <tr><td>{{ $row->unit }}</td><td>{{ $row->year }} · {{ $row->target }}</td><td>{{ $row->reviewer }}</td>
            <td>{{ \App\Support\ZiInstrument::STATES[$row->state] }}</td><td>
            @if(($row->organization_id==auth()->user()->organization_id && $unit) || ($row->reviewer_id==auth()->id() && $tpi))
                <a href="{{ route('zi.show',$row->id) }}">Buka LKE</a>
            @endif</td></tr>
    @empty<tr><td colspan="5">Belum ada penilaian untuk lingkup akses Anda.</td></tr>@endforelse
    </tbody></table></div><x-catalog-pagination :rows="$list" noun="penilaian"/>
</section>
@endsection
