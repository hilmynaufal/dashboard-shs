<?php

namespace App\Http\Controllers;

use App\Support\{AccountValidation, CatalogAudit, InspectorateImport, UploadLimits};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class InspectorateImportController extends Controller
{
    private function authorizeImport(Request $request): void
    {
        abort_unless($request->user()->hasPermission('inspectorate.import'), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeImport($request);
        $imports = DB::table('inspectorate_imports as import')->join('submission_periods as period', 'period.id', '=', 'import.submission_period_id')
            ->join('users as author', 'author.id', '=', 'import.created_by')->orderByDesc('import.id')
            ->select('import.*', 'period.name as period_name', 'period.budget_year', 'author.name as author_name')->paginate(15);

        return view('inspectorate.imports.index', ['imports' => $imports, 'periods' => DB::table('submission_periods')->orderByDesc('budget_year')->orderBy('id')->get()]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->authorizeImport($request);
        $data = $request->validate(['file' => ['required', 'file', 'max:'.UploadLimits::kilobytes(5120), 'extensions:xlsx'],
            'submission_period_id' => ['required', 'integer', 'exists:submission_periods,id']],
            ['file.max' => 'Berkas Excel maksimal '.UploadLimits::label(5120).'.', 'file.extensions' => 'Gunakan berkas .xlsx.', 'file.uploaded' => 'Berkas gagal diunggah; periksa batas ukuran server.']
            + AccountValidation::MESSAGES, ['file' => 'Berkas', 'submission_period_id' => 'Periode']);
        ini_set('memory_limit', '768M'); set_time_limit(300);
        $file = $request->file('file');
        $id = InspectorateImport::create($request->user(), (int)$data['submission_period_id'], $file->getRealPath(), $file->getClientOriginalName(), hash_file('sha256', $file->getRealPath()));

        return redirect()->route('inspectorate.imports.show', $id)->with('status', 'Pratinjau siap. Periksa pemetaan pengusul sebelum menyimpan.');
    }

    public function show(Request $request, int $import): View
    {
        $this->authorizeImport($request);
        $record = $this->find($import);
        $base = ['import' => $record, 'summary' => json_decode($record->summary, true), 'period' => DB::table('submission_periods')->find($record->submission_period_id),
            'author' => DB::table('users')->where('id', $record->created_by)->value('name')];
        if ($record->status !== 'preview') { return view('inspectorate.imports.show', $base + ['mapping' => [], 'preview' => false]); }
        $references = InspectorateImport::references($record->id);

        return view('inspectorate.imports.show', $base + ['preview' => true, 'mapping' => json_decode($record->mapping, true), 'owners' => InspectorateImport::owners($record->id),
            'organizations' => DB::table('organizations')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'references' => $references, 'recordVersion' => InspectorateImport::token($record),
            'problems' => DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $record->id)->whereNotNull('error')->orderBy('id')->paginate(25, ['*'], 'masalah')->withQueryString()]);
    }

    public function update(Request $request, int $import): RedirectResponse
    {
        $this->authorizeImport($request);
        $data = $request->validate(['record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'], 'intent' => ['required', Rule::in(['save', 'commit'])],
            'owners' => ['required', 'array', 'min:1', 'max:500'], 'owners.*' => ['required', 'string', 'max:30'],
            'owner_keys' => ['required', 'array', 'min:1', 'max:500'], 'owner_keys.*' => ['required', 'string', 'max:120'], 'confirm_references' => ['nullable', 'boolean']], AccountValidation::MESSAGES);
        ini_set('memory_limit', '768M'); set_time_limit(300);
        DB::transaction(function () use ($request, $import, $data): void {
            $record = DB::table('inspectorate_imports')->where('id', $import)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($record->status === 'preview', 409, 'Impor ini sudah selesai atau dibatalkan.');
            InspectorateImport::assertCurrent($record, $data['record_version']);
            $mapping = InspectorateImport::normalizeMapping($this->choices($request), array_keys(InspectorateImport::owners($record->id)));
            if ($data['intent'] === 'save') {
                InspectorateImport::saveMapping($record, $mapping);

                return;
            }
            InspectorateImport::commit($request->user(), $record, $mapping, (bool)($data['confirm_references'] ?? false));
        }, 3);

        return $data['intent'] === 'save'
            ? redirect()->route('inspectorate.imports.show', $import)->with('status', 'Pemetaan pengusul tersimpan. Data belum masuk ke paket sebelum Anda menyimpan impor.')
            : redirect()->route('inspectorate.imports.show', $import)->with('status', 'Impor tersimpan. Item berstatus Siap direviu dan dapat ditugaskan dari menu Penugasan reviu.');
    }

    public function cancel(Request $request, int $import): RedirectResponse
    {
        $this->authorizeImport($request);
        DB::transaction(function () use ($request, $import): void {
            $record = DB::table('inspectorate_imports')->where('id', $import)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($record->status === 'preview', 409, 'Hanya pratinjau yang dapat dibatalkan.');
            DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $record->id)->delete();
            DB::table('inspectorate_imports')->where('id', $record->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            CatalogAudit::record($request->user()->id, 'inspectorate_import.cancelled', 'inspectorate_import', $record->id, null, null);
        });

        return redirect()->route('inspectorate.imports.index')->with('status', 'Pratinjau dibatalkan dan data sementara dihapus.');
    }

    /** Kunci pengusul dikirim sebagai indeks daftar agar karakter khusus tidak merusak nama field; nilai asli ada pada owner_keys. */
    private function choices(Request $request): array
    {
        $keys = $request->input('owner_keys', []); $values = $request->input('owners', []);
        $choices = [];
        foreach ($values as $index => $value) {
            if (is_string($keys[$index] ?? null)) { $choices[$keys[$index]] = $value; }
        }

        return $choices;
    }

    private function find(int $id): object
    {
        return DB::table('inspectorate_imports')->where('id', $id)->first() ?? abort(404);
    }
}
