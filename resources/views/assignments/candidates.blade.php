@extends('layouts.workspace')
@section('title','Pilih item siap reviu')
@section('subtitle',$assignment->number.' · '.$assignment->title)
@section('actions')<a class="btn secondary" href="{{ route('assignments.show',$assignment) }}">Kembali ke penugasan</a>@endsection
@section('content')
<p class="account-body-copy">Hanya item siap reviu pada SKPD dan periode penugasan ini yang ditampilkan. Pilih pemeriksa untuk item yang akan dibagikan; biarkan pilihan lainnya kosong. Simpan pilihan sebelum berpindah halaman atau mengubah pencarian.</p>
<section class="table-panel">@include('assignments.filters')
@if($items->count())<form method="POST" action="{{ route('assignments.allocate',$assignment) }}" data-submit-form>@csrf<input type="hidden" name="record_version" value="{{ \App\Support\FormInput::oldText('record_version',$recordVersion) }}"><div class="table-scroll"><table class="item-table"><thead><tr><th>Item</th><th>Paket</th><th>Jenis</th><th>Versi siap</th><th>Pemeriksa tujuan</th></tr></thead><tbody>
@foreach($items as $item)<tr><td><strong>{{ $item->description }}</strong><small class="cell-detail">ID {{ $item->id }}</small></td><td>{{ $item->proposal_number }}</td><td>{{ $item->type }}</td><td>{{ $item->version_number }}</td><td><div class="form-field assignment-row-field"><label class="sr-only" for="examiner-{{ $item->id }}">Pemeriksa untuk {{ $item->description }}</label><select id="examiner-{{ $item->id }}" name="items[{{ $item->id }}]"><option value="">Tidak dipilih</option>@foreach($examiners as $examiner)<option value="{{ $examiner->id }}" @selected((string)\App\Support\FormInput::oldText('items.'.$item->id)===(string)$examiner->id)>{{ $examiner->name }}</option>@endforeach</select><x-field-error :field="'items.'.$item->id"/></div></td></tr>@endforeach
</tbody></table></div><div class="assignment-savebar"><button class="btn primary" type="submit" @disabled($examiners->isEmpty())>Tugaskan item yang dipilih</button><small>Pilihan hanya untuk halaman ini.</small></div></form>@else<div class="empty-state"><h3>Belum ada item siap yang sesuai</h3><p>Ubah pencarian atau tunggu hasil kelengkapan BKAD. Item yang sudah ditugaskan tidak muncul kembali.</p></div>@endif
<x-catalog-pagination :rows="$items" noun="item"/></section>
@if($errors->has('record_version'))<a class="btn secondary access-reload" href="{{ route('assignments.candidates',$assignment) }}">Muat ulang daftar</a>@endif
@endsection
