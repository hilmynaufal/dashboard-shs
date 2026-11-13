@extends('layouts.workspace')
@section('title','Impor Inspektorat')
@section('subtitle',$import->file_name.' · '.$period->budget_year.' '.$period->name)
@section('actions')<a class="btn secondary" href="{{ route('inspectorate.imports.index') }}">Kembali ke daftar impor</a>@endsection
@section('content')
@php($n=fn($value)=>number_format((int)$value,0,',','.'))
@if(!$preview)
<section class="form-panel">
    <h2>{{ $import->status==='completed' ? 'Impor tersimpan' : 'Impor dibatalkan' }}</h2>
    @if($import->status==='completed')
    <p class="account-body-copy">{{ $n($summary['items'] ?? 0) }} item berstatus <strong>Siap direviu</strong> pada {{ count($summary['packages'] ?? []) }} paket. @if(($summary['excluded'] ?? 0)>0){{ $n($summary['excluded']) }} baris bermasalah tidak diimpor. @endif Oleh {{ $author }} · {{ \Carbon\CarbonImmutable::parse($import->completed_at,'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB.</p>
    @php($new=$summary['new_references'] ?? [])
    @if(array_sum($new) || !empty($summary['new_organizations']))<p class="account-body-copy">Ditambahkan ke referensi: {{ $n($new['classifications'] ?? 0) }} kode barang, {{ $n($new['units'] ?? 0) }} satuan, {{ $n($new['accounts'] ?? 0) }} rekening, {{ count($summary['new_organizations'] ?? []) }} SKPD.</p>@endif
    @else<p class="account-body-copy">Pratinjau dibatalkan dan data sementara dihapus. Tidak ada item yang masuk.</p>@endif
</section>
@if($import->status==='completed')
<section class="table-panel"><div class="table-toolbar"><h2>Paket hasil impor</h2><span class="account-body-copy">@can('assignments.manage')Buat penugasan per SKPD, lalu pilih pemeriksa untuk itemnya.@else Penugasan dilakukan oleh Ketua Tim.@endcan</span></div>
<div class="table-scroll"><table class="item-table"><thead><tr><th scope="col">SKPD</th><th scope="col">Jenis</th><th scope="col">Nomor paket</th><th scope="col" class="number">Item siap reviu</th><th scope="col"><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
@foreach($summary['packages'] ?? [] as $package)<tr><td>{{ $package['organization'] }}</td><td>{{ $package['type'] }}</td><td>{{ $package['number'] }}</td><td class="number">{{ $n($package['items']) }}</td>
<td>@can('assignments.manage')<a href="{{ route('assignments.create',['periode'=>$import->submission_period_id,'skpd'=>$package['organization_id']]) }}">Buat penugasan<span class="sr-only"> {{ $package['organization'] }}</span></a>@endcan</td></tr>@endforeach
</tbody></table></div></section>
@endif
@else
<section class="form-panel">
    <h2>Ringkasan pratinjau</h2>
    <dl class="proposal-version-facts">
        <div><dt>Lembar dibaca</dt><dd>{{ implode(', ',$summary['sheets'] ?? []) }}</dd></div>
        <div><dt>Baris item</dt><dd>{{ $n($summary['rows'] ?? 0) }}</dd></div>
        <div><dt>Baris kelompok dilewati</dt><dd>{{ $n($summary['groups'] ?? 0) }}</dd></div>
        <div><dt>Baris bermasalah</dt><dd>{{ $n($summary['invalid'] ?? 0) }}</dd></div>
    </dl>
    <p class="account-body-copy">Baris dengan beberapa pengusul (dipisah titik koma) dibuat satu item per pengusul; ASB dilepas bila ada pengusul lain. Baris bermasalah tidak ikut diimpor dan tercantum di bawah. Pratinjau tidak menambahkan item sebelum Anda menyimpan impor.</p>
</section>
<form method="POST" action="{{ route('inspectorate.imports.update',$import->id) }}" data-submit-form>@csrf
<input type="hidden" name="record_version" value="{{ \App\Support\FormInput::oldText('record_version',$recordVersion) }}">
<section class="table-panel"><div class="table-toolbar"><h2>Pemetaan pengusul ke SKPD</h2><span class="account-body-copy">Pengusul yang cocok dengan kode atau nama SKPD dipilih otomatis; sisanya akan dibuat sebagai SKPD baru kecuali Anda memilih SKPD yang ada.</span></div>
<div class="table-scroll" tabindex="0" role="region" aria-label="Pemetaan pengusul"><table class="item-table"><thead><tr><th scope="col">Pengusul pada berkas</th><th scope="col" class="number">SHS</th><th scope="col" class="number">SBU</th><th scope="col" class="number">Bermasalah</th><th scope="col">SKPD tujuan</th></tr></thead><tbody>
@php($index=0)
@foreach($owners as $owner=>$counts)@php($entry=$mapping[$owner] ?? ['new'=>true])@php($choice=\App\Support\FormInput::oldText('owners.'.$index,isset($entry['new'])?'new':'org:'.$entry['org_id']))
<tr><th scope="row">{{ \App\Support\InspectorateImport::label($owner) }}<input type="hidden" name="owner_keys[{{ $index }}]" value="{{ $owner }}"></th>
<td class="number">{{ $n($counts['SHS']) }}</td><td class="number">{{ $n($counts['SBU']) }}</td><td class="number">{{ $n($counts['invalid']) }}</td>
<td><div class="form-field assignment-row-field"><label class="sr-only" for="owner-{{ $index }}">SKPD untuk {{ \App\Support\InspectorateImport::label($owner) }}</label><select id="owner-{{ $index }}" name="owners[{{ $index }}]" required><option value="new" @selected($choice==='new')>Buat SKPD baru: {{ \App\Support\InspectorateImport::label($owner) }}</option>@foreach($organizations as $org)<option value="org:{{ $org->id }}" @selected($choice==='org:'.$org->id)>{{ $org->name }} ({{ $org->code }})</option>@endforeach</select><x-field-error :field="'owners.'.$index"/></div></td></tr>
@php($index++)@endforeach
</tbody></table></div></section>
@php($missing=$references['missing'])@php($new=array_sum(array_map('count',$missing)))
<section class="form-panel">
    <h2>Referensi</h2>
    @if($references['inactive'])
        <div class="review-stored-status"><strong>Referensi nonaktif dipakai berkas</strong><ul>@foreach(array_slice(array_unique($references['inactive']),0,10) as $line)<li>{{ $line }}</li>@endforeach</ul><p class="account-body-copy">Aktifkan di menu Referensi sebelum menyimpan impor.</p></div>
    @endif
    @if($new)
        <p class="account-body-copy">Berkas memakai referensi yang belum ada: <strong>{{ $n(count($missing['classifications'])) }}</strong> kode barang, <strong>{{ $n(count($missing['units'])) }}</strong> satuan, <strong>{{ $n(count($missing['accounts'])) }}</strong> rekening. Referensi ini akan ditambahkan saat impor disimpan.</p>
        <label class="form-field checkbox-field"><input type="checkbox" name="confirm_references" value="1" @checked(old('confirm_references'))> Saya mengonfirmasi penambahan referensi baru tersebut.</label><x-field-error field="confirm_references"/>
    @else
        <p class="account-body-copy">Semua kode barang, satuan, dan rekening pada berkas sudah ada di referensi.</p>
    @endif
</section>
<div class="form-actionbar"><button class="btn secondary" type="submit" name="intent" value="save">Simpan pemetaan</button><button class="btn primary" type="submit" name="intent" value="commit" @disabled($references['inactive'])>Simpan impor ({{ $n(array_sum(array_map(fn($c)=>$c['SHS']+$c['SBU'],$owners))) }} item)</button></div>
</form>
<form method="POST" action="{{ route('inspectorate.imports.cancel',$import->id) }}" data-submit-form>@csrf<div class="form-actionbar"><button class="btn secondary" type="submit">Batalkan pratinjau</button></div></form>
<section class="table-panel proposal-followup"><div class="table-toolbar"><h2>Baris bermasalah</h2><span class="account-body-copy">Perbaiki di berkas lalu unggah ulang bila baris ini perlu diimpor.</span></div>
<div class="table-scroll"><table class="item-table"><thead><tr><th scope="col">Lembar / baris</th><th scope="col">Pengusul</th><th scope="col">Kode</th><th scope="col">Uraian</th><th scope="col">Masalah</th></tr></thead><tbody>
@forelse($problems as $row)@php($data=json_decode($row->data,true))<tr><td>{{ $row->type }} · {{ $row->row_number }}</td><td>{{ \App\Support\InspectorateImport::label($row->owner) }}</td><td>{{ $data['code'] }}</td><td>{{ $data['name'] }}</td><td>{{ $row->error }}</td></tr>
@empty<tr><td colspan="5">Tidak ada baris bermasalah.</td></tr>@endforelse
</tbody></table></div><x-catalog-pagination :rows="$problems" noun="baris"/></section>
@endif
@endsection
