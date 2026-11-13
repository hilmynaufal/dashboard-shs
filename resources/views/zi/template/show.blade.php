@extends('layouts.workspace')
@use('App\Support\ZiTemplate')
@php
    $draft = $row->status === 'draft';
    $n = fn($v) => ZiTemplate::number($v);
    $update = route('zi.template.update', $row->id);
@endphp
@section('title','Template '.$row->version)
@section('subtitle',($draft?'Draf · dapat disunting':'Terbit · terkunci').' · '.count($items).' butir · bobot '.$n(array_sum(array_column($rules['areas'],'max'))))
@section('crumbs')<x-ui-icon name="arrow"/><a href="{{ route('zi.template.index') }}">Template instrumen ZI</a>@endsection
@section('actions')<a class="btn secondary" href="{{ route('zi.template.index') }}">Semua versi</a>@endsection
@section('content')
<section class="form-panel">
    @if($draft)
        <h2>{{ $issues?'Belum siap terbit':'Siap terbit' }}</h2>
        @if($issues)
            <p class="tpl-meta">Selesaikan {{ count($issues) }} hal berikut sebelum menerbitkan.</p>
            <ul class="tpl-issues">@foreach($issues as $issue)<li>{{ $issue }}</li>@endforeach</ul>
        @else
            <p class="tpl-meta">Semua pemeriksaan lulus. Setelah terbit, versi ini terkunci dan dapat dipilih saat membuka penilaian.</p>
        @endif
        <div class="tpl-actions">
            <form method="post" action="{{ route('zi.template.publish',$row->id) }}">@csrf<input type="hidden" name="revision" value="{{ $row->revision }}"><button class="btn primary" @disabled((bool)$issues)>Terbitkan dan kunci</button></form>
            <form method="post" action="{{ route('zi.template.destroy',$row->id) }}" onsubmit="return confirm('Hapus draf {{ $row->version }}? Tindakan ini tidak dapat dibatalkan.')">@csrf<input type="hidden" name="revision" value="{{ $row->revision }}"><button class="btn secondary danger">Hapus draf</button></form>
        </div>
    @else
        <h2>Versi terkunci</h2>
        <p class="tpl-meta">Dipakai {{ $used }} penilaian. Untuk mengubah isi, buat draf baru dari versi ini di halaman Semua versi.</p>
    @endif
</section>

<section class="form-panel">
    <h2>Ambang kelulusan per area</h2>
    <p class="tpl-meta">Nilai tiap area dibandingkan dengan persentase ini dari bobot areanya.</p>
    @if($draft)
    <form method="post" action="{{ $update }}">@csrf<input type="hidden" name="revision" value="{{ $row->revision }}"><input type="hidden" name="op" value="thresholds">
        <div class="form-grid">
            <label class="form-field">Ambang WBK (%)<input name="wbk" inputmode="decimal" required value="{{ old('wbk',$n(($rules['thresholds']['WBK']??0)*100)) }}"></label>
            <label class="form-field">Ambang WBBM (%)<input name="wbbm" inputmode="decimal" required value="{{ old('wbbm',$n(($rules['thresholds']['WBBM']??0)*100)) }}"></label>
        </div>
        <button class="btn secondary">Simpan ambang</button>
    </form>
    @else
    <p>WBK {{ $n(($rules['thresholds']['WBK']??0)*100) }}% · WBBM {{ $n(($rules['thresholds']['WBBM']??0)*100) }}%</p>
    @endif
    <p class="tpl-meta">Skala nilai: @foreach($rules['scales'] as $type=>$scale){{ $type }} = {{ implode(' / ',array_map($n,$scale)) }}@if(!$loop->last) · @endif @endforeach. Persentase dibatasi {{ $n($rules['percent']['cap']*100) }}%. Skala mengikuti pedoman dan tidak diubah dari layar ini.</p>
</section>

@foreach($rules['areas'] as $area)
@php $sum = array_sum(array_column($area['groups'],'weight')); @endphp
<section class="table-panel">
    <div class="table-toolbar">
        <div><h2>{{ $area['name'] }}</h2><span class="tpl-meta">Bobot area {{ $n($area['max']) }} · jumlah bobot kelompok {{ $n($sum) }}@if(abs($sum-$area['max'])>1e-9) · <strong class="tpl-warn">belum sama</strong>@endif · minimum WBK {{ $n($area['max']*($rules['thresholds']['WBK']??0)) }}, WBBM {{ $n($area['max']*($rules['thresholds']['WBBM']??0)) }}</span></div>
        @if($draft)
        <details class="tpl-edit"><summary class="btn secondary">Ubah area</summary>
            <form method="post" action="{{ $update }}">@csrf<input type="hidden" name="revision" value="{{ $row->revision }}"><input type="hidden" name="area" value="{{ $area['key'] }}">
                <div class="form-grid">
                    <label class="form-field">Nama area<input name="name" required maxlength="150" value="{{ $area['name'] }}"></label>
                    <label class="form-field">Bobot area<input name="max" inputmode="decimal" required value="{{ $n($area['max']) }}"></label>
                </div>
                <div class="tpl-actions"><button class="btn secondary" name="op" value="area.save">Simpan area</button>
                @unless($area['groups'])<button class="btn secondary danger" name="op" value="area.delete" formnovalidate>Hapus area</button>@endunless</div>
            </form>
        </details>
        @endif
    </div>
    @foreach($area['groups'] as $group)
    <div class="tpl-group">
        <header>
            <div><h3>{{ ZiTemplate::groupName($group,$items) }}</h3><span class="tpl-meta">Bobot {{ $n($group['weight']) }} · {{ count($group['items']) }} butir @if($group['include']??[])· skor ikut memperhitungkan subtotal kelompok lain (rumus khusus)@endif</span></div>
            @if($draft)
            <div class="tpl-actions">
                <a class="btn secondary" href="{{ route('zi.template.item',[$row->id,0]) }}?kelompok={{ urlencode($area['key'].'/'.$group['key']) }}">Tambah butir</a>
                <details class="tpl-edit"><summary class="btn secondary">Ubah kelompok</summary>
                    <form method="post" action="{{ $update }}">@csrf<input type="hidden" name="revision" value="{{ $row->revision }}"><input type="hidden" name="area" value="{{ $area['key'] }}"><input type="hidden" name="group" value="{{ $group['key'] }}">
                        <div class="form-grid">
                            <label class="form-field">Nama kelompok<input name="name" required maxlength="200" value="{{ ZiTemplate::groupName($group,$items) }}"></label>
                            <label class="form-field">Bobot kelompok<input name="weight" inputmode="decimal" required value="{{ $n($group['weight']) }}"></label>
                        </div>
                        <div class="tpl-actions"><button class="btn secondary" name="op" value="group.save">Simpan kelompok</button>
                        @unless($group['items'])<button class="btn secondary danger" name="op" value="group.delete" formnovalidate>Hapus kelompok</button>@endunless</div>
                    </form>
                </details>
            </div>
            @endif
        </header>
        @if($group['items'])
        <div class="table-scroll"><table class="item-table tpl-items"><thead><tr><th>No.</th><th>Butir</th><th>Tipe jawaban</th><th>Kriteria</th><th></th></tr></thead><tbody>
        @foreach($group['items'] as $id)
            @php $q = $items[$id] ?? null; @endphp
            <tr><td>{{ $id }}</td><td>{{ $q['title'] ?? 'Butir tidak ditemukan' }}</td>
                <td>{{ $q['type'] ?? '—' }}@if(($q['type']??'')==='%')<small class="cell-detail">{{ ZiTemplate::FORMULAS[$q['formula'] ?? 'ratio'] }}</small>@endif</td>
                <td>{{ count($q['rubric'] ?? []) }} baris</td>
                <td>@if($draft && $q)<a href="{{ route('zi.template.item',[$row->id,$id]) }}">Ubah</a>@endif</td></tr>
        @endforeach
        </tbody></table></div>
        @else
        <p class="tpl-meta">Belum ada butir di kelompok ini.</p>
        @endif
    </div>
    @endforeach
    @if($draft)
    <div class="tpl-group">
        <details class="tpl-edit"><summary class="btn secondary">Tambah kelompok di area ini</summary>
            <form method="post" action="{{ $update }}">@csrf<input type="hidden" name="revision" value="{{ $row->revision }}"><input type="hidden" name="op" value="group.save"><input type="hidden" name="area" value="{{ $area['key'] }}">
                <div class="form-grid">
                    <label class="form-field">Nama kelompok<input name="name" required maxlength="200" placeholder="mis. Pemenuhan · Standar Pelayanan"></label>
                    <label class="form-field">Bobot kelompok<input name="weight" inputmode="decimal" required></label>
                </div>
                <button class="btn secondary">Tambah kelompok</button>
            </form>
        </details>
    </div>
    @endif
</section>
@endforeach

@if($draft)
<section class="form-panel">
    <h2>Tambah area</h2>
    <form method="post" action="{{ $update }}">@csrf<input type="hidden" name="revision" value="{{ $row->revision }}"><input type="hidden" name="op" value="area.save">
        <div class="form-grid">
            <label class="form-field">Nama area<input name="name" required maxlength="150" placeholder="mis. Penguatan Akuntabilitas"></label>
            <label class="form-field">Bobot area<input name="max" inputmode="decimal" required><small>Gabungan Pemenuhan dan Reform untuk area ini.</small></label>
        </div>
        <button class="btn secondary">Tambah area</button>
    </form>
</section>
@endif

<section class="table-panel"><div class="table-toolbar"><h2>Riwayat perubahan</h2></div>
<div class="table-scroll"><table class="item-table"><thead><tr><th>Waktu WIB</th><th>Perubahan</th><th>Pelaku</th></tr></thead><tbody>
@forelse($history as $event)<tr><td>{{ \Illuminate\Support\Carbon::parse($event->created_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td><td>{{ $event->detail }}</td><td>{{ $event->actor }}</td></tr>
@empty<tr><td colspan="3">Versi bawaan dari berkas instrumen; belum ada perubahan tercatat.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
