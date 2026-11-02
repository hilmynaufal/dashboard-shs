<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{ItemReview, ReviewAssignment, User};
use Illuminate\Support\Facades\DB;

class ItemReviewPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function create(User $user, ReviewAssignment $assignment, int $scopeId): bool
    {
        return $assignment->status === 'active' && $user->hasRole('examiner') && $user->hasPermission('reviews.write')
            && $user->hasPermission('proposals.view_assigned') && DB::table('assignment_items as scope')
                ->join('assignment_members as member', 'member.id', '=', 'scope.examiner_member_id')
                ->join('proposal_items as item', 'item.id', '=', 'scope.proposal_item_id')
                ->join('proposals as proposal', 'proposal.id', '=', 'item.proposal_id')
                ->join('proposal_item_versions as version', 'version.id', '=', 'scope.initial_version_id')
                ->where('scope.id', $scopeId)->where('scope.review_assignment_id', $assignment->id)
                ->where('member.review_assignment_id', $assignment->id)->where('member.user_id', $user->id)
                ->where('member.role', 'examiner')->where('member.is_active', true)->whereNotNull('version.submitted_at')
                ->whereIn('item.status', ['assigned', 'reviewing'])->where('proposal.status', 'review')->exists();
    }

    public function view(User $user, ItemReview $review): bool
    {
        return $this->access->review($user, $review);
    }

    public function update(User $user, ItemReview $review): bool
    {
        return $this->access->writeReview($user, $review);
    }
}
