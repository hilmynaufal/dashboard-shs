@extends('layouts.workspace')
@section('title','Impor Inspektorat')
@section('subtitle','Impor workbook usulan SHS/SBU yang siap direviu, lalu tugaskan pemeriksa.')
@section('content')
@php($tones=['preview'=>'warning','completed'=>'green','cancelled'=>'neutral'])
@php($labels=['preview'=>'Pratinjau','completed'=>'Tersimpan','cancelled'=>'Dibatalkan'])
<section class="form-panel catalog-form-panel"><h2>Unggah workbook usulan</h2>
<p class="account-body-copy">Format Inspektorat: lembar <strong>Data_SSH</strong> (SHS) dan <strong>Data_SBU</strong> (SBU) dengan kolom kode, uraian, spek, satuan, harga, rekening, kelompok, TKDN, Pengusul, dan Tipe. Lembar lain, termasuk lembar REVIU, tidak dibaca. Item yang tersimpan langsung berstatus <strong>Siap direviu</strong> tanpa pengiriman SKPD dan verifikasi BKAD; Ketua Tim kemudian menugaskan pemeriksa dari menu Penugasan reviu.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('inspectorate.imports.upload') }}" data-submit-form>@csrf
<div class="form-grid">
<div class="form-field"><label for="period">Periode <span class="required">*</span></label><select id="period" name="submission_period_id" required><option value="">Pilih periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected((string)\App\Support\FormInput::oldText('submission_period_id')===(string)$period->id)>{{ $period->budget_year }} · {{ $period->name }}</option>@endforeach</select><x-field-error field="submission_period_id"/></div>
<div class="form-field"><label for="xlsx-file">Berkas .xlsx <span class="required">*</span></label><input id="xlsx-file" type="file" name="file" accept=".xlsx" required data-dropzone="Berkas Excel .xlsx" data-max-bytes="{{ \App\Support\UploadLimits::kilobytes(5120)*1024 }}"><small>Maksimal {{ \App\Support\UploadLimits::label(5120) }}; 12.000 baris berisi per lembar. Kode barang dan rekening harus berformat teks.</small><x-field-error field="file"/></div>
</div>
<p class="form-bottom-note">Membaca berkas besar dapat memerlukan beberapa belas detik. Impor tidak langsung menambahkan item: Anda akan melihat pratinjau dan memetakan pengusul ke SKPD terlebih dahulu.</p>
<div class="form-actionbar"><button class="btn primary" type="submit" @disabled($periods->isEmpty())>Buka pratinjau<x-ui-icon name="arrow"/></button></div>
</form></section>
<section class="table-panel"><div class="table-toolbar"><h2>Riwayat impor</h2></div>
<div class="table-scroll"><table class="item-table"><thead><tr><th scope="col">Berkas</th><th scope="col">Periode</th><th scope="col">Status</th><th scope="col">Item</th><th scope="col">Oleh / waktu WIB</th><th scope="col"><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
@forelse($imports as $import)@php($summary=json_decode($import->summary,true))
<tr><td><strong>{{ $import->file_name }}</strong><small class="cell-detail">{{ $import->file_sha256 ? substr($import->file_sha256,0,12) : '' }}…</small></td>
<td>{{ $import->budget_year }} · {{ $import->period_name }}</td>
<td><span class="badge {{ $tones[$import->status] ?? 'neutral' }}">{{ $labels[$import->status] ?? $import->status }}</span></td>
<td>@if($import->status==='completed'){{ number_format($summary['items'] ?? 0,0,',','.') }}@else{{ number_format($summary['rows'] ?? 0,0,',','.') }} baris @endif</td>
<td>{{ $import->author_name }}<small class="cell-detail">{{ \Carbon\CarbonImmutable::parse($import->created_at,'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</small></td>
<td><a href="{{ route('inspectorate.imports.show',$import->id) }}">Buka<span class="sr-only"> {{ $import->file_name }}</span></a></td></tr>
@empty<tr><td colspan="6">Belum ada impor.</td></tr>@endforelse
</tbody></table></div><x-catalog-pagination :rows="$imports" noun="impor"/></section>
@endsection
