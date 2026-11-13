<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Impor workbook usulan Inspektorat: pratinjau per pengusul lalu penyimpanan massal.
 * Item langsung berstatus Siap direviu (tanpa kirim SKPD dan verifikasi BKAD) agar Ketua Tim dapat menugaskannya
 * lewat alur penugasan yang sudah ada. Referensi baru dan SKPD baru hanya dibuat setelah dikonfirmasi pengguna.
 */
final class InspectorateImport
{
    private const CHUNK = 500;

    public static function create(User $user, int $periodId, string $path, string $name, string $hash): int
    {
        $parsed = InspectorateWorkbook::parse($path);
        if (!$parsed['rows']) {
            throw ValidationException::withMessages(['file' => 'Tidak ada baris item pada lembar yang dikenali.']);
        }
        if (self::completed($hash, $periodId)) {
            throw ValidationException::withMessages(['file' => 'Berkas yang sama sudah diimpor pada periode ini.']);
        }
        $owners = array_values(array_unique(array_column($parsed['rows'], 'owner')));
        $invalid = count(array_filter($parsed['rows'], fn ($row) => $row['error'] !== null));

        return DB::transaction(function () use ($user, $periodId, $parsed, $name, $hash, $owners, $invalid): int {
            $id = DB::table('inspectorate_imports')->insertGetId([
                'submission_period_id' => $periodId, 'created_by' => $user->id, 'file_name' => mb_substr(basename(str_replace('\\', '/', mb_scrub($name, 'UTF-8'))), 0, 240),
                'file_sha256' => $hash, 'status' => 'preview', 'mapping' => json_encode(self::defaultMapping($owners), JSON_THROW_ON_ERROR),
                'summary' => json_encode(['sheets' => $parsed['sheets'], 'groups' => $parsed['groups'], 'rows' => count($parsed['rows']), 'invalid' => $invalid], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_chunk($parsed['rows'], self::CHUNK) as $chunk) {
                DB::table('inspectorate_import_rows')->insert(array_map(fn ($row) => ['inspectorate_import_id' => $id, 'type' => $row['type'], 'row_number' => $row['row_number'],
                    'owner' => $row['owner'], 'data' => json_encode($row['data'], JSON_THROW_ON_ERROR), 'error' => $row['error']], $chunk));
            }
            CatalogAudit::record($user->id, 'inspectorate_import.previewed', 'inspectorate_import', $id, null, ['rows' => count($parsed['rows']), 'owners' => count($owners)]);

            return $id;
        }, 3);
    }

    public static function token(object $import): string
    {
        return RecordVersion::of([$import->id, $import->status, json_decode($import->mapping ?? 'null', true)]);
    }

    public static function assertCurrent(object $import, string $version): void
    {
        if (!hash_equals(self::token($import), $version)) {
            throw ValidationException::withMessages(['record_version' => 'Pemetaan berubah sejak halaman dibuka. Muat ulang halaman sebelum menyimpan.'])->status(409);
        }
    }

    public static function completed(string $hash, int $periodId): bool
    {
        return DB::table('inspectorate_imports')->where('file_sha256', $hash)->where('submission_period_id', $periodId)->where('status', 'completed')->exists();
    }

    public static function label(string $owner): string
    {
        return $owner === InspectorateWorkbook::NO_OWNER ? 'Pengusul belum tercantum' : $owner;
    }

    /** Pengusul dicocokkan ke SKPD aktif lewat kode atau nama yang sama persis (tanpa membedakan huruf besar/kecil). */
    private static function defaultMapping(array $owners): array
    {
        $byCode = $byName = [];
        foreach (DB::table('organizations')->where('is_active', true)->orderBy('id')->get(['id', 'code', 'name']) as $org) {
            $byCode[mb_strtolower($org->code)] ??= $org->id;
            $byName[mb_strtolower($org->name)] ??= $org->id;
        }
        $mapping = [];
        foreach ($owners as $owner) {
            $key = mb_strtolower(self::label($owner));
            $id = $byCode[mb_strtolower($owner)] ?? $byName[$key] ?? null;
            $mapping[$owner] = $id ? ['org_id' => $id] : ['new' => true];
        }

        return $mapping;
    }

    /** Ringkasan per pengusul: jumlah item sah per jenis dan baris bermasalah. */
    public static function owners(int $id): array
    {
        $rows = [];
        $query = DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $id)
            ->selectRaw('owner, type, count(*) as total, sum(case when error is null then 1 else 0 end) as valid')->groupBy('owner', 'type')->orderBy('owner');
        foreach ($query->get() as $row) {
            $rows[$row->owner] ??= ['SHS' => 0, 'SBU' => 0, 'invalid' => 0];
            $rows[$row->owner][$row->type] = (int)$row->valid;
            $rows[$row->owner]['invalid'] += (int)$row->total - (int)$row->valid;
        }

        return $rows;
    }

    /** Referensi yang dipakai baris sah, dibandingkan dengan referensi yang sudah ada. */
    public static function references(int $id): array
    {
        $codes = $units = $accounts = [];
        foreach (DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $id)->whereNull('error')->orderBy('id')->cursor() as $row) {
            $data = json_decode($row->data, true, 512, JSON_THROW_ON_ERROR);
            $codes[mb_strtolower($data['code'])] ??= [$data['code'], $data['name']];
            $units[mb_strtolower($data['unit'])] ??= $data['unit'];
            foreach ($data['accounts'] as $account) { $accounts[mb_strtolower($account)] ??= $account; }
        }
        $state = ['missing' => ['classifications' => [], 'units' => [], 'accounts' => []], 'inactive' => []];
        $existing = DB::table('item_classifications')->pluck('is_active', 'code')->mapWithKeys(fn ($active, $code) => [mb_strtolower($code) => (bool)$active]);
        foreach ($codes as $key => [$code, $name]) {
            if (!$existing->has($key)) { $state['missing']['classifications'][$key] = [$code, $name]; }
            elseif (!$existing[$key]) { $state['inactive'][] = 'Kode barang '.$code; }
        }
        $known = [];
        foreach (DB::table('units')->orderBy('id')->get(['code', 'name', 'is_active']) as $unit) {
            $known[mb_strtolower($unit->code)] ??= (bool)$unit->is_active;
            $known[mb_strtolower($unit->name)] ??= (bool)$unit->is_active;
        }
        foreach ($units as $key => $unit) {
            if (!array_key_exists($key, $known)) { $state['missing']['units'][$key] = $unit; }
            elseif (!$known[$key]) { $state['inactive'][] = 'Satuan '.$unit; }
        }
        $existing = DB::table('account_codes')->pluck('is_active', 'code')->mapWithKeys(fn ($active, $code) => [mb_strtolower($code) => (bool)$active]);
        foreach ($accounts as $key => $account) {
            if (!$existing->has($key)) { $state['missing']['accounts'][$key] = $account; }
            elseif (!$existing[$key]) { $state['inactive'][] = 'Rekening '.$account; }
        }

        return $state;
    }

    /** Pilihan pengguna ("new" atau "org:ID") menjadi pemetaan yang sudah diperiksa terhadap SKPD aktif. */
    public static function normalizeMapping(array $choices, array $owners): array
    {
        $mapping = [];
        foreach ($owners as $owner) {
            $choice = $choices[$owner] ?? null;
            if ($choice === 'new') { $mapping[$owner] = ['new' => true]; continue; }
            if (is_string($choice) && preg_match('/\Aorg:([1-9][0-9]{0,18})\z/', $choice, $match)
                && DB::table('organizations')->where('id', $match[1])->where('is_active', true)->exists()) {
                $mapping[$owner] = ['org_id' => (int)$match[1]]; continue;
            }
            throw ValidationException::withMessages(['owners' => 'Pilih SKPD aktif untuk pengusul '.self::label($owner).'.']);
        }

        return $mapping;
    }

    public static function saveMapping(object $import, array $mapping): void
    {
        DB::table('inspectorate_imports')->where('id', $import->id)->update(['mapping' => json_encode($mapping, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    /**
     * Menyimpan seluruh baris sah: referensi/SKPD baru, paket per SKPD dan jenis, item Siap direviu beserta versi pertama.
     * Harus dipanggil di dalam transaksi dengan baris impor terkunci.
     */
    public static function commit(User $actor, object $import, array $mapping, bool $confirmed): array
    {
        $owners = array_keys(self::owners($import->id));
        $mapping = self::normalizeMapping(array_map(fn ($entry) => isset($entry['new']) ? 'new' : 'org:'.$entry['org_id'], $mapping), $owners);
        $state = self::references($import->id);
        if ($state['inactive']) {
            throw ValidationException::withMessages(['import' => 'Referensi nonaktif dipakai berkas: '.implode(', ', array_slice(array_unique($state['inactive']), 0, 8)).'. Aktifkan di menu Referensi terlebih dahulu.']);
        }
        $newReferences = array_map('count', $state['missing']);
        if (array_sum($newReferences) && !$confirmed) {
            throw ValidationException::withMessages(['confirm_references' => 'Konfirmasi penambahan referensi baru sebelum menyimpan.']);
        }
        $now = now();
        foreach (array_chunk(array_values($state['missing']['classifications']), self::CHUNK) as $chunk) {
            DB::table('item_classifications')->insert(array_map(fn ($c) => ['code' => $c[0], 'name' => mb_substr($c[1], 0, 255), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }
        foreach (array_chunk(array_values($state['missing']['units']), self::CHUNK) as $chunk) {
            DB::table('units')->insert(array_map(fn ($u) => ['code' => 'U-'.substr(sha1(mb_strtolower($u)), 0, 16), 'name' => $u, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }
        foreach (array_chunk(array_values($state['missing']['accounts']), self::CHUNK) as $chunk) {
            DB::table('account_codes')->insert(array_map(fn ($a) => ['code' => $a, 'name' => $a, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }
        $classes = $unitMap = $accountMap = [];
        foreach (DB::table('item_classifications')->get(['id', 'code', 'name']) as $r) { $classes[mb_strtolower($r->code)] = ['id' => $r->id, 'code' => $r->code, 'name' => $r->name]; }
        foreach (DB::table('units')->orderBy('id')->get(['id', 'code', 'name']) as $r) {
            $unitMap[mb_strtolower($r->code)] ??= ['id' => $r->id, 'code' => $r->code, 'name' => $r->name];
            $unitMap[mb_strtolower($r->name)] ??= ['id' => $r->id, 'code' => $r->code, 'name' => $r->name];
        }
        foreach (DB::table('account_codes')->get(['id', 'code', 'name']) as $r) { $accountMap[mb_strtolower($r->code)] = ['id' => $r->id, 'code' => $r->code, 'name' => $r->name]; }

        $orgs = self::organizations($mapping, $now);
        $packages = []; $items = 0;
        foreach (self::groups($import->id, $mapping) as $org => $types) {
            foreach ($types as $type => $ownerList) {
                $orgName = $orgs['names'][$org];
                $proposal = DB::table('proposals')->insertGetId(['organization_id' => $org, 'submission_period_id' => $import->submission_period_id, 'type' => $type,
                    'number' => 'IMP-'.$import->id.'-'.$type.'-'.$org, 'title' => 'Usulan '.$type.' · '.mb_substr($orgName, 0, 200), 'status' => 'verification',
                    'created_by' => $actor->id, 'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
                $count = 0;
                DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $import->id)->where('type', $type)->whereIn('owner', $ownerList)->whereNull('error')
                    ->chunkById(self::CHUNK, function ($chunk) use ($proposal, $actor, $import, $classes, $unitMap, $accountMap, $now, &$count): void {
                        self::store($chunk, $proposal, $actor, $import, $classes, $unitMap, $accountMap, $now);
                        $count += $chunk->count();
                    });
                $packages[] = ['id' => $proposal, 'number' => 'IMP-'.$import->id.'-'.$type.'-'.$org, 'organization_id' => $org, 'organization' => $orgName, 'type' => $type, 'items' => $count];
                $items += $count;
            }
        }
        $excluded = (int)DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $import->id)->whereNotNull('error')->count();
        $summary = array_replace(json_decode($import->summary, true), ['packages' => $packages, 'items' => $items, 'excluded' => $excluded,
            'new_references' => $newReferences, 'new_organizations' => $orgs['created']]);
        DB::table('inspectorate_imports')->where('id', $import->id)->update(['status' => 'completed', 'mapping' => json_encode($mapping, JSON_THROW_ON_ERROR),
            'summary' => json_encode($summary, JSON_THROW_ON_ERROR), 'completed_at' => $now, 'updated_at' => $now]);
        DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $import->id)->delete();
        CatalogAudit::record($actor->id, 'inspectorate_import.completed', 'inspectorate_import', $import->id, null,
            ['packages' => array_column($packages, 'id'), 'items' => $items, 'excluded' => $excluded, 'new_references' => $newReferences, 'new_organizations' => $orgs['created']]);

        return $summary;
    }

    /** SKPD tujuan; pengusul bermapping "new" dibuat sebagai SKPD baru dengan kode unik. */
    private static function organizations(array &$mapping, $now): array
    {
        $names = DB::table('organizations')->pluck('name', 'id')->all(); $created = [];
        foreach ($mapping as $owner => $entry) {
            if (!isset($entry['new'])) { continue; }
            $base = mb_substr($owner, 0, 50); $code = $base; $n = 1;
            while (DB::table('organizations')->where('code', $code)->exists()) { $n++; $code = mb_substr($base, 0, 46).'-'.$n; }
            $id = DB::table('organizations')->insertGetId(['code' => $code, 'name' => self::label($owner), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            $names[$id] = self::label($owner); $created[] = ['id' => $id, 'code' => $code, 'name' => self::label($owner)];
            $mapping[$owner] = ['org_id' => $id];
        }

        return ['names' => $names, 'created' => $created];
    }

    /** SKPD => jenis => daftar pengusul; pengusul berbeda yang dipetakan ke SKPD sama masuk paket yang sama. */
    private static function groups(int $id, array $mapping): array
    {
        $groups = [];
        $pairs = DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $id)->whereNull('error')->select('owner', 'type')->distinct()->orderBy('type')->orderBy('owner')->get();
        foreach ($pairs as $pair) { $groups[$mapping[$pair->owner]['org_id']][$pair->type][] = $pair->owner; }
        ksort($groups);

        return $groups;
    }

    private static function store($chunk, int $proposal, User $actor, object $import, array $classes, array $unitMap, array $accountMap, $now): void
    {
        $itemRows = []; $byUuid = [];
        foreach ($chunk as $row) {
            $uuid = (string)Str::uuid();
            $byUuid[$uuid] = json_decode($row->data, true, 512, JSON_THROW_ON_ERROR);
            $itemRows[] = ['proposal_id' => $proposal, 'public_id' => $uuid, 'status' => 'ready', 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('proposal_items')->insert($itemRows);
        $ids = DB::table('proposal_items')->whereIn('public_id', array_keys($byUuid))->pluck('id', 'public_id');
        $versions = []; $snapshots = [];
        foreach ($byUuid as $uuid => $data) {
            $class = $classes[mb_strtolower($data['code'])]; $unit = $unitMap[mb_strtolower($data['unit'])];
            $accounts = array_values(array_map(fn ($code) => $accountMap[mb_strtolower($code)], $data['accounts']));
            usort($accounts, fn ($a, $b) => $a['id'] <=> $b['id']);
            $snapshots[$ids[$uuid]] = $accounts;
            $versions[] = ['proposal_item_id' => $ids[$uuid], 'version_number' => 1, 'item_classification_id' => $class['id'], 'unit_id' => $unit['id'],
                'description' => $data['name'], 'specification' => $data['spec'], 'proposed_price' => $data['price'], 'tkdn_percent' => $data['tkdn'], 'origin' => $data['origin'],
                'justification' => $data['note'], 'reference_snapshot' => json_encode(['classification' => $class, 'unit' => $unit, 'accounts' => $accounts], JSON_THROW_ON_ERROR),
                'created_by' => $actor->id, 'change_reason' => 'Diimpor Inspektorat dari '.$import->file_name.'.', 'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('proposal_item_versions')->insert($versions);
        $versionIds = DB::table('proposal_item_versions')->whereIn('proposal_item_id', $ids->values()->all())->where('version_number', 1)->pluck('id', 'proposal_item_id');
        $links = $verifications = [];
        foreach ($snapshots as $itemId => $accounts) {
            foreach ($accounts as $account) {
                $links[] = ['item_version_id' => $versionIds[$itemId], 'account_code_id' => $account['id'], 'code_snapshot' => $account['code'], 'name_snapshot' => $account['name']];
            }
            $verifications[] = ['item_version_id' => $versionIds[$itemId], 'verified_by' => $actor->id, 'result' => 'ready',
                'reason' => 'Diimpor Inspektorat; verifikasi SKPD/BKAD tidak diperlukan.', 'verified_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($links, 1000) as $part) { DB::table('item_version_accounts')->insert($part); }
        DB::table('verification_results')->insert($verifications);
    }
}
