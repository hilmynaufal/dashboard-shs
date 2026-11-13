<?php

namespace App\Support;

use App\Models\{ItemReview, Proposal, ProposalItem, ProposalItemVersion, ReviewAssignment, User};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

final class ReviewWork
{
    public static function token(ItemReview $review): string
    {
        $assignment = ReviewAssignment::findOrFail(DB::table('assignment_items')->where('id', $review->assignment_item_id)->value('review_assignment_id'));

        return RecordVersion::of([$review->getAttributes(), AssignmentWork::token($assignment)]);
    }

    /** Serialize saves/start/completion with assignment transfers before reading permissions. */
    public static function lockScope(User $user, int $assignmentId, int $scopeId): array
    {
        $assignment = ReviewAssignment::query()->lockForUpdate()->findOrFail($assignmentId);
        Gate::forUser($user->refresh())->authorize('view', $assignment);
        $scope = DB::table('assignment_items')->where('review_assignment_id', $assignment->id)->where('id', $scopeId)->first(); abort_unless($scope, 404);
        $initial = ProposalItem::findOrFail($scope->proposal_item_id);
        $proposal = Proposal::query()->lockForUpdate()->findOrFail($initial->proposal_id);
        $item = ProposalItem::query()->lockForUpdate()->findOrFail($initial->id);
        $scope = DB::table('assignment_items')->where('id', $scope->id)->lockForUpdate()->first();

        return [$assignment, $scope, $proposal, $item];
    }

    public static function lockReview(User $user, int $reviewId, string $token): array
    {
        $initial = ItemReview::findOrFail($reviewId);
        $assignmentId = DB::table('assignment_items')->where('id', $initial->assignment_item_id)->value('review_assignment_id');
        [$assignment, $scope, $proposal, $item] = self::lockScope($user, $assignmentId, $initial->assignment_item_id);
        $review = ItemReview::query()->lockForUpdate()->findOrFail($reviewId);
        Gate::forUser($user)->authorize('update', $review);
        if ((int)$review->proposal_item_id !== (int)$scope->proposal_item_id || (int)$review->item_version_id !== (int)$scope->initial_version_id ||
            $proposal->status !== 'review' || !in_array($item->status, ['assigned', 'reviewing', 'awaiting_response', 'recheck'], true)) {
            throw ValidationException::withMessages(['review' => 'Item atau versi tidak berada dalam pencatatan reviu aktif.']);
        }
        if (!hash_equals(self::token($review), $token)) {
            throw ValidationException::withMessages(['record_version' => 'Hasil atau pembagian tugas berubah. Muat ulang reviu sebelum menyimpan.'])->status(409);
        }

        return [$review, $item];
    }

    public static function results(int $reviewId)
    {
        return DB::table('review_results')->where('item_review_id', $reviewId)->orderBy('id')->get()->map(function ($row) {
            $row->criterion = json_decode($row->criterion_snapshot, true, 512, JSON_THROW_ON_ERROR);

            return $row;
        });
    }

    /** Mode tersimpan; "single" berarti satu hasil berlaku untuk semua aspek dan catatannya ada pada baris aspek pertama. */
    public static function mode(int $reviewId): string
    {
        return DB::table('item_reviews')->where('id', $reviewId)->value('result_mode') === 'single' ? 'single' : 'per_aspect';
    }

    /** Nilai awal formulir mode tunggal: hasil bersama bila seragam, selain itu Belum dinilai. */
    public static function overall($rows): array
    {
        $first = $rows->first();
        $same = $first && $rows->every(fn ($row) => $row->result === $first->result);

        return ['result' => $same ? $first->result : 'not_assessed', 'reason' => $first->reason ?? null, 'recommendation' => $first->recommendation ?? null];
    }

    public static function problems(int $reviewId, ?string $mode = null): array
    {
        $rows = self::results($reviewId); $errors = [];
        if ($rows->isEmpty()) { $errors[] = 'Aspek pemeriksaan belum disiapkan; reviu tidak dapat diselesaikan tanpa aspek.'; }
        if (($mode ?? self::mode($reviewId)) === 'single') {
            $overall = self::overall($rows);
            if ($rows->isNotEmpty() && $overall['result'] === 'not_assessed') { $errors[] = 'Hasil pemeriksaan belum dinilai.'; }
            if ($overall['result'] === 'not_applicable' && trim($overall['reason'] ?? '') === '') { $errors[] = 'Hasil pemeriksaan: jelaskan alasan tidak berlaku.'; }

            return $errors;
        }
        foreach ($rows as $row) {
            $name = $row->criterion['name'];
            if (($row->criterion['is_required'] ?? true) && $row->result === 'not_assessed') { $errors[] = $name.': aspek wajib belum dinilai.'; }
            if ($row->result === 'not_applicable' && trim($row->reason ?? '') === '') { $errors[] = $name.': jelaskan alasan tidak berlaku.'; }
        }

        return $errors;
    }

    public static function money(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') { return null; }
        try { return ProposalMoney::normalize($value); }
        catch (ValidationException $error) { throw ValidationException::withMessages([$field => $error->errors()['price']]); }
    }
}
