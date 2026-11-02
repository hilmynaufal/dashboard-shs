@extends('layouts.workspace')
@section('title','Alihkan pemeriksa utama')
@section('subtitle',$assignment->number.' · '.$item->description)
@section('actions')<a class="btn secondary" href="{{ route('assignments.show',$assignment) }}">Kembali ke penugasan</a>@endsection
@section('content')
<section class="form-panel catalog-form-panel"><h2>{{ $item->description }}</h2><p class="account-body-copy">Pemeriksa saat ini: <strong>{{ $item->examiner_name }}</strong>. Cakupan item dan versi yang ditugaskan tetap dipertahankan.</p>
<form method="POST" action="{{ route('assignments.transfer',[$assignment,$item->id]) }}" data-submit-form>@csrf<input type="hidden" name="record_version" value="{{ \App\Support\FormInput::oldText('record_version',$recordVersion) }}">
<div class="form-field"><label for="examiner">Pemeriksa tujuan <span class="required">*</span></label><select id="examiner" name="examiner_member_id" required><option value="">Pilih anggota tim</option>@foreach($examiners as $examiner)@if((int)$examiner->id!==(int)$item->examiner_member_id)<option value="{{ $examiner->id }}" @selected((string)\App\Support\FormInput::oldText('examiner_member_id')===(string)$examiner->id)>{{ $examiner->name }}</option>@endif @endforeach</select><x-field-error field="examiner_member_id"/></div>
<div class="form-field"><label for="reason">Alasan pengalihan <span class="required">*</span></label><textarea id="reason" name="reason" rows="4" maxlength="5000" required>{{ \App\Support\FormInput::oldText('reason') }}</textarea><x-field-error field="reason"/></div>
<p class="form-bottom-note">Pemeriksa sebelumnya tidak lagi memiliki akses melalui tugas ini setelah pengalihan. Riwayat pengalihan tetap tersimpan.</p>
@if($errors->has('record_version'))<a class="btn secondary access-reload" href="{{ route('assignments.transfer.form',[$assignment,$item->id]) }}">Muat ulang pembagian tugas</a>@endif
<div class="form-actionbar"><button class="btn primary" type="submit" @disabled($examiners->where('id','!=',$item->examiner_member_id)->isEmpty())>Simpan pengalihan</button></div>
</form></section>
@endsection
