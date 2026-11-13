<?php

namespace App\Http\Controllers;

use App\Models\{ItemReview, Proposal, ProposalItemVersion, ReviewAssignment};
use App\Support\{AccountValidation, AssignmentWork, CatalogAudit, ProposalItemInput, ReviewWork};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function desk(Request $request, ReviewAssignment $assignment, int $scope): View|RedirectResponse
    {
        Gate::authorize('view', $assignment);
        $record = AssignmentWork::items($request->user(), $assignment)->where('scope.id', $scope)->first(); abort_unless($record, 404);
        $review = ItemReview::where('assignment_item_id', $scope)->where('item_version_id', $record->initial_version_id)->first();
        if ($review) { return redirect()->route('reviews.show', $review); }
        $version = ProposalItemVersion::findOrFail($record->initial_version_id); Gate::authorize('view', $version);

        return view('reviews.start', $this->source($version) + [
            'assignment' => $assignment, 'scope' => $record, 'recordVersion' => AssignmentWork::token($assignment),
            'aspects' => DB::table('review_aspects')->where('is_active', true)->orderBy('id')->get(),
            'nav' => $this->neighbors($request, $assignment, $scope),
        ]);
    }

    public function start(Request $request, ReviewAssignment $assignment, int $scope): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [ItemReview::class, $assignment, $scope]);
        $data = $request->validate(['record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']], AccountValidation::MESSAGES);
        $review = DB::transaction(function () use ($request, $assignment, $scope, $data): ItemReview {
            [$locked, $record, $proposal, $item] = ReviewWork::lockScope($request->user(), $assignment->id, $scope);
            Gate::forUser($request->user())->authorize('create', [ItemReview::class, $locked, $scope]);
            if (!hash_equals(AssignmentWork::token($locked), $data['record_version'])) { throw ValidationException::withMessages(['record_version' => 'Pembagian tugas berubah. Muat ulang halaman.'])->status(409); }
            $existing = ItemReview::where('assignment_item_id', $scope)->where('item_version_id', $record->initial_version_id)->first();
            if ($existing) { return $existing; }
            $aspects = DB::table('review_aspects')->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            if ($aspects->isEmpty() || $aspects->count() > 200) { throw ValidationException::withMessages(['aspects' => 'Daftar aspek pemeriksaan belum tersedia atau terlalu banyak. Hubungi pengelola sebelum memulai.']); }
            $review = new ItemReview;
            $review->assignment_item_id = $scope; $review->proposal_item_id = $item->id; $review->item_version_id = $record->initial_version_id;
            $review->reviewed_by = $request->user()->id; $review->status = 'in_progress'; $review->save();
            foreach ($aspects as $aspect) {
                DB::table('review_results')->insert(['item_review_id' => $review->id, 'review_aspect_id' => $aspect->id, 'result' => 'not_assessed',
                    'criterion_snapshot' => json_encode(['code' => $aspect->code, 'name' => $aspect->name, 'is_required' => (bool)$aspect->is_required], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            }
            $item->status = 'reviewing'; $item->lock_version++; $item->save();
            CatalogAudit::record($request->user()->id, 'review.started', 'item_review', $review->id, null, ['scope_id' => $scope, 'version_id' => $record->initial_version_id]);

            return $review;
        }, 3);

        return $request->expectsJson() ? response()->json(['id' => $review->id, 'message' => 'Reviu dimulai.'], 201)
            : redirect()->route('reviews.show', $review)->with('status', 'Reviu dimulai dengan aspek pemeriksaan yang disimpan untuk versi ini.');
    }

    public function show(Request $request, ItemReview $review): View
    {
        Gate::authorize('view', $review);
        $request->validate(['note_page' => ['nullable', 'integer', 'min:1', 'max:100000']], AccountValidation::MESSAGES);
        $version = ProposalItemVersion::findOrFail($review->item_version_id);
        $assignment = ReviewAssignment::findOrFail(DB::table('assignment_items')->where('id', $review->assignment_item_id)->value('review_assignment_id'));
        $notes = DB::table('review_notes as note')->join('users as author', 'author.id', '=', 'note.created_by')
            ->where('note.item_review_id', $review->id)->where('note.type', 'substantive')
            ->select('note.*', 'author.name as author_name')->orderByDesc('note.id')->paginate(10, ['*'], 'note_page')->withQueryString();

        return view('reviews.show', $this->source($version) + [
            'review' => $review, 'assignment' => $assignment, 'results' => $results = ReviewWork::results($review->id), 'overall' => ReviewWork::overall($results),
            'notes' => $notes, 'recordVersion' => Gate::allows('update', $review) ? ReviewWork::token($review) : null,
            'problems' => ReviewWork::problems($review->id),
            'nav' => $this->neighbors($request, $assignment, (int)$review->assignment_item_id),
        ]);
    }

    public function save(Request $request, ItemReview $review): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $review);
        // Tanpa result_mode, formulir/klien lama tetap memakai mode yang tersimpan pada reviu.
        $request->mergeIfMissing(['result_mode' => $review->result_mode ?? 'per_aspect']);
        $single = $request->input('result_mode') === 'single';
        $data = $request->validate([
            'record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'], 'intent' => ['required', Rule::in(['save', 'complete', 'complete_next'])],
            'result_mode' => ['required', Rule::in(array_keys(ItemReview::MODES))],
            'comparison_price' => ['nullable'], 'recommended_price' => ['nullable'], 'recommendation' => ['nullable', 'string', 'max:10000'],
        ] + ($single ? [
            'overall' => ['required', 'array:result,reason,recommendation'], 'overall.result' => ['required', Rule::in(array_keys(ItemReview::RESULTS))],
            'overall.reason' => ['nullable', 'string', 'max:10000'], 'overall.recommendation' => ['nullable', 'string', 'max:10000'],
        ] : [
            'results' => ['required', 'array', 'min:1', 'max:200'], 'results.*' => ['required', 'array:result,reason,recommendation'],
            'results.*.result' => ['required', Rule::in(array_keys(ItemReview::RESULTS))],
            'results.*.reason' => ['nullable', 'string', 'max:10000'], 'results.*.recommendation' => ['nullable', 'string', 'max:10000'],
        ]), AccountValidation::MESSAGES);
        // "Selesai & lanjut" menyimpan persis seperti "selesai"; bedanya hanya tujuan setelah tersimpan.
        $continue = $data['intent'] === 'complete_next';
        if ($continue) { $data['intent'] = 'complete'; }
        $comparison = ReviewWork::money($data['comparison_price'] ?? null, 'comparison_price');
        $recommended = ReviewWork::money($data['recommended_price'] ?? null, 'recommended_price');
        DB::transaction(function () use ($request, $review, $data, $comparison, $recommended): void {
            [$locked, $item] = ReviewWork::lockReview($request->user(), $review->id, $data['record_version']);
            $rows = ReviewWork::results($locked->id); $mode = $data['result_mode'];
            if ($mode === 'single') {
                // Satu hasil berlaku untuk semua aspek; catatan dan rekomendasi disimpan sekali pada aspek pertama agar tidak berulang di KKR.
                $overall = $data['overall'];
                if ($overall['result'] === 'not_applicable' && trim($overall['reason'] ?? '') === '') {
                    throw ValidationException::withMessages(['overall.reason' => 'Jelaskan alasan hasil pemeriksaan tidak berlaku.']);
                }
                foreach ($rows->values() as $index => $row) {
                    DB::table('review_results')->where('id', $row->id)->update(['result' => $overall['result'],
                        'reason' => $index === 0 ? ($overall['reason'] ?? null) : null, 'recommendation' => $index === 0 ? ($overall['recommendation'] ?? null) : null, 'updated_at' => now()]);
                }
            } else {
                $expected = $rows->pluck('id')->map(fn ($id) => (string)$id)->all(); sort($expected, SORT_STRING);
                $provided = array_map('strval', array_keys($data['results'])); sort($provided, SORT_STRING);
                if ($provided !== $expected) { throw ValidationException::withMessages(['results' => 'Daftar aspek tidak sesuai reviu ini. Muat ulang sebelum menyimpan.']); }
                foreach ($rows as $row) {
                    $value = $data['results'][$row->id];
                    if ($value['result'] === 'not_applicable' && trim($value['reason'] ?? '') === '') {
                        throw ValidationException::withMessages(['results.'.$row->id.'.reason' => 'Jelaskan alasan aspek tidak berlaku.']);
                    }
                    DB::table('review_results')->where('id', $row->id)->update(['result' => $value['result'], 'reason' => $value['reason'] ?? null, 'recommendation' => $value['recommendation'] ?? null, 'updated_at' => now()]);
                }
            }
            $locked->result_mode = $mode;
            $locked->comparison_price = $comparison; $locked->recommended_price = $recommended; $locked->recommendation = $data['recommendation'] ?? null;
            if ($data['intent'] === 'complete') {
                $problems = ReviewWork::problems($locked->id, $mode);
                if (\App\Support\NoteWork::forItem($item->id)->whereIn('note.status', ['sent', 'responded'])->exists()) { $problems[] = 'Masih ada catatan resmi yang belum selesai ditindaklanjuti.'; }
                if ($problems) { throw ValidationException::withMessages(['completion' => $problems]); }
                $locked->status = 'completed'; $locked->completed_at = now(); $item->status = 'reviewed';
            }
            $locked->lock_version++; $locked->save(); $item->lock_version++; $item->save();
            CatalogAudit::record($request->user()->id, $data['intent'] === 'complete' ? 'review.completed' : 'review.saved', 'item_review', $locked->id, null,
                ['lock_version' => $locked->lock_version, 'result_ids' => $rows->pluck('id')->all(), 'status' => $locked->status]);
        }, 3);
        $message = $data['intent'] === 'complete' ? 'Reviu item selesai diperiksa; supervisi dan KKR mengikuti tahap berikutnya.' : 'Draf hasil reviu tersimpan.';

        if ($continue) {
            $scope = (int)$review->assignment_item_id;
            $assignmentId = (int)DB::table('assignment_items')->where('id', $scope)->value('review_assignment_id');
            $todo = $this->neighbors($request, ReviewAssignment::findOrFail($assignmentId), $scope)['todo'];
            $next = $todo ? route('reviews.desk', [$assignmentId, $todo]) : null;
            $message = $todo ? 'Reviu item sebelumnya selesai diperiksa. Ini item berikutnya yang perlu Anda kerjakan.'
                : 'Reviu item selesai diperiksa. Tidak ada item lain yang perlu Anda kerjakan pada penugasan ini.';
            if ($request->expectsJson()) { return response()->json(['message' => $message, 'next' => $next]); }

            return redirect()->to($next ?? route('reviews.show', $review))->with('status', $message);
        }

        return $request->expectsJson() ? response()->json(['message' => $message]) : redirect()->route('reviews.show', $review)->with('status', $message);
    }

    public function note(Request $request, ItemReview $review): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $review); Gate::authorize('notes.internal');
        $data = $request->validate(['record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'category' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:10000'], 'note_recommendation' => ['nullable', 'string', 'max:10000']], AccountValidation::MESSAGES,
            ['category' => 'Kategori catatan', 'description' => 'Masalah', 'note_recommendation' => 'Rekomendasi catatan']);
        $id = DB::transaction(function () use ($request, $review, $data): int {
            [$locked] = ReviewWork::lockReview($request->user(), $review->id, $data['record_version']); Gate::forUser($request->user())->authorize('notes.internal');
            $id = DB::table('review_notes')->insertGetId(['item_version_id' => $locked->item_version_id, 'item_review_id' => $locked->id,
                'created_by' => $request->user()->id, 'type' => 'substantive', 'category' => $data['category'], 'description' => $data['description'],
                'recommendation' => $data['note_recommendation'] ?? null, 'status' => 'internal', 'created_at' => now(), 'updated_at' => now()]);
            $locked->lock_version++; $locked->save();
            CatalogAudit::record($request->user()->id, 'review.note_added', 'item_review', $locked->id, null, ['note_id' => $id, 'lock_version' => $locked->lock_version]);

            return $id;
        }, 3);

        return $request->expectsJson() ? response()->json(['id' => $id, 'message' => 'Catatan internal ditambahkan.'], 201)
            : redirect()->route('reviews.show', $review)->with('status', 'Catatan internal ditambahkan tanpa menimpa catatan sebelumnya.');
    }

    /**
     * Posisi item di antara item penugasan yang boleh dilihat pengguna (urut pembagian tugas),
     * beserta item berikutnya milik pengguna yang reviunya belum selesai.
     */
    private function neighbors(Request $request, ReviewAssignment $assignment, int $scope): array
    {
        $user = $request->user();
        $rows = AssignmentWork::items($user, $assignment)->addSelect('member.user_id as examiner_user_id')->orderBy('scope.id')->get();
        $ids = $rows->pluck('id')->map(fn ($id) => (int)$id)->values();
        $index = $ids->search($scope);
        $pending = $rows->filter(fn ($row) => (int)$row->id !== $scope && (int)$row->examiner_user_id === (int)$user->id && (int)$row->completed_reviews === 0)
            ->pluck('id')->map(fn ($id) => (int)$id)->values();

        return [
            'assignment' => $assignment->id, 'total' => $ids->count(), 'position' => $index === false ? null : $index + 1,
            'prev' => $index === false || $index === 0 ? null : $ids[$index - 1],
            'next' => $index === false || $index === $ids->count() - 1 ? null : $ids[$index + 1],
            // Utamakan item setelah item ini; bila habis, kembali ke yang terlewat di depannya.
            'todo' => $pending->first(fn ($id) => $id > $scope) ?? $pending->first(),
        ];
    }

    private function source(ProposalItemVersion $version): array
    {
        $proposal = Proposal::findOrFail(DB::table('proposal_items')->where('id', $version->proposal_item_id)->value('proposal_id'));

        return ['version' => $version, 'proposal' => $proposal, 'fields' => ProposalItemInput::fields($version),
            'files' => DB::table('item_version_evidence')->join('evidence_files', 'evidence_files.id', '=', 'evidence_file_id')
                ->where('item_version_id', $version->id)->where('organization_id', $proposal->organization_id)->select('evidence_files.*')->get()];
    }
}
