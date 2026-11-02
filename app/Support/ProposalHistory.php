<?php

namespace App\Support;

use App\Authorization\ResourceAccess;
use App\Models\{Proposal, ProposalItem, ProposalItemVersion, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ProposalHistory
{
    public static function versions(User $user, ProposalItem $item): Builder
    {
        $proposal = Proposal::findOrFail($item->proposal_id);
        $owner = $user->hasPermission('history.own') && $user->hasPermission('proposals.view_own')
            && app(ResourceAccess::class)->owns($user, $proposal->organization_id);

        return ProposalItemVersion::query()->where('proposal_item_id', $item->id)
            ->when(!$owner, fn ($q) => $q->whereNotNull('submitted_at'));
    }

    public static function files(int $versionId, int $organizationId)
    {
        return DB::table('item_version_evidence')->join('evidence_files', 'evidence_files.id', '=', 'evidence_file_id')
            ->where('item_version_id', $versionId)->where('organization_id', $organizationId)
            ->select('evidence_files.id', 'evidence_files.original_name')->orderBy('evidence_files.id')->get();
    }

    /** Only recognized item/version events are projected; never expose raw audit JSON. */
    public static function events(User $user, ProposalItemVersion $version)
    {
        $item = ProposalItem::findOrFail($version->proposal_item_id);
        $saved = DB::table('proposal_item_versions as version')->leftJoin('users as actor', 'actor.id', '=', 'version.created_by')
            ->where('version.id', $version->id)->whereNotNull('version.created_at')
            ->selectRaw("CONCAT('saved.', version.id) as event_key, 'Versi disimpan' as label, NULL as reason, actor.name as actor_name, version.created_at as event_at, NULL as note_id");
        $submission = self::submissionLogs($version, $item)->leftJoin('users as actor', 'actor.id', '=', 'log.actor_id')
            ->selectRaw("CONCAT('submitted.', log.id) as event_key, CASE log.action WHEN 'verification.resubmitted' THEN 'Kelengkapan diajukan ulang' WHEN 'proposal.item_revision_submitted' THEN 'Revisi dikirim bersama tanggapan' ELSE 'Usulan dikirim' END as label, NULL as reason, actor.name as actor_name, log.occurred_at as event_at, NULL as note_id");
        // Imported/legacy records may lack a sender audit. Show the stored time without inventing an actor.
        $fallback = DB::table('proposal_item_versions as version')->where('version.id', $version->id)->whereNotNull('version.submitted_at')
            ->whereNotExists(self::submissionLogs($version, $item)->selectRaw('1'))
            ->selectRaw("CONCAT('submitted_legacy.', version.id) as event_key, 'Versi dikirim' as label, NULL as reason, NULL as actor_name, version.submitted_at as event_at, NULL as note_id");
        $verified = DB::table('verification_results as result')->join('users as actor', 'actor.id', '=', 'result.verified_by')->where('result.item_version_id', $version->id)
            ->selectRaw("CONCAT('verification.', result.id) as event_key, CASE result.result WHEN 'needs_completion' THEN 'Dikembalikan untuk dilengkapi' ELSE 'Dinyatakan siap direviu' END as label, result.reason, actor.name as actor_name, result.verified_at as event_at, NULL as note_id");
        $visibleNotes = app(ResourceAccess::class)->visibleNoteIds($user);
        $sent = DB::table('review_notes as note')->leftJoin('users as actor', 'actor.id', '=', 'note.sent_by')
            ->where('note.item_version_id', $version->id)->whereIn('note.id', clone $visibleNotes)->whereNotNull('note.sent_at')
            ->selectRaw("CONCAT('note_sent.', note.id) as event_key, CASE note.type WHEN 'administrative' THEN 'Catatan administrasi dikirim' ELSE 'Catatan reviu resmi dikirim' END as label, note.description as reason, actor.name as actor_name, note.sent_at as event_at, note.id as note_id");
        $responses = DB::table('note_responses as response')->join('review_notes as note', 'note.id', '=', 'response.review_note_id')
            ->join('users as actor', 'actor.id', '=', 'response.responded_by')->whereIn('note.id', clone $visibleNotes)
            ->where(fn ($q) => $q->where('note.item_version_id', $version->id)->orWhere('response.revised_version_id', $version->id))
            ->selectRaw("CONCAT('response.', response.id) as event_key, 'Tanggapan SKPD dikirim' as label, response.response as reason, actor.name as actor_name, response.responded_at as event_at, note.id as note_id");
        $decisions = DB::table('note_response_decisions as decision')->join('note_responses as response', 'response.id', '=', 'decision.note_response_id')
            ->join('review_notes as note', 'note.id', '=', 'response.review_note_id')->join('users as actor', 'actor.id', '=', 'decision.decided_by')
            ->whereIn('note.id', $visibleNotes)->where(fn ($q) => $q->where('note.item_version_id', $version->id)->orWhere('response.revised_version_id', $version->id))
            ->selectRaw("CONCAT('decision.', decision.id) as event_key, CASE decision.decision WHEN 'needs_revision' THEN 'Tanggapan dikembalikan untuk diperbaiki' ELSE 'Catatan selesai ditindaklanjuti' END as label, decision.reason, actor.name as actor_name, decision.decided_at as event_at, note.id as note_id");

        return DB::query()->fromSub($saved->unionAll($submission)->unionAll($fallback)->unionAll($verified)->unionAll($sent)->unionAll($responses)->unionAll($decisions), 'event')
            ->orderByDesc('event_at')->orderByDesc('event_key');
    }

    private static function submissionLogs(ProposalItemVersion $version, ProposalItem $item)
    {
        return DB::table('activity_logs as log')->where(function ($q) use ($version, $item): void {
            $q->where(fn ($q) => $q->where('log.subject_type', 'proposal')->where('log.subject_id', $item->proposal_id)
                ->where('log.action', 'proposal.submitted')->whereJsonContains('log.after_values->version_ids', $version->id))
                ->orWhere(fn ($q) => $q->where('log.subject_type', 'proposal_item_version')->where('log.subject_id', $version->id)
                    ->whereIn('log.action', ['verification.resubmitted', 'proposal.item_revision_submitted']));
        });
    }
}
