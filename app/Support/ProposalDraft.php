<?php

namespace App\Support;

use App\Authorization\ResourceAccess;
use App\Models\{Proposal, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProposalDraft
{
    public static function lock(User $user, int $id, string $ability = 'update'): Proposal
    {
        $proposal = Proposal::query()->lockForUpdate()->findOrFail($id);
        Gate::forUser($user->refresh())->authorize($ability, $proposal);

        return $proposal;
    }

    public static function rows(Proposal $proposal, ?User $viewer = null): Collection
    {
        $owner = $viewer === null || ($viewer->hasPermission('proposals.view_own') && (int)$viewer->organization_id === (int)$proposal->organization_id);
        $query = DB::table('proposal_items as i')->join('proposal_item_versions as v', 'v.proposal_item_id', '=', 'i.id')
            ->where('i.proposal_id', $proposal->id)->where('v.id', function ($q) use ($owner): void {
                $q->from('proposal_item_versions as latest')->selectRaw('MAX(latest.id)')->whereColumn('latest.proposal_item_id', 'i.id');
                if (! $owner) { $q->whereNotNull('latest.submitted_at'); }
            });
        if ($viewer !== null) { $query->whereIn('i.id', app(ResourceAccess::class)->visibleItemIds($viewer)); }
        $rows = $query->select(['v.*', 'v.id as version_id', 'i.id as item_id', 'i.public_id', 'i.lock_version'])->orderBy('i.id')->get();
        $files = DB::table('item_version_evidence as e')->join('evidence_files as f', 'f.id', '=', 'e.evidence_file_id')
            ->whereIn('e.item_version_id', $rows->pluck('version_id'))->where('f.organization_id', $proposal->organization_id)
            ->get(['e.item_version_id', 'f.id', 'f.original_name', 'f.byte_size', 'f.created_at'])->groupBy('item_version_id');
        foreach ($rows as $row) { $row->files = $files[$row->version_id] ?? collect(); }

        return $rows;
    }

    public static function version(Proposal $proposal): string
    {
        $items = DB::table('proposal_items')->where('proposal_id', $proposal->id)->orderBy('id')->get(['id', 'lock_version'])->map(fn ($r) => (array)$r)->all();
        $batches = DB::table('import_batches')->where('proposal_id', $proposal->id)->orderBy('id')->get(['id', 'status', 'mapping'])->map(fn ($r) => (array)$r)->all();

        return RecordVersion::of([$proposal->getAttributes(), $items, $batches]);
    }

    public static function append(Proposal $proposal, User $actor, array $data, ?int $itemId = null): int
    {
        if ($itemId === null) {
            $itemId = DB::table('proposal_items')->insertGetId(['proposal_id' => $proposal->id, 'public_id' => Str::uuid()->toString(), 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        }
        $item = DB::table('proposal_items')->where('id', $itemId)->where('proposal_id', $proposal->id)->lockForUpdate()->first(); abort_unless($item, 404);
        $old = DB::table('proposal_item_versions')->where('proposal_item_id', $itemId)->orderByDesc('version_number')->first();
        foreach (['classification' => 'item_classifications', 'unit' => 'units'] as $key => $table) {
            $current = DB::table($table)->where('id', $data[$key]['id'])->lockForUpdate()->first();
            if (! $current || ! $current->is_active || $current->code !== $data[$key]['code']) { throw ValidationException::withMessages(['code' => 'Referensi telah berubah atau nonaktif. Muat ulang dan pilih referensi aktif.']); }
            $data[$key] = ['id' => $current->id, 'code' => $current->code, 'name' => $current->name];
        }
        usort($data['accounts'], fn ($a,$b) => $a['id'] <=> $b['id']);
        foreach ($data['accounts'] as &$entry) {
            $current = DB::table('account_codes')->where('id', $entry['id'])->lockForUpdate()->first();
            if (! $current || ! $current->is_active || $current->code !== $entry['code']) { throw ValidationException::withMessages(['account' => 'Referensi rekening berubah. Pilih kembali rekening aktif.']); }
            $entry = ['id' => $current->id, 'code' => $current->code, 'name' => $current->name];
        } unset($entry);
        $id = DB::table('proposal_item_versions')->insertGetId([
            'proposal_item_id' => $itemId, 'version_number' => ($old->version_number ?? 0) + 1,
            'item_classification_id' => $data['classification']['id'], 'unit_id' => $data['unit']['id'],
            'description' => $data['description'], 'specification' => $data['specification'], 'proposed_price' => $data['proposed_price'],
            'reference_snapshot' => json_encode(array_intersect_key($data, array_flip(['classification','unit','accounts'])), JSON_THROW_ON_ERROR),
            'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($data['accounts'] as $account) {
            DB::table('item_version_accounts')->insert(['item_version_id' => $id, 'account_code_id' => $account['id'], 'code_snapshot' => $account['code'], 'name_snapshot' => $account['name']]);
        }
        if ($old) { self::copyAttachments($old->id, $id); }
        DB::table('proposal_items')->where('id', $itemId)->update(['lock_version' => $item->lock_version + 1, 'updated_at' => now()]);
        DB::table('proposals')->where('id', $proposal->id)->update(['updated_at' => now()]);

        return $id;
    }

    public static function copyVersion(Proposal $proposal, User $actor, int $itemId): int
    {
        abort_unless(DB::table('proposal_items')->where('id',$itemId)->where('proposal_id',$proposal->id)->exists(),404);
        $old = DB::table('proposal_item_versions')->where('proposal_item_id', $itemId)->orderByDesc('version_number')->first(); abort_unless($old,404);
        $values = (array)$old; unset($values['id']);
        $values['version_number']++; $values['created_by'] = $actor->id; $values['submitted_at'] = null;
        $values['created_at'] = $values['updated_at'] = now();
        $id = DB::table('proposal_item_versions')->insertGetId($values);
        foreach (DB::table('item_version_accounts')->where('item_version_id',$old->id)->get() as $link) {
            DB::table('item_version_accounts')->insert(array_replace((array)$link,['item_version_id'=>$id]));
        }
        self::copyAttachments($old->id,$id);
        DB::table('proposal_items')->where('id',$itemId)->where('proposal_id',$proposal->id)->increment('lock_version');
        DB::table('proposals')->where('id',$proposal->id)->update(['updated_at'=>now()]);

        return $id;
    }

    private static function copyAttachments(int $old, int $new): void
    {
        foreach (DB::table('item_version_evidence')->where('item_version_id',$old)->get() as $link) {
            DB::table('item_version_evidence')->insert(['item_version_id'=>$new,'evidence_file_id'=>$link->evidence_file_id,'purpose'=>$link->purpose]);
        }
        foreach (DB::table('price_sources')->where('item_version_id',$old)->get() as $source) {
            $values = (array)$source; unset($values['id']); $values['item_version_id']=$new;
            DB::table('price_sources')->insert($values);
        }
    }

    public static function problems(Proposal $proposal, bool $lockReferences = false): array
    {
        $problems = [];
        $rows = self::rows($proposal);
        $ids = $lockReferences ? ['code'=>$rows->pluck('item_classification_id')->unique()->all(), 'unit'=>$rows->pluck('unit_id')->unique()->all(),
            'account'=>DB::table('item_version_accounts')->whereIn('item_version_id',$rows->pluck('version_id'))->pluck('account_code_id')->unique()->all()] : null;
        $lookup = new ProposalItemInput($ids);
        if ($rows->isEmpty()) { $problems['package'] = ['Tambahkan sedikitnya satu item.']; }
        foreach ($rows as $row) {
            try { $lookup->validate(ProposalItemInput::fields($row), true); }
            catch (ValidationException $error) { $problems[$row->item_id] = array_merge(...array_values($error->errors())); }
            if ($row->files->isEmpty()) { $problems[$row->item_id][] = 'Bukti pendukung belum dilampirkan.'; }
        }
        if (DB::table('import_batches')->where('proposal_id',$proposal->id)->where('status','preview')->exists()) { $problems['import'] = ['Simpan atau batalkan pratinjau impor sebelum mengirim usulan.']; }

        return $problems;
    }
}
