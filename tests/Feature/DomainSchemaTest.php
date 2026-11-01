<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'shs_new_testing') {
            throw new \RuntimeException('Pengujian skema hanya boleh mengosongkan MySQL shs_new_testing.');
        }
    }

    private function fixture(): array
    {
        $org = DB::table('organizations')->insertGetId(['code' => 'DEMO', 'name' => 'Organisasi simulasi']);
        $user = DB::table('users')->insertGetId(['name' => 'Pengguna simulasi', 'email' => 'schema@example.test', 'password' => bcrypt(Str::random(40)), 'organization_id' => $org]);
        $period = DB::table('submission_periods')->insertGetId(['budget_year' => 2026, 'name' => 'Periode simulasi', 'opens_at' => '2026-01-01', 'closes_at' => '2026-12-31', 'created_by' => $user]);
        $classification = DB::table('item_classifications')->insertGetId(['code' => '1.01', 'name' => 'Klasifikasi simulasi']);
        $unit = DB::table('units')->insertGetId(['code' => 'UNIT', 'name' => 'Unit']);
        $proposal = DB::table('proposals')->insertGetId(['organization_id' => $org, 'submission_period_id' => $period, 'type' => 'SHS', 'title' => 'Usulan simulasi', 'created_by' => $user]);
        $item = DB::table('proposal_items')->insertGetId(['public_id' => Str::uuid()->toString(), 'proposal_id' => $proposal]);
        $values = ['proposal_item_id' => $item, 'version_number' => 1, 'item_classification_id' => $classification, 'unit_id' => $unit, 'description' => 'Item simulasi', 'specification' => 'Spesifikasi simulasi', 'proposed_price' => '123456789012345.67', 'reference_snapshot' => json_encode(['code' => '1.01', 'unit' => 'Unit']), 'created_by' => $user];
        $version = DB::table('proposal_item_versions')->insertGetId($values);

        return compact('org', 'user', 'period', 'classification', 'unit', 'proposal', 'item', 'version', 'values');
    }

    public function test_versions_keep_exact_prices_and_master_snapshots(): void
    {
        $f = $this->fixture();
        DB::table('units')->where('id', $f['unit'])->update(['name' => 'Satuan terbaru']);
        $v = DB::table('proposal_item_versions')->find($f['version']);
        $this->assertSame('123456789012345.67', $v->proposed_price);
        $this->assertSame('Unit', json_decode($v->reference_snapshot, true)['unit']);
        $this->expectException(QueryException::class);
        DB::table('proposal_item_versions')->insert($f['values']);
    }

    public function test_referenced_master_cannot_be_deleted(): void
    {
        $f = $this->fixture();
        $this->expectException(QueryException::class);
        DB::table('units')->where('id', $f['unit'])->delete();
    }

    public function test_item_supports_multiple_accounts_without_duplicate_links(): void
    {
        $f = $this->fixture();
        foreach (['5.01', '5.02'] as $code) {
            $account = DB::table('account_codes')->insertGetId(['code' => $code, 'name' => 'Rekening simulasi']);
            DB::table('item_version_accounts')->insert(['item_version_id' => $f['version'], 'account_code_id' => $account, 'code_snapshot' => $code, 'name_snapshot' => 'Rekening simulasi']);
        }
        $this->assertSame(2, DB::table('item_version_accounts')->where('item_version_id', $f['version'])->count());
        $this->expectException(QueryException::class);
        DB::table('item_version_accounts')->insert(['item_version_id' => $f['version'], 'account_code_id' => $account, 'code_snapshot' => $code, 'name_snapshot' => 'Rekening simulasi']);
    }

    public function test_import_batch_identity_prevents_duplicate_imports_in_same_package(): void
    {
        $f = $this->fixture();
        $batch = ['proposal_id' => $f['proposal'], 'created_by' => $f['user'], 'file_sha256' => hash('sha256', 'simulation'), 'sheet_name' => 'Data_SSH'];
        DB::table('import_batches')->insert($batch);
        $this->expectException(QueryException::class);
        DB::table('import_batches')->insert($batch);
    }

    public function test_assignment_cannot_reference_another_items_version(): void
    {
        $f = $this->fixture();
        $assignment = DB::table('review_assignments')->insertGetId(['number' => 'DEMO-TASK', 'title' => 'Penugasan simulasi', 'submission_period_id' => $f['period'], 'object_organization_id' => $f['org'], 'created_by' => $f['user']]);
        $member = DB::table('assignment_members')->insertGetId(['review_assignment_id' => $assignment, 'user_id' => $f['user'], 'role' => 'examiner']);
        $otherItem = DB::table('proposal_items')->insertGetId(['public_id' => Str::uuid()->toString(), 'proposal_id' => $f['proposal']]);
        $this->expectException(QueryException::class);
        DB::table('assignment_items')->insert(['review_assignment_id' => $assignment, 'proposal_item_id' => $otherItem, 'initial_version_id' => $f['version'], 'examiner_member_id' => $member]);
    }

    public function test_member_from_another_assignment_cannot_receive_scope(): void
    {
        $f = $this->fixture();
        $task = ['title' => 'Penugasan simulasi', 'submission_period_id' => $f['period'], 'object_organization_id' => $f['org'], 'created_by' => $f['user']];
        $first = DB::table('review_assignments')->insertGetId($task + ['number' => 'DEMO-FIRST']);
        $second = DB::table('review_assignments')->insertGetId($task + ['number' => 'DEMO-SECOND']);
        $member = DB::table('assignment_members')->insertGetId(['review_assignment_id' => $first, 'user_id' => $f['user'], 'role' => 'examiner']);
        $this->expectException(QueryException::class);
        DB::table('assignment_items')->insert(['review_assignment_id' => $second, 'proposal_item_id' => $f['item'], 'initial_version_id' => $f['version'], 'examiner_member_id' => $member]);
    }

    public function test_approvals_belong_to_specific_kkr_version(): void
    {
        $f = $this->fixture();
        $assignment = DB::table('review_assignments')->insertGetId(['number' => 'DEMO-KKR', 'title' => 'Penugasan simulasi', 'submission_period_id' => $f['period'], 'object_organization_id' => $f['org'], 'created_by' => $f['user']]);
        $document = DB::table('kkr_documents')->insertGetId(['review_assignment_id' => $assignment, 'prepared_by' => $f['user'], 'number' => 'DEMO-KKR-01', 'title' => 'KKR simulasi']);
        foreach ([1, 2] as $number) {
            $snapshot = json_encode(['conclusion' => 'Simulasi '.$number]);
            $versions[$number] = DB::table('kkr_versions')->insertGetId(['kkr_document_id' => $document, 'version_number' => $number, 'template_version' => 'demo-1', 'snapshot' => $snapshot, 'snapshot_sha256' => hash('sha256', $snapshot), 'created_by' => $f['user']]);
        }
        DB::table('kkr_approvals')->insert(['kkr_version_id' => $versions[1], 'decided_by' => $f['user'], 'level' => 'team_lead', 'decision' => 'approved', 'decided_at' => now()]);
        $this->assertSame(0, DB::table('kkr_approvals')->where('kkr_version_id', $versions[2])->count());
        $this->expectException(QueryException::class);
        DB::table('kkr_versions')->where('id', $versions[1])->delete();
    }
}
