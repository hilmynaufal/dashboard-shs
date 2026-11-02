<?php

namespace App\Http\Controllers;

use App\Authorization\ResourceAccess;
use App\Models\{Proposal, ProposalItem, ProposalItemVersion, ReviewNote, SubmissionPeriod};
use App\Support\{AccountValidation, CatalogAudit, ItemVerification, NoteWork, ProposalDraft, ProposalItemInput, SubmissionWindow};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class ReviewNoteController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(array_intersect($request->user()->permissionCodes(), ['notes.respond_own', 'notes.administrative', 'proposals.view_assigned']), 403);
        $filters = $request->validate(['status' => ['nullable', Rule::in(array_keys(ReviewNote::LABELS))], 'item' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']], AccountValidation::MESSAGES);
        $notes = DB::table('review_notes as note')->join('proposal_item_versions as version', 'version.id', '=', 'note.item_version_id')
            ->join('proposal_items as item', 'item.id', '=', 'version.proposal_item_id')->join('proposals as proposal', 'proposal.id', '=', 'item.proposal_id')
            ->whereIn('note.id', app(ResourceAccess::class)->visibleNoteIds($request->user()))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('note.status', $status))
            ->when($filters['item'] ?? null, fn ($q, $id) => $q->where('item.id', $id))
            ->select('note.*', 'version.description as item_name', 'version.version_number', 'proposal.number')->orderByDesc('note.id')->paginate(20)->withQueryString();

        return view('notes.index', compact('notes'));
    }

    public function show(Request $request, ReviewNote $note): View
    {
        Gate::authorize('view', $note);
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']], AccountValidation::MESSAGES);
        $origin = ProposalItemVersion::findOrFail($note->item_version_id);
        $item = ProposalItem::findOrFail($origin->proposal_item_id); $proposal = Proposal::findOrFail($item->proposal_id);
        $responses = DB::table('note_responses as response')->join('users as author', 'author.id', '=', 'response.responded_by')
            ->leftJoin('note_response_decisions as decision', 'decision.note_response_id', '=', 'response.id')->leftJoin('users as decider', 'decider.id', '=', 'decision.decided_by')
            ->where('response.review_note_id', $note->id)->select('response.*', 'author.name as author_name', 'decision.decision', 'decision.reason', 'decision.decided_at', 'decider.name as decider_name')
            ->orderByDesc('response.id')->paginate(10)->withQueryString();
        foreach ($responses as $response) {
            $response->version = $response->revised_version_id ? ProposalItemVersion::where('proposal_item_id', $item->id)->whereNotNull('submitted_at')->findOrFail($response->revised_version_id) : $origin;
            $response->fields = ProposalItemInput::fields($response->version);
            $response->files = $this->files($response->version->id, $proposal->organization_id);
        }
        $latest = ItemVerification::latest($item->id);
        return view('notes.show', [
            'note' => $note, 'item' => $item, 'proposal' => $proposal, 'origin' => $origin, 'originFields' => ProposalItemInput::fields($origin),
            'originFiles' => $this->files($origin->id, $proposal->organization_id), 'responses' => $responses,
            'lastResponseId' => DB::table('note_responses')->where('review_note_id', $note->id)->max('id'),
            'hasDraft' => $latest->submitted_at === null, 'recordVersion' => NoteWork::token($note),
            'mutable' => NoteWork::isMutable($proposal, NoteWork::assignment($note), $item->id),
        ]);
    }

    public function administrative(Request $request, ProposalItem $item): JsonResponse|RedirectResponse
    {
        Gate::authorize('administrative', [ReviewNote::class, $item]);
        $data = $request->validate($this->tokenRules() + ['category' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:10000'], 'recommendation' => ['nullable', 'string', 'max:10000']], AccountValidation::MESSAGES);
        $note = DB::transaction(function () use ($request, $item, $data): ReviewNote {
            [$proposal, $locked] = ItemVerification::lock($request->user(), $item->id, 'viewVerification');
            Gate::forUser($request->user())->authorize('administrative', [ReviewNote::class, $locked]);
            NoteWork::assertMutable($proposal, null, $locked->id);
            if (!hash_equals(ItemVerification::token($locked), $data['record_version'])) { throw ValidationException::withMessages(['record_version' => 'Item berubah. Muat ulang pemeriksaan.'])->status(409); }
            $note = new ReviewNote;
            $note->item_version_id = ItemVerification::latest($item->id, true)->id; $note->created_by = $request->user()->id;
            $note->type = 'administrative'; $note->category = $data['category']; $note->description = $data['description'];
            $note->recommendation = $data['recommendation'] ?? null; $note->status = 'sent'; $note->sent_by = $request->user()->id; $note->sent_at = now(); $note->save();
            NoteWork::refreshItem($locked);
            CatalogAudit::record($request->user()->id, 'note.administrative_sent', 'review_note', $note->id, null, ['item_version_id' => $note->item_version_id]);
            return $note;
        }, 3);
        return $request->expectsJson() ? response()->json(['id' => $note->id, 'message' => 'Catatan administrasi dikirim.'], 201)
            : redirect()->route('notes.show', $note)->with('status', 'Catatan administrasi resmi dikirim kepada SKPD.');
    }

    public function send(Request $request, ReviewNote $note): JsonResponse|RedirectResponse
    {
        Gate::authorize('send', $note); $data = $request->validate($this->tokenRules() + ['confirm' => ['accepted']], AccountValidation::MESSAGES);
        DB::transaction(function () use ($request, $note, $data): void {
            [$locked, $proposal, $item] = NoteWork::lock($request->user(), $note->id, 'send', $data['record_version']);
            $locked->status = 'sent'; $locked->sent_by = $request->user()->id; $locked->sent_at = now(); $locked->save();
            NoteWork::refreshItem($item);
            CatalogAudit::record($request->user()->id, 'note.sent', 'review_note', $locked->id, ['status' => $note->status], ['status' => 'sent', 'item_version_id' => $locked->item_version_id]);
        }, 3);
        return $this->done($request, $note, 'Catatan resmi dikirim kepada SKPD.');
    }

    public function respond(Request $request, ReviewNote $note): JsonResponse|RedirectResponse
    {
        Gate::authorize('respond', $note);
        $data = $request->validate($this->tokenRules() + ['response' => ['required', 'string', 'max:10000'], 'submit_revision' => ['nullable', 'boolean'], 'revised_version_id' => ['prohibited']], AccountValidation::MESSAGES);
        DB::transaction(function () use ($request, $note, $data): void {
            [$locked, $proposal, $item] = NoteWork::lock($request->user(), $note->id, 'respond', $data['record_version']);
            $version = ItemVerification::latest($item->id, true);
            if ($data['submit_revision'] ?? false) {
                Gate::forUser($request->user())->authorize('proposals.submit_own');
                $version = ItemVerification::latest($item->id);
                if ($version->submitted_at !== null) { throw ValidationException::withMessages(['submit_revision' => 'Belum ada draf revisi baru. Ubah item atau tambahkan bukti terlebih dahulu.']); }
                $period = SubmissionPeriod::query()->lockForUpdate()->findOrFail($proposal->submission_period_id); SubmissionWindow::assertOpen($period);
                $problems = ProposalDraft::problems($proposal, true, $item->id);
                if ($problems) { throw ValidationException::withMessages(['submit_revision' => array_merge(...array_values($problems))]); }
                foreach ($this->files($version->id, $proposal->organization_id) as $file) {
                    if ($file->disk !== 'local' || !Storage::disk('local')->exists($file->path)) { throw ValidationException::withMessages(['submit_revision' => 'Ada bukti yang tidak tersedia. Unggah kembali sebelum mengirim revisi.']); }
                }
                DB::table('proposal_item_versions')->where('id', $version->id)->whereNull('submitted_at')->update(['submitted_at' => now()]);
                CatalogAudit::record($request->user()->id, 'proposal.item_revision_submitted', 'proposal_item_version', $version->id, null, ['item_id' => $item->id, 'note_id' => $locked->id]);
            }
            $id = DB::table('note_responses')->insertGetId(['review_note_id' => $locked->id, 'responded_by' => $request->user()->id,
                'response' => $data['response'], 'revised_version_id' => $version->id, 'responded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $locked->status = 'responded'; $locked->save(); NoteWork::refreshItem($item);
            CatalogAudit::record($request->user()->id, 'note.responded', 'note_response', $id, null, ['note_id' => $locked->id, 'version_id' => $version->id]);
        }, 3);
        return $this->done($request, $note, 'Tanggapan dikirim untuk diperiksa.');
    }

    public function decide(Request $request, ReviewNote $note): JsonResponse|RedirectResponse
    {
        Gate::authorize('decide', $note);
        $data = $request->validate($this->tokenRules() + ['decision' => ['required', Rule::in(['resolved', 'needs_revision'])], 'reason' => ['required', 'string', 'max:10000']], AccountValidation::MESSAGES);
        DB::transaction(function () use ($request, $note, $data): void {
            [$locked, $proposal, $item] = NoteWork::lock($request->user(), $note->id, 'decide', $data['record_version']);
            $response = DB::table('note_responses')->where('review_note_id', $locked->id)->orderByDesc('id')->lockForUpdate()->first(); abort_unless($response, 409);
            if (DB::table('note_response_decisions')->where('note_response_id', $response->id)->exists()) { throw ValidationException::withMessages(['decision' => 'Tanggapan ini sudah diputuskan. Muat ulang catatan.'])->status(409); }
            DB::table('note_response_decisions')->insert(['note_response_id' => $response->id, 'decided_by' => $request->user()->id,
                'decision' => $data['decision'], 'reason' => $data['reason'], 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $locked->status = $data['decision'] === 'resolved' ? 'resolved' : 'sent'; $locked->save(); NoteWork::refreshItem($item);
            CatalogAudit::record($request->user()->id, 'note.response_decided', 'note_response', $response->id, null, ['decision' => $data['decision'], 'reason' => $data['reason']]);
        }, 3);
        return $this->done($request, $note, $data['decision'] === 'resolved' ? 'Catatan selesai ditindaklanjuti.' : 'Tanggapan dikembalikan kepada SKPD beserta alasan perbaikan.');
    }

    private function files(int $versionId, int $organizationId)
    {
        return DB::table('item_version_evidence')->join('evidence_files', 'evidence_files.id', '=', 'evidence_file_id')->where('item_version_id', $versionId)->where('organization_id', $organizationId)->select('evidence_files.*')->get();
    }

    private function tokenRules(): array { return ['record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']]; }

    private function done(Request $request, ReviewNote $note, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message]) : redirect()->route('notes.show', $note)->with('status', $message);
    }
}
