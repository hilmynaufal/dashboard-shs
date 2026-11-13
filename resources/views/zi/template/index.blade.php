@extends('layouts.workspace')
@section('title','Template instrumen ZI')
@section('subtitle','Area, kelompok, butir, bobot, dan ambang WBK/WBBM per versi')
@section('crumbs')<x-ui-icon name="arrow"/><a href="{{ route('zi.index') }}">Penilaian ZI</a>@endsection
@section('actions')<a class="btn secondary" href="{{ route('zi.index') }}">Daftar penilaian</a>@endsection
@section('content')
<section class="form-panel">
    <h2>Buat draf versi baru</h2>
    <p class="tpl-meta">Draf menyalin seluruh isi versi sumber. Versi terbit tidak berubah, dan penilaian yang sudah berjalan tetap memakai versinya.</p>
    <form method="post" action="{{ route('zi.template.store') }}">@csrf
        <div class="form-grid">
            <label class="form-field">Salin dari versi<select name="source" required>@foreach($list as $row)<option value="{{ $row->version }}" @selected(old('source')===$row->version)>{{ $row->version }} · {{ $row->status==='published'?'Terbit':'Draf' }}</option>@endforeach</select></label>
            <label class="form-field">Kode versi baru<input name="version" required maxlength="40" pattern="[a-z0-9][a-z0-9-]{2,39}" placeholder="mis. zi-2026" value="{{ old('version') }}"><small>Huruf kecil, angka, dan tanda hubung. Kode tidak dapat diubah setelah dibuat.</small></label>
        </div>
        <button class="btn primary">Buat draf</button>
    </form>
</section>
<section class="table-panel">
    <div class="table-toolbar"><h2>Versi instrumen</h2></div>
    <div class="table-scroll"><table class="item-table"><thead><tr><th>Versi</th><th>Area</th><th>Butir / bobot</th><th>Status</th><th>Dipakai</th><th></th></tr></thead><tbody>
    @foreach($list as $row)
        <tr><td><strong>{{ $row->version }}</strong>@if($row->source)<small class="cell-detail">Disalin dari {{ $row->source }}</small>@endif</td>
            <td>{{ implode(', ',$row->areas) ?: '—' }}</td>
            <td>{{ $row->item_count }} butir · bobot {{ \App\Support\ZiTemplate::number($row->weight) }}</td>
            <td>{{ $row->status==='published'?'Terbit · terkunci':'Draf' }}</td>
            <td>{{ $row->used }} penilaian</td>
            <td><a href="{{ route('zi.template.show',$row->id) }}">{{ $row->status==='draft'?'Sunting':'Lihat' }}</a></td></tr>
    @endforeach
    </tbody></table></div>
</section>
@endsection
