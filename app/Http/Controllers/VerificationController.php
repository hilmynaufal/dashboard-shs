<?php

namespace App\Http\Controllers;

use App\Models\{Proposal, ProposalItem, SubmissionPeriod};
use App\Support\{AccountValidation, CatalogAudit, ItemVerification, ProposalDraft, ProposalItemInput, RecordVersion, ReferenceCatalog, SubmissionWindow};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('verification.perform');
        Gate::authorize('proposals.view_all');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['submitted', 'needs_completion', 'ready'])],
            'type' => ['nullable', Rule::in(['SHS', 'SBU'])],
        ], AccountValidation::MESSAGES);
        $status = $filters['status'] ?? 'submitted';
        $items = DB::table('proposal_items as item')->join('proposals as proposal', 'proposal.id', '=', 'item.proposal_id')
            ->join('organizations as organization', 'organization.id', '=', 'proposal.organization_id')
            ->join('proposal_item_versions as version', function ($join): void {
                $join->on('version.proposal_item_id', '=', 'item.id')->where('version.id', '=', function ($q): void {
                    $q->selectRaw('MAX(latest.id)')->from('proposal_item_versions as latest')
                        ->whereColumn('latest.proposal_item_id', 'item.id')->whereNotNull('latest.submitted_at');
                });
            })->whereIn('proposal.status', ['submitted', 'verification', 'review'])->where('item.status', $status)
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('proposal.type', $type))
            ->when(($filters['q'] ?? null) !== null, function ($q) use ($filters): void {
                $term = ReferenceCatalog::searchPattern($filters['q']);
                $q->where(fn ($q) => $q->where('version.description', 'like', $term)->orWhere('proposal.number', 'like', $term)->orWhere('organization.name', 'like', $term));
            })->select(['item.id', 'item.status', 'proposal.number', 'proposal.type', 'organization.name as organization_name', 'version.description', 'version.version_number', 'version.submitted_at'])
            ->orderBy('version.submitted_at')->orderBy('item.id')->paginate(25)->withQueryString();

        return view('verification.index', compact('items', 'status'));
    }

    public function show(ProposalItem $item): View
    {
        Gate::authorize('viewVerification', $item);
        $proposal = Proposal::findOrFail($item->proposal_id);
        $version = ItemVerification::latest($item->id, true);
        $files = DB::table('item_version_evidence')->join('evidence_files', 'evidence_files.id', '=', 'evidence_file_id')
            ->where('item_version_id', $version->id)->where('organization_id', $proposal->organization_id)->select('evidence_files.*')->get();

        return view('verification.show', [
            'item' => $item, 'proposal' => $proposal, 'version' => $version,
            'fields' => ProposalItemInput::fields($version), 'files' => $files,
            'organization' => DB::table('organizations')->where('id', $proposal->organization_id)->value('name'),
            'history' => ItemVerification::history($item->id), 'recordVersion' => ItemVerification::token($item),
        ]);
    }

    public function completion(Proposal $proposal, int $item): View
    {
        $itemModel = ItemVerification::item($proposal, $item);
        Gate::authorize('resubmit', $itemModel);
        $version = ItemVerification::latest($item);

        return view('verification.completion', [
            'proposal' => $proposal, 'item' => $itemModel, 'version' => $version,
            'fields' => ProposalItemInput::fields($version), 'recordVersion' => ItemVerification::token($itemModel),
            'history' => ItemVerification::history($item), 'problems' => ProposalDraft::problems($proposal, false, $item),
            'files' => DB::table('item_version_evidence')->join('evidence_files', 'evidence_files.id', '=', 'evidence_file_id')
                ->where('item_version_id', $version->id)->where('organization_id', $proposal->organization_id)->select('evidence_files.*')->get(),
        ]);
    }

    public function decide(Request $request, ProposalItem $item): JsonResponse|RedirectResponse
    {
        Gate::authorize('verify', $item);
        $data = $request->validate([
            'result' => ['required', Rule::in(['ready', 'needs_completion'])],
            'reason' => ['required_if:result,needs_completion', 'nullable', 'string', 'max:5000'],
            'record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ], AccountValidation::MESSAGES + ['reason.required_if' => 'Jelaskan alasan pengembalian agar SKPD dapat melengkapinya.'],
            ['result' => 'Hasil pemeriksaan', 'reason' => 'Alasan', 'record_version' => 'Versi halaman']);
        DB::transaction(function () use ($request, $item, $data): void {
            [$proposal, $locked] = ItemVerification::lock($request->user(), $item->id, 'verify');
            $this->assertToken($data['record_version'], $locked);
            $version = ItemVerification::latest($locked->id, true);
            abort_unless((int) $version->id === (int) ItemVerification::latest($locked->id)->id, 409, 'Versi item berubah. Muat ulang pemeriksaan.');
            if ($data['result'] === 'ready') { $this->assertComplete($proposal, $locked->id, $version->id); }
            $resultId = DB::table('verification_results')->insertGetId([
                'item_version_id' => $version->id, 'verified_by' => $request->user()->id,
                'result' => $data['result'], 'reason' => $data['reason'] ?? null,
                'verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $locked->status = $data['result']; $locked->lock_version++; $locked->save();
            if ($proposal->status === 'submitted') { $proposal->status = 'verification'; }
            $proposal->touch(); $proposal->save();
            CatalogAudit::record($request->user()->id, 'verification.decided', 'verification_result', $resultId, null,
                ['item_id' => $locked->id, 'version_id' => $version->id, 'result' => $data['result']]);
        }, 3);
        $message = $data['result'] === 'ready' ? 'Item dinyatakan siap direviu.' : 'Item dikembalikan kepada SKPD untuk dilengkapi.';

        return $request->expectsJson() ? response()->json(['message' => $message])
            : redirect()->route('verification.show', $item)->with('status', $message);
    }

    public function resubmit(Request $request, Proposal $proposal, int $item): JsonResponse|RedirectResponse
    {
        Gate::authorize('resubmit', ItemVerification::item($proposal, $item));
        $data = $request->validate([
            'record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'], 'confirm' => ['accepted'],
        ], AccountValidation::MESSAGES + ['confirm.accepted' => 'Centang pernyataan pemeriksaan kelengkapan.']);
        DB::transaction(function () use ($request, $proposal, $item, $data): void {
            [$package, $locked] = ItemVerification::lock($request->user(), $item, 'resubmit', $proposal->id);
            $this->assertToken($data['record_version'], $locked);
            $version = ItemVerification::latest($item);
            if ($version->submitted_at !== null) {
                throw ValidationException::withMessages(['completion' => 'Simpan perbaikan data atau bukti sebagai versi baru sebelum mengirim kelengkapan.']);
            }
            $period = SubmissionPeriod::query()->lockForUpdate()->findOrFail($package->submission_period_id);
            SubmissionWindow::assertOpen($period);
            $this->assertComplete($package, $item, $version->id);
            DB::table('proposal_item_versions')->where('id', $version->id)->whereNull('submitted_at')->update(['submitted_at' => now()]);
            $locked->status = 'submitted'; $locked->lock_version++; $locked->save();
            $package->touch();
            CatalogAudit::record($request->user()->id, 'verification.resubmitted', 'proposal_item_version', $version->id, null,
                ['proposal_id' => $package->id, 'item_id' => $item]);
        }, 3);

        return $request->expectsJson() ? response()->json(['message' => 'Kelengkapan dikirim untuk diperiksa BKAD kembali.'])
            : redirect()->route('proposals.show', $proposal)->with('status', 'Kelengkapan item dikirim untuk diperiksa BKAD kembali.');
    }

    private function assertToken(string $token, ProposalItem $item): void
    {
        if (!hash_equals(ItemVerification::token($item), $token)) {
            throw ValidationException::withMessages(['record_version' => 'Data atau hasil pemeriksaan berubah. Muat ulang halaman.'])->status(409);
        }
    }

    private function assertComplete(Proposal $proposal, int $itemId, int $versionId): void
    {
        $problems = ProposalDraft::problems($proposal, true, $itemId);
        if ($problems) { throw ValidationException::withMessages(['completion' => array_merge(...array_values($problems))]); }
        foreach (DB::table('item_version_evidence')->join('evidence_files', 'evidence_files.id', '=', 'evidence_file_id')
            ->where('item_version_id', $versionId)->get(['disk', 'path']) as $file) {
            if ($file->disk !== 'local' || !Storage::disk('local')->exists($file->path)) {
                throw ValidationException::withMessages(['completion' => 'Bukti pendukung tidak tersedia. Lengkapi berkas sebelum melanjutkan.']);
            }
        }
    }
}
