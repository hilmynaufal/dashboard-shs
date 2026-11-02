<?php

namespace App\Http\Controllers;

use App\Models\{Proposal, ProposalItem};
use App\Support\{AccountValidation, ProposalHistory, ProposalItemInput};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;

class ProposalHistoryController extends Controller
{
    public function show(Request $request, Proposal $proposal, int $item): View
    {
        $item = ProposalItem::where('proposal_id', $proposal->id)->findOrFail($item);
        Gate::authorize('viewHistory', $item);
        $filters = $request->validate(['version' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'event_page' => ['nullable', 'integer', 'min:1', 'max:100000']], AccountValidation::MESSAGES);
        $query = ProposalHistory::versions($request->user(), $item);
        $selected = isset($filters['version']) ? (clone $query)->findOrFail($filters['version']) : (clone $query)->orderByDesc('version_number')->first();
        $versions = (clone $query)->withCasts(['created_at' => 'datetime'])->orderByDesc('version_number')->paginate(20)->withQueryString();
        $actors = DB::table('users')->whereIn('id', $versions->pluck('created_by'))->pluck('name', 'id');
        $previous = $selected ? (clone $query)->where('version_number', '<', $selected->version_number)->orderByDesc('version_number')->first() : null;

        return view('proposals.history', [
            'proposal' => $proposal, 'item' => $item, 'versions' => $versions, 'actors' => $actors, 'selected' => $selected, 'previous' => $previous,
            'fields' => $selected ? ProposalItemInput::fields($selected) : [], 'previousFields' => $previous ? ProposalItemInput::fields($previous) : [],
            'files' => $selected ? ProposalHistory::files($selected->id, $proposal->organization_id) : collect(),
            'previousFiles' => $previous ? ProposalHistory::files($previous->id, $proposal->organization_id) : collect(),
            'events' => $selected ? ProposalHistory::events($request->user(), $selected)->paginate(20, ['*'], 'event_page')->withQueryString() : null,
        ]);
    }
}
