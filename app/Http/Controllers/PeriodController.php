<?php

namespace App\Http\Controllers;

use App\Models\SubmissionPeriod;
use App\Support\{AccountValidation, CatalogAudit, RecordVersion};
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class PeriodController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('periods.manage');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['draft', 'open', 'closed'])]], AccountValidation::MESSAGES);
        $term = $filters['q'] ?? null;
        $periods = SubmissionPeriod::query()
            ->when($term !== null, fn ($query) => $query->where(function ($query) use ($term): void {
                $query->where('name', 'like', \App\Support\ReferenceCatalog::searchPattern($term));
                if (preg_match('/\A[0-9]{4}\z/', $term)) { $query->orWhere('budget_year', (int) $term); }
            }))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('budget_year')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('catalog.periods.index', compact('periods'));
    }

    public function create(): View
    {
        Gate::authorize('periods.manage');

        return view('catalog.periods.form', ['period' => null]);
    }

    public function edit(SubmissionPeriod $period): View
    {
        Gate::authorize('periods.manage');

        return view('catalog.periods.form', ['period' => $period, 'recordVersion' => RecordVersion::of($period->getAttributes())]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('periods.manage');
        $data = $this->validated($request, false);
        $period = DB::transaction(function () use ($request, $data): SubmissionPeriod {
            $period = new SubmissionPeriod;
            $this->assign($period, $data);
            $period->created_by = $request->user()->id;
            $period->save();
            $period->refresh();
            CatalogAudit::record($request->user()->id, 'period.created', 'submission_period', $period->id, null, $period->getAttributes());

            return $period;
        });

        return $request->expectsJson() ? response()->json(['message' => 'Periode berhasil dibuat.', 'id' => $period->id], 201)
            : redirect()->route('periods.index')->with('status', 'Periode berhasil dibuat.');
    }

    public function update(Request $request, SubmissionPeriod $period): JsonResponse|RedirectResponse
    {
        Gate::authorize('periods.manage');
        $data = $this->validated($request, true);
        DB::transaction(function () use ($request, $period, $data): void {
            $locked = SubmissionPeriod::query()->lockForUpdate()->findOrFail($period->id);
            RecordVersion::assertCurrent($data['record_version'], $locked->getAttributes());
            if ((int) $data['budget_year'] !== $locked->budget_year && (DB::table('proposals')->where('submission_period_id', $locked->id)->exists() || DB::table('review_assignments')->where('submission_period_id', $locked->id)->exists())) {
                throw ValidationException::withMessages(['budget_year' => 'Tahun anggaran periode yang sudah digunakan tidak dapat diubah.']);
            }
            $before = $locked->getAttributes();
            $this->assign($locked, $data);
            if ($locked->isDirty()) {
                $locked->save(); $locked->refresh();
                CatalogAudit::record($request->user()->id, 'period.updated', 'submission_period', $locked->id, $before, $locked->getAttributes());
            }
        });

        return $request->expectsJson() ? response()->json(['message' => 'Periode berhasil diperbarui.'])
            : redirect()->route('periods.index')->with('status', 'Periode berhasil diperbarui.');
    }

    private function validated(Request $request, bool $update): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'budget_year' => ['required', 'integer', 'between:1000,9999'],
            'opens_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-02', 'before_or_equal:9999-12-31'],
            'closes_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:opens_on', 'before_or_equal:9999-12-31'],
            'status' => ['required', Rule::in(['draft', 'open', 'closed'])],
            'record_version' => $update ? ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'] : ['prohibited'],
            'created_by' => ['prohibited'], 'opens_at' => ['prohibited'], 'closes_at' => ['prohibited'],
        ], array_replace(AccountValidation::MESSAGES, [
            'integer' => ':attribute harus berupa angka bulat.', 'between' => 'Tahun anggaran harus terdiri dari empat digit.',
            'date_format' => ':attribute harus berupa tanggal yang sah.', 'after_or_equal' => 'Tanggal tutup tidak boleh lebih awal daripada tanggal buka.',
            'opens_on.after_or_equal' => 'Tanggal buka berada di luar rentang tanggal yang didukung.',
            'before_or_equal' => ':attribute berada di luar rentang tanggal yang didukung.',
            'record_version.required' => 'Muat ulang data sebelum menyimpan.', 'regex' => 'Penanda versi tidak sesuai; muat ulang data.',
        ]), ['name' => 'Nama periode', 'budget_year' => 'Tahun anggaran', 'opens_on' => 'Tanggal buka', 'closes_on' => 'Tanggal tutup', 'status' => 'Status', 'record_version' => 'Versi data']);
    }

    private function assign(SubmissionPeriod $period, array $data): void
    {
        $period->name = $data['name']; $period->budget_year = $data['budget_year']; $period->status = $data['status'];
        $period->opens_at = CarbonImmutable::createFromFormat('!Y-m-d', $data['opens_on'], SubmissionPeriod::DISPLAY_TIMEZONE)->startOfDay()->utc();
        $period->closes_at = CarbonImmutable::createFromFormat('!Y-m-d', $data['closes_on'], SubmissionPeriod::DISPLAY_TIMEZONE)->endOfDay()->setMicrosecond(0)->utc();
    }
}
