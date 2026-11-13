@extends('layouts.workspace')
@section('title',$question['title'])
@section('subtitle',$question['group'].' · '.$a->target.' · '.$a->year)
@section('actions')<a class="btn secondary" href="{{ route('zi.show',$a->id) }}">Kembali ke LKE</a>@endsection
@section('content')
@php
    $draft=$access['write'] && $a->state==='draft';
    $revised=$access['write'] && $access['submit'] && $last?->action==='returned';
    $waiting=$a->state==='review' && in_array($last?->action,['submitted','revised'],true);
    $review=$waiting && $access['review'];
    $unitAnswer=$unitEntry ? json_decode($unitEntry->answer,true) : [];
@endphp
<section class="form-panel">
    <h2>Kriteria penilaian</h2>
    <ul>@foreach($question['rubric'] as $criterion)<li>{{ $criterion }}</li>@endforeach</ul>
    @if($question['type']==='%')<p>Capaian aktual ditampilkan penuh. Kontribusi terhadap skor dibatasi hingga 100%.</p>@endif
    @if($unitEntry)
        <p><strong>Jawaban unit</strong> · {{ \App\Support\ZiInstrument::display($unitAnswer) }}</p>
        <p>{{ $unitEntry->note }}</p>
        @if($unitEntry->evidence)<a class="btn secondary" href="{{ route('zi.evidence',[$a->id,$unitEntry->id]) }}">Unduh bukti versi ini</a>@endif
        @if($unitEntry->evidence_url)<a class="btn secondary" href="{{ $unitEntry->evidence_url }}" target="_blank" rel="noopener noreferrer">Buka tautan bukti (Google Drive)</a>@endif
    @endif
    @if($last?->action==='returned')<p><strong>Permintaan perbaikan TPI</strong> · {{ $last->note }}</p>@endif
</section>
@if($draft || $revised || $review)
<section class="form-panel"><h2>{{ $review?'Penilaian TPI':($revised?'Perbaiki butir':'Isi jawaban unit') }}</h2>
    <form method="post" enctype="multipart/form-data" action="{{ route('zi.save',[$a->id,$item]) }}">@csrf
        <input type="hidden" name="revision" value="{{ $last?->id ?? 0 }}">
        <input type="hidden" name="action" value="{{ $review?'rated':($draft?'draft':'revised') }}">
        <div class="form-grid">
        @if(isset($question['fields']))
            @foreach($question['fields'] as $field)
            <label class="form-field">{{ $field['label'] }}{{ $field['required']?'':' (opsional)' }}<input type="number" min="0" max="1000000000" name="{{ $field['name'] }}" @required($field['required']) value="{{ old($field['name'],$unitAnswer[$field['name']]??'') }}"></label>
            @endforeach
        @elseif($question['type']==='%')
            <label class="form-field">{{ $question['labels']['numerator'] ?? ($item===125?'Jumlah perubahan yang dibuat':'Jumlah perubahan terintegrasi') }}<input type="number" min="0" max="1000000000" name="numerator" required value="{{ old('numerator',$unitAnswer['numerator']??'') }}"></label>
            <label class="form-field">{{ $question['labels']['denominator'] ?? ($item===125?'Jumlah agen perubahan':'Jumlah perubahan yang dibuat') }}<input type="number" min="1" max="1000000000" name="denominator" required value="{{ old('denominator',$unitAnswer['denominator']??'') }}"></label>
        @else
            <label class="form-field">{{ $review?'Jawaban TPI':'Jawaban unit' }}<select name="choice" required><option value="">Pilih jawaban</option>
            @foreach(explode('/',$question['type']) as $value)<option @selected(old('choice',$review?'':($unitAnswer['choice']??''))===$value)>{{ $value }}</option>@endforeach</select></label>
        @endif
            <label class="form-field">{{ $review?'Catatan reviu (wajib jika jawaban berbeda)':'Penjelasan unit' }}<textarea name="note" rows="3" maxlength="10000">{{ old('note',$review?'':$unitEntry?->note) }}</textarea></label>
            @unless($review)
            @php($evidenceMode=\App\Support\FormInput::oldText('evidence_mode',$unitEntry?->evidence_url?'link':'file'))
            <div class="form-field"><label for="evidence-mode">Bukti dukung</label>
                <select id="evidence-mode" name="evidence_mode" data-choices="segmented" data-mode-switch>
                    <option value="file" @selected($evidenceMode==='file')>Unggah berkas</option>
                    <option value="link" @selected($evidenceMode==='link')>Tautan Google Drive</option>
                </select>
                <x-field-error field="evidence_mode"/>
            </div>
            <fieldset class="mode-panel" data-mode-panel="file" @if($evidenceMode!=='file') hidden disabled @endif>
                <label class="form-field" for="evidence-file">Bukti privat (PDF, JPG, PNG · maks. 5 MB)</label>
                <input id="evidence-file" type="file" name="file" accept=".pdf,.png,.jpg,.jpeg"><x-field-error field="file"/>
                <small>Bukti sebelumnya (berkas atau tautan) tetap terpakai bila tidak memilih berkas baru.</small>
            </fieldset>
            <fieldset class="mode-panel" data-mode-panel="link" @if($evidenceMode!=='link') hidden disabled @endif>
                <label class="form-field" for="evidence-url">Tautan Google Drive</label>
                <input id="evidence-url" type="url" name="evidence_url" maxlength="2000" inputmode="url" placeholder="https://drive.google.com/…" value="{{ \App\Support\FormInput::oldText('evidence_url',$unitEntry?->evidence_url) }}"><x-field-error field="evidence_url"/>
                <small>Pastikan akses berbagi tautan dapat dibuka TPI (mis. &ldquo;Siapa saja yang memiliki link&rdquo; atau dibagikan ke akun TPI). Sistem tidak memeriksa isi atau izin tautan.</small>
            </fieldset>
            @endunless
        </div>
        <button class="btn primary">{{ $review?'Selesaikan penilaian':($draft?'Simpan draf':'Kirim ulang butir') }}</button>
    </form>
</section>
@endif
@if($waiting && $access['return'])
<section class="form-panel"><form method="post" action="{{ route('zi.save',[$a->id,$item]) }}">@csrf
    <input type="hidden" name="revision" value="{{ $last->id }}"><input type="hidden" name="action" value="returned">
    <label class="form-field">Klarifikasi atau bukti yang diperlukan<textarea name="note" rows="2" required maxlength="10000"></textarea></label>
    <button class="btn secondary">Minta perbaikan</button>
</form></section>
@endif
<section class="table-panel"><div class="table-toolbar"><h2>Riwayat butir</h2></div>
<div class="table-scroll"><table class="item-table"><thead><tr><th>Versi / tindakan</th><th>Pelaku / waktu WIB</th><th>Jawaban dan catatan</th><th>Bukti</th></tr></thead><tbody>
@forelse($history as $event)<tr><td>#{{ $event->id }} · {{ \App\Support\ZiInstrument::ACTIONS[$event->action] ?? 'Diajukan ke TPI' }}</td><td>{{ $event->actor }}<small class="cell-detail">{{ \Illuminate\Support\Carbon::parse($event->created_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</small></td>
<td>{{ $event->answer ? \App\Support\ZiInstrument::display(json_decode($event->answer,true)) : '—' }}<small class="cell-detail">{{ $event->note }}</small></td>
<td>@if($event->evidence)<a href="{{ route('zi.evidence',[$a->id,$event->id]) }}">Unduh</a>@endif @if($event->evidence_url)<a href="{{ $event->evidence_url }}" target="_blank" rel="noopener noreferrer">Buka tautan</a>@endif</td></tr>
@empty<tr><td colspan="4">Belum ada kiriman yang dapat dilihat.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
