<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{ItemReview, ReviewNote, User};
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
        return $user->hasPermission('notes.respond_own') && in_array($note->status, ['sent', 'responded'], true) &&
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
}
