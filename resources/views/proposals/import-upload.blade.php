@extends('layouts.workspace')
@section('title','Impor Excel')
@section('subtitle',$proposal->number.' · Pilih lembar, periksa data, lalu simpan ke paket.')
@section('actions')<a class="btn secondary" href="{{ route('proposals.show',$proposal) }}">Kembali ke paket</a>@endsection
@section('content')
<section class="form-panel catalog-form-panel"><h2>Unggah berkas usulan</h2><p class="account-body-copy">Impor tidak langsung menambahkan item. Anda akan melihat pratinjau, pemetaan kolom, dan baris yang perlu diperbaiki.</p><form method="POST" enctype="multipart/form-data" action="{{ route('proposals.import.upload',$proposal) }}" data-submit-form>@csrf
<div class="form-field"><label for="xlsx-file">Berkas .xlsx</label><input id="xlsx-file" type="file" name="file" accept=".xlsx" required data-dropzone="Berkas Excel .xlsx" data-sheet-file data-max-bytes="{{ \App\Support\UploadLimits::kilobytes(5120)*1024 }}"><small>Maksimal {{ \App\Support\UploadLimits::label(5120) }}; 12.000 baris dan 60 kolom berisi data per lembar. Kode barang/rekening harus berformat teks.</small><x-field-error field="file"/></div>
<div class="form-field"><label for="sheet-index">Lembar Excel</label><select id="sheet-index" name="sheet_index"><option value="0">Lembar pertama</option></select><small id="sheet-status" role="status">Pilih berkas untuk memuat daftar lembar.</small><x-field-error field="sheet_index"/></div><p class="form-bottom-note">Kolom yang dipetakan: uraian, spesifikasi, kode barang, rekening, satuan dan harga. Beberapa rekening dipisahkan titik koma (;). Formula tidak dihitung; gunakan nilai atau koreksi pada pratinjau.</p><div class="form-actionbar"><button class="btn primary" type="submit">Buka pratinjau<x-ui-icon name="arrow"/></button></div>
</form></section>
@endsection
