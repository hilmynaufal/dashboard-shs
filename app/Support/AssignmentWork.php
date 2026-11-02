<?php

namespace App\Support;

use App\Authorization\ResourceAccess;
use App\Models\{Proposal, ProposalItem, ReviewAssignment, User};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

final class AssignmentWork
{
    public static function token(ReviewAssignment $assignment): string
    {
        return RecordVersion::of([
            $assignment->getAttributes(),
            DB::table('assignment_members')->where('review_assignment_id', $assignment->id)->orderBy('id')->get()->map(fn ($row) => (array)$row)->all(),
            DB::table('assignment_items')->where('review_assignment_id', $assignment->id)->orderBy('id')->get()->map(fn ($row) => (array)$row)->all(),
            DB::table('assignment_transfers')->whereIn('assignment_item_id', DB::table('assignment_items')->select('id')->where('review_assignment_id', $assignment->id))->max('id'),
        ]);
    }

    public static function lock(User $actor, int $id, string $token): ReviewAssignment
    {
        $assignment = ReviewAssignment::query()->lockForUpdate()->findOrFail($id);
        Gate::forUser($actor->refresh())->authorize('manage', $assignment);
        if ($assignment->status !== 'active') {
            throw ValidationException::withMessages(['assignment' => 'Penugasan harus aktif untuk membagi atau mengalihkan item.']);
        }
        if (!hash_equals(self::token($assignment), $token)) {
            throw ValidationException::withMessages(['record_version' => 'Pembagian tugas berubah. Muat ulang halaman sebelum menyimpan.'])->status(409);
        }

        return $assignment;
    }

    public static function candidates(ReviewAssignment $assignment): Builder
    {
        return ItemVerification::readyForAssignment()->where('proposal.organization_id', $assignment->object_organization_id)
            ->where('proposal.submission_period_id', $assignment->submission_period_id);
    }

    public static function items(User $viewer, ReviewAssignment $assignment): Builder
    {
        $query = self::base()->where('assignment.id', $assignment->id);
        $access = app(ResourceAccess::class);
        if (!$access->membership($viewer, $assignment->id, 'team_lead') && !$access->membership($viewer, $assignment->id, 'technical_controller')) {
            $query->where('member.user_id', $viewer->id)->where('member.is_active', true);
            if (!$viewer->hasRole('examiner')) { $query->whereRaw('1 = 0'); }
        }

        return $query;
    }

    public static function examinerItems(User $viewer): Builder
    {
        return self::base()->where('member.user_id', $viewer->id)->where('member.is_active', true)
            ->whereIn('assignment.status', ['active', 'completed']);
    }

    private static function base(): Builder
    {
        return DB::table('assignment_items as scope')->join('review_assignments as assignment', 'assignment.id', '=', 'scope.review_assignment_id')
            ->join('proposal_items as item', 'item.id', '=', 'scope.proposal_item_id')
            ->join('proposals as proposal', 'proposal.id', '=', 'item.proposal_id')
            ->join('proposal_item_versions as version', 'version.id', '=', 'scope.initial_version_id')
            ->join('assignment_members as member', 'member.id', '=', 'scope.examiner_member_id')
            ->join('users as examiner', 'examiner.id', '=', 'member.user_id')
            ->where('member.role', 'examiner')
            ->select(['scope.*', 'item.status as item_status', 'proposal.id as proposal_id', 'proposal.number as proposal_number', 'proposal.type',
                'version.description', 'version.version_number', 'assignment.number as assignment_number',
                'assignment.title as assignment_title', 'assignment.status as assignment_status', 'examiner.name as examiner_name'])
            ->selectSub(DB::table('item_reviews')->selectRaw('COUNT(*)')->whereColumn('assignment_item_id', 'scope.id')->where('status', 'completed'), 'completed_reviews');
    }

    public static function allocate(User $actor, ReviewAssignment $assignment, array $selection): void
    {
        $ids = array_map('intval', array_keys($selection)); sort($ids);
        // Lock parent packages before item rows, matching the submission/verification workflow.
        $parents = ProposalItem::whereIn('id', $ids)->pluck('proposal_id')->unique()->sort()->values()->all();
        $packages = Proposal::whereIn('id', $parents)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $items = ProposalItem::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($items->count() !== count($ids)) { self::invalidItems(); }
        $members = [];
        foreach (array_unique(array_map('intval', array_values($selection))) as $memberId) {
            $members[$memberId] = AssignmentTeam::examiner($assignment, $memberId);
        }
        foreach ($ids as $id) {
            $item = $items[$id]; $package = $packages[$item->proposal_id];
            $versionId = self::candidates($assignment)->where('item.id', $id)->value('version.id');
            if (!$versionId) { self::invalidItems(); }
            DB::table('assignment_items')->insert([
                'review_assignment_id' => $assignment->id, 'proposal_item_id' => $id, 'initial_version_id' => $versionId,
                'examiner_member_id' => $members[(int)$selection[$id]]->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $item->status = 'assigned'; $item->lock_version++; $item->save();
            $package->status = 'review'; $package->save();
        }
        $assignment->touch();
        CatalogAudit::record($actor->id, 'assignment.items_allocated', 'review_assignment', $assignment->id, null, ['item_ids' => $ids]);
    }

    private static function invalidItems(): never
    {
        throw ValidationException::withMessages(['items' => 'Ada item yang tidak lagi siap, sudah ditugaskan, atau berada di luar SKPD/periode penugasan ini. Muat ulang daftar.']);
    }
}
