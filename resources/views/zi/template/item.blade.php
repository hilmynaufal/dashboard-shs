@extends('layouts.workspace')
@use('App\Support\ZiTemplate')
@php
    $new = $question === null;
    $formula = old('formula', $question['formula'] ?? 'ratio');
    $labelA = old('label_a', $question['fields'][0]['label'] ?? ($question['labels']['numerator'] ?? ''));
    $labelB = old('label_b', $question['fields'][1]['label'] ?? ($question['labels']['denominator'] ?? ''));
@endphp
@section('title',$new?'Tambah butir':'Ubah butir '.$item)
@section('subtitle','Template '.$row->version.' · draf')
@section('crumbs')<x-ui-icon name="arrow"/><a href="{{ route('zi.template.index') }}">Template instrumen ZI</a><x-ui-icon name="arrow"/><a href="{{ route('zi.template.show',$row->id) }}">{{ $row->version }}</a>@endsection
@section('actions')<a class="btn secondary" href="{{ route('zi.template.show',$row->id) }}">Kembali ke template</a>@endsection
@section('content')
<section class="form-panel">
    <h2>Redaksi dan kriteria</h2>
    <form method="post" action="{{ route('zi.template.update',$row->id) }}">@csrf
        <input type="hidden" name="revision" value="{{ $row->revision }}"><input type="hidden" name="op" value="item.save">
        @unless($new)<input type="hidden" name="original" value="{{ $item }}">@endunless
        <div class="form-grid">
            <label class="form-field">Nomor butir<input type="number" name="id" min="1" max="9999" required value="{{ old('id',$new?'':$item) }}" @readonly(!$new)><small>{{ $new?'Nomor baris pada LKE. Tidak dapat diubah setelah disimpan.':'Nomor tetap agar jawaban dan riwayat dapat ditelusuri.' }}</small></label>
            <label class="form-field">Kelompok<select name="placement" required><option value="">Pilih kelompok</option>
                @foreach($rules['areas'] as $area)<optgroup label="{{ $area['name'] }}">
                    @foreach($area['groups'] as $group)<option value="{{ $area['key'].'/'.$group['key'] }}" @selected(old('placement',$placement)===$area['key'].'/'.$group['key'])>{{ ZiTemplate::groupName($group,$items) }}</option>@endforeach
                </optgroup>@endforeach</select></label>
            <label class="form-field">Redaksi butir<textarea name="title" rows="3" required maxlength="1000">{{ old('title',$question['title'] ?? '') }}</textarea></label>
            <label class="form-field">Tipe jawaban<select name="type" required>@foreach(ZiTemplate::TYPES as $type)<option @selected(old('type',$question['type'] ?? '')===$type)>{{ $type }}</option>@endforeach</select><small>Skor: Ya/Tidak 1/0 · A–C 1/0,5/0 · A–D 1/0,67/0,33/0 · A–E 1/0,75/0,5/0,25/0 · % dari angka sumber.</small></label>
            <label class="form-field">Kriteria nilai<textarea name="rubric" rows="6" required maxlength="10000">{{ old('rubric',implode("\n",$question['rubric'] ?? [])) }}</textarea><small>Satu baris per pilihan, berurutan dari A. Untuk Ya/Tidak dan %, tulis penjelasan cara menilai.</small></label>
            <div>
                <label class="form-field">Rumus persentase<select name="formula">@foreach(ZiTemplate::FORMULAS as $key=>$label)<option value="{{ $key }}" @selected($formula===$key)>{{ $label }}</option>@endforeach</select><small>Hanya berlaku untuk tipe %. Penurunan: tahun ini 0 = 100%, kenaikan = 0%.</small></label>
                <label class="form-field">Label angka pertama<input name="label_a" maxlength="150" value="{{ $labelA }}" placeholder="Pembilang, atau jumlah tahun sebelumnya"></label>
                <label class="form-field">Label angka kedua<input name="label_b" maxlength="150" value="{{ $labelB }}" placeholder="Penyebut, atau jumlah tahun ini"></label>
            </div>
        </div>
        <button class="btn primary">{{ $new?'Tambah butir':'Simpan butir' }}</button>
    </form>
</section>
@unless($new)
<section class="form-panel">
    <h2>Hapus butir</h2>
    <p class="tpl-meta">Butir dihapus dari draf ini saja. Versi terbit dan penilaian yang berjalan tidak terpengaruh.</p>
    <form method="post" action="{{ route('zi.template.update',$row->id) }}" onsubmit="return confirm('Hapus butir {{ $item }} dari draf?')">@csrf
        <input type="hidden" name="revision" value="{{ $row->revision }}"><input type="hidden" name="op" value="item.delete"><input type="hidden" name="original" value="{{ $item }}">
        <button class="btn secondary danger">Hapus butir {{ $item }}</button>
    </form>
</section>
@endunless
@endsection
