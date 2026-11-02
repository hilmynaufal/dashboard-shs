<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{ItemReview, Proposal, ProposalItem, ReviewNote, User};
use Illuminate\Support\Facades\DB;

class ReviewNotePolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, ReviewNote $note): bool
    {
        return $this->access->note($user, $note);
    }

    public function respond(User $user, ReviewNote $note): bool
    {
        return $user->hasPermission('notes.respond_own') && $note->status === 'sent' &&
            $this->access->note($user, $note);
    }

    public function send(User $user, ReviewNote $note): bool
    {
        if (! in_array($note->status, ['internal', 'awaiting_lead'], true) || ! $this->access->note($user, $note)) {
            return false;
        }
        if ($note->type === 'administrative') {
            return $user->hasPermission('notes.administrative') && $this->access->note($user, $note);
        }
        $review = $note->item_review_id ? ItemReview::find($note->item_review_id) : null;
        $assignmentId = $review ? DB::table('assignment_items')->where('id', $review->assignment_item_id)->value('review_assignment_id') : null;

        return $assignmentId !== null && $user->hasPermission('notes.send') &&
            $this->access->membership($user, $assignmentId, 'team_lead');
    }

    public function administrative(User $user, ProposalItem $item): bool
    {
        $proposal = Proposal::findOrFail($item->proposal_id);
        return $user->hasPermission('notes.administrative') && $user->hasPermission('proposals.view_all')
            && in_array($proposal->status, ['submitted', 'verification', 'review'], true)
            && in_array($item->status, ['submitted', 'needs_completion', 'ready', 'awaiting_response', 'recheck'], true)
            && !DB::table('assignment_items')->where('proposal_item_id', $item->id)->exists();
    }

    public function decide(User $user, ReviewNote $note): bool
    {
        if ($note->status !== 'responded' || !$this->view($user, $note)) { return false; }
        if ($note->type === 'administrative') { return $user->hasPermission('notes.administrative'); }
        $review = ItemReview::find($note->item_review_id);
        return $review && $user->hasPermission('notes.resolve') && $user->hasRole('examiner')
            && DB::table('assignment_items as scope')->join('assignment_members as member', 'member.id', '=', 'scope.examiner_member_id')
                ->join('review_assignments as assignment', 'assignment.id', '=', 'scope.review_assignment_id')
                ->where('scope.id', $review->assignment_item_id)->where('member.user_id', $user->id)
                ->where('member.role', 'examiner')->where('member.is_active', true)->where('assignment.status', 'active')->exists();
    }
}
