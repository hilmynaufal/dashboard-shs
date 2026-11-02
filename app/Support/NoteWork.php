<?php

namespace App\Support;

use App\Models\{ItemReview, Proposal, ProposalItem, ProposalItemVersion, ReviewAssignment, ReviewNote, User};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

final class NoteWork
{
    public static function forItem(int $itemId)
    {
        return DB::table('review_notes as note')->join('proposal_item_versions as origin', 'origin.id', '=', 'note.item_version_id')
            ->where('origin.proposal_item_id', $itemId);
    }

    public static function revisionAllowed(int $itemId): bool
    {
        if (self::finalItem($itemId)) { return false; }
        return self::forItem($itemId)->leftJoin('item_reviews as review', 'review.id', '=', 'note.item_review_id')
            ->leftJoin('assignment_items as scope', 'scope.id', '=', 'review.assignment_item_id')
            ->leftJoin('review_assignments as assignment', 'assignment.id', '=', 'scope.review_assignment_id')
            ->where('note.status', 'sent')->where(fn ($q) => $q->where('note.type', 'administrative')->orWhere('assignment.status', 'active'))->exists();
    }

    public static function finalItem(int $itemId): bool
    {
        return DB::table('kkr_entries')->join('kkr_versions', 'kkr_versions.id', '=', 'kkr_entries.kkr_version_id')
            ->join('item_reviews', 'item_reviews.id', '=', 'kkr_entries.item_review_id')->whereNotNull('kkr_versions.finalized_at')
            ->where('item_reviews.proposal_item_id', $itemId)->exists();
    }

    public static function token(ReviewNote $note): string
    {
        $item = ProposalItem::findOrFail(ProposalItemVersion::findOrFail($note->item_version_id)->proposal_item_id);
        $assignment = self::assignment($note);

        return RecordVersion::of([$note->getAttributes(), $item->getAttributes(), (array) ItemVerification::latest($item->id),
            DB::table('note_responses')->where('review_note_id', $note->id)->max('id'),
            DB::table('note_response_decisions')->whereIn('note_response_id', DB::table('note_responses')->select('id')->where('review_note_id', $note->id))->max('id'),
            $assignment ? AssignmentWork::token($assignment) : null]);
    }

    public static function assignment(ReviewNote $note): ?ReviewAssignment
    {
        $review = $note->item_review_id ? ItemReview::find($note->item_review_id) : null;
        return $review ? ReviewAssignment::find(DB::table('assignment_items')->where('id', $review->assignment_item_id)->value('review_assignment_id')) : null;
    }

    /** Match review/transfer lock order; operator drafts only lock parent -> item. */
    public static function lock(User $user, int $noteId, string $ability, string $token): array
    {
        $initial = ReviewNote::findOrFail($noteId);
        $assignment = self::assignment($initial);
        if ($assignment) { $assignment = ReviewAssignment::query()->lockForUpdate()->findOrFail($assignment->id); }
        $version = ProposalItemVersion::findOrFail($initial->item_version_id);
        $initialItem = ProposalItem::findOrFail($version->proposal_item_id);
        $proposal = Proposal::query()->lockForUpdate()->findOrFail($initialItem->proposal_id);
        $item = ProposalItem::query()->lockForUpdate()->findOrFail($initialItem->id);
        $note = ReviewNote::query()->lockForUpdate()->findOrFail($noteId);
        Gate::forUser($user->refresh())->authorize($ability, $note);
        self::assertMutable($proposal, $assignment, $item->id);
        if (!hash_equals(self::token($note), $token)) {
            throw ValidationException::withMessages(['record_version' => 'Catatan, tanggapan, versi item atau penugasan berubah. Muat ulang catatan sebelum melanjutkan.'])->status(409);
        }

        return [$note, $proposal, $item];
    }

    public static function assertMutable(Proposal $proposal, ?ReviewAssignment $assignment = null, ?int $itemId = null): void
    {
        if (!self::isMutable($proposal, $assignment, $itemId)) {
            throw ValidationException::withMessages(['note' => 'Usulan atau penugasan tidak lagi menerima tindak lanjut.']);
        }
    }

    public static function isMutable(Proposal $proposal, ?ReviewAssignment $assignment, ?int $itemId): bool
    {
        return in_array($proposal->status, ['submitted', 'verification', 'review'], true)
            && (!$assignment || $assignment->status === 'active') && ($itemId === null || !self::finalItem($itemId));
    }

    public static function refreshItem(ProposalItem $item): void
    {
        $open = self::forItem($item->id)->whereIn('note.status', ['sent', 'responded'])->pluck('note.status');
        if ($open->contains('sent')) { $item->status = 'awaiting_response'; }
        elseif ($open->contains('responded')) { $item->status = 'recheck'; }
        else {
            $review = ItemReview::where('proposal_item_id', $item->id)->orderByDesc('id')->first();
            $item->status = $review ? ($review->status === 'completed' ? 'reviewed' : 'reviewing')
                : (DB::table('verification_results')->where('item_version_id', ItemVerification::latest($item->id, true)->id)->orderByDesc('id')->value('result') ?? 'submitted');
        }
        $item->lock_version++; $item->save();
    }
}
