@extends('layouts.workspace')
@section('title','Pekerjaan pemeriksa')
@section('subtitle','Item yang saat ini ditugaskan kepada Anda beserta identitas penugasannya.')
@section('content')
<section class="table-panel">@include('assignments.filters',['allowAssignmentSort'=>true])
@if($items->count())<div class="table-scroll"><table class="item-table"><thead><tr><th>Item dan paket</th><th>Penugasan</th><th>Versi ditugaskan</th><th>Status</th><th><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
@foreach($items as $item)<tr><td><strong>{{ $item->description }}</strong><small class="cell-detail">{{ $item->proposal_number }}</small></td><td><a href="{{ route('assignments.show',$item->review_assignment_id) }}">{{ $item->assignment_number }}</a><small class="cell-detail">{{ $item->assignment_title }}</small></td><td>{{ $item->version_number }}</td><td><span class="badge {{ \App\Support\StatusTone::of($item->item_status) }}">{{ \App\Models\ProposalItem::LABELS[$item->item_status] }}</span></td><td><a class="btn secondary" href="{{ route('proposals.items.version',[$item->proposal_id,$item->proposal_item_id,$item->initial_version_id]) }}">Lihat data & bukti</a><a class="btn icon-text" href="{{ route('reviews.desk',[$item->review_assignment_id,$item->id]) }}">Buka reviu</a></td></tr>@endforeach
</tbody></table></div>@else<div class="empty-state"><h3>Belum ada pekerjaan yang sesuai</h3><p>Ubah pencarian atau tunggu pembagian tugas dari Ketua Tim.</p></div>@endif
<x-catalog-pagination :rows="$items" noun="item"/></section>
@endsection
