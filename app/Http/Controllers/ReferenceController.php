<?php

namespace App\Http\Controllers;

use App\Support\{AccountValidation, CatalogAudit, RecordVersion, ReferenceCatalog};
use Illuminate\Database\QueryException;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class ReferenceController extends Controller
{
    public function index(Request $request, string $kind): View
    {
        Gate::authorize('references.manage'); $type = ReferenceCatalog::type($kind);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]], AccountValidation::MESSAGES);
        $term = $filters['q'] ?? null;
        $rows = DB::table($type['table'])->when($term !== null, fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', ReferenceCatalog::searchPattern($term))->orWhere('name', 'like', ReferenceCatalog::searchPattern($term))))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('code')->orderBy('id')->paginate(15)->withQueryString();

        return view('catalog.references.index', compact('kind', 'type', 'rows'));
    }

    public function create(string $kind): View
    {
        Gate::authorize('references.manage');

        return view('catalog.references.form', ['kind' => $kind, 'type' => ReferenceCatalog::type($kind), 'row' => null]);
    }

    public function edit(string $kind, int $record): View
    {
        Gate::authorize('references.manage'); $type = ReferenceCatalog::type($kind);
        $row = DB::table($type['table'])->find($record); abort_unless($row, 404);

        return view('catalog.references.form', ['kind' => $kind, 'type' => $type, 'row' => $row, 'recordVersion' => RecordVersion::of((array) $row)]);
    }

    public function store(Request $request, string $kind): JsonResponse|RedirectResponse
    {
        Gate::authorize('references.manage'); $type = ReferenceCatalog::type($kind);
        $data = $this->validated($request, $type['table'], null);
        try {
            $id = DB::transaction(function () use ($request, $type, $data): int {
                $id = DB::table($type['table'])->insertGetId(['code' => $data['code'], 'name' => $data['name'], 'is_active' => (bool) $data['is_active'], 'created_at' => now(), 'updated_at' => now()]);
                CatalogAudit::record($request->user()->id, 'reference.created', $type['table'], $id, null, (array) DB::table($type['table'])->find($id));

                return $id;
            });
        } catch (QueryException $error) { $this->duplicateOrThrow($error); }

        return $request->expectsJson() ? response()->json(['message' => 'Referensi berhasil dibuat.', 'id' => $id], 201)
            : redirect()->route('references.index', $kind)->with('status', 'Referensi berhasil dibuat.');
    }

    public function update(Request $request, string $kind, int $record): JsonResponse|RedirectResponse
    {
        Gate::authorize('references.manage'); $type = ReferenceCatalog::type($kind);
        abort_unless(DB::table($type['table'])->where('id', $record)->exists(), 404);
        $data = $this->validated($request, $type['table'], $record);
        try {
            DB::transaction(function () use ($request, $type, $record, $data): void {
                $row = DB::table($type['table'])->where('id', $record)->lockForUpdate()->first(); abort_unless($row, 404);
                $before = (array) $row; RecordVersion::assertCurrent($data['record_version'], $before);
                if ($row->code !== $data['code'] || $row->name !== $data['name'] || (bool) $row->is_active !== (bool) $data['is_active']) {
                    DB::table($type['table'])->where('id', $record)->update(['code' => $data['code'], 'name' => $data['name'], 'is_active' => (bool) $data['is_active'], 'updated_at' => now()]);
                    CatalogAudit::record($request->user()->id, 'reference.updated', $type['table'], $record, $before, (array) DB::table($type['table'])->find($record));
                }
            });
        } catch (QueryException $error) { $this->duplicateOrThrow($error); }

        return $request->expectsJson() ? response()->json(['message' => 'Referensi berhasil diperbarui.'])
            : redirect()->route('references.index', $kind)->with('status', 'Referensi berhasil diperbarui.');
    }

    public function destroy(Request $request, string $kind, int $record): JsonResponse|RedirectResponse
    {
        Gate::authorize('references.manage'); $type = ReferenceCatalog::type($kind);
        $data = $request->validate(['record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']], ['required' => 'Muat ulang data sebelum menghapus.', 'regex' => 'Penanda versi tidak sesuai. Muat ulang data.']);
        try {
            DB::transaction(function () use ($request, $type, $record, $data): void {
                $row = DB::table($type['table'])->where('id', $record)->lockForUpdate()->first(); abort_unless($row, 404);
                RecordVersion::assertCurrent($data['record_version'], (array) $row);
                DB::table($type['table'])->where('id', $record)->delete();
                CatalogAudit::record($request->user()->id, 'reference.deleted', $type['table'], $record, (array) $row, null);
            });
        } catch (QueryException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) === 1451) { throw ValidationException::withMessages(['reference' => 'Referensi sudah digunakan atau memiliki relasi dan tidak dapat dihapus. Nonaktifkan jika tidak dipakai untuk usulan baru.']); }
            throw $error;
        }

        return $request->expectsJson() ? response()->json(['message' => 'Referensi berhasil dihapus.'])
            : redirect()->route('references.index', $kind)->with('status', 'Referensi berhasil dihapus.');
    }

    private function validated(Request $request, string $table, ?int $id): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique($table, 'code')->ignore($id)], 'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'], 'parent_id' => ['prohibited'],
            'record_version' => $id === null ? ['prohibited'] : ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ], AccountValidation::MESSAGES + ['record_version.required' => 'Muat ulang data sebelum menyimpan.', 'regex' => 'Penanda versi tidak sesuai. Muat ulang data.'], ['code' => 'Kode', 'name' => 'Nama', 'is_active' => 'Status referensi', 'record_version' => 'Versi data']);
    }

    private function duplicateOrThrow(QueryException $error): never
    {
        if ((int) ($error->errorInfo[1] ?? 0) === 1062) { throw ValidationException::withMessages(['code' => 'Kode sudah digunakan pada jenis referensi yang sama.']); }
        throw $error;
    }
}
