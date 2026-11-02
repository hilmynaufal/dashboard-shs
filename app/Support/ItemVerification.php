<?php

namespace App\Support;

use App\Models\{Proposal, ProposalItem, User};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\{DB, Gate};

final class ItemVerification
{
    public static function item(Proposal $proposal, int $id): ProposalItem
    {
        return ProposalItem::where('proposal_id', $proposal->id)->findOrFail($id);
    }

    /** All writes use parent -> item -> period/references lock order. */
    public static function lock(User $user, int $itemId, string $ability, ?int $proposalId = null): array
    {
        $initial = ProposalItem::findOrFail($itemId);
        abort_if($proposalId !== null && (int) $initial->proposal_id !== $proposalId, 404);
        $proposal = Proposal::query()->lockForUpdate()->findOrFail($initial->proposal_id);
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->lockForUpdate()->findOrFail($itemId);
        Gate::forUser($user->refresh())->authorize($ability, $item);

        return [$proposal, $item];
    }

    public static function latest(int $itemId, bool $submittedOnly = false): object
    {
        $version = DB::table('proposal_item_versions')->where('proposal_item_id', $itemId)
            ->when($submittedOnly, fn ($q) => $q->whereNotNull('submitted_at'))->orderByDesc('version_number')->first();
        abort_unless($version, 404);

        return $version;
    }

    public static function history(int $itemId)
    {
        return DB::table('verification_results as result')->join('proposal_item_versions as version', 'version.id', '=', 'result.item_version_id')
            ->join('users as verifier', 'verifier.id', '=', 'result.verified_by')->where('version.proposal_item_id', $itemId)
            ->orderByDesc('result.id')->select(['result.*', 'version.version_number', 'verifier.name as verifier_name'])->get();
    }

    public static function token(ProposalItem $item): string
    {
        return RecordVersion::of([$item->getAttributes(), (array) self::latest($item->id),
            DB::table('verification_results')->whereIn('item_version_id', DB::table('proposal_item_versions')->select('id')->where('proposal_item_id', $item->id))->max('id')]);
    }

    /** K-13 must select from this query; a different item's return never blocks this item. */
    public static function readyForAssignment(): Builder
    {
        return DB::table('proposal_items as item')->join('proposals as proposal', 'proposal.id', '=', 'item.proposal_id')
            ->join('proposal_item_versions as version', function ($join): void {
                $join->on('version.proposal_item_id', '=', 'item.id')->where('version.id', '=', function ($q): void {
                    $q->selectRaw('MAX(latest.id)')->from('proposal_item_versions as latest')->whereColumn('latest.proposal_item_id', 'item.id');
                });
            })->where('item.status', 'ready')->whereNotNull('version.submitted_at')
            ->whereNotExists(function ($q): void {
                $q->from('assignment_items as allocated')->join('review_assignments as assignment', 'assignment.id', '=', 'allocated.review_assignment_id')
                    ->whereColumn('allocated.proposal_item_id', 'item.id')->where('assignment.status', 'active');
            })
            ->whereIn('proposal.status', ['submitted', 'verification', 'review'])
            ->whereExists(function ($q): void {
                $q->from('verification_results as result')->whereColumn('result.item_version_id', 'version.id')->where('result.result', 'ready')
                    ->where('result.id', '=', function ($latest): void {
                        $latest->selectRaw('MAX(decision.id)')->from('verification_results as decision')->whereColumn('decision.item_version_id', 'version.id');
                    });
            });
    }
}
