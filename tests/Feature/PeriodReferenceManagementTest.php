<?php

namespace Tests\Feature;

use App\Models\SubmissionPeriod;
use App\Support\{RecordVersion, ReferenceCatalog, SubmissionWindow};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\MySqlAccessTestCase;

class PeriodReferenceManagementTest extends MySqlAccessTestCase
{
    private function periodData(): array
    {
        return ['name' => 'Periode simulasi', 'budget_year' => 2027, 'opens_on' => '2026-12-01', 'closes_on' => '2027-01-31', 'status' => 'open'];
    }

    private function periodVersion(int $id): string
    {
        return RecordVersion::of(SubmissionPeriod::findOrFail($id)->getAttributes());
    }

    private function referenceVersion(string $kind, int $id): string
    {
        return RecordVersion::of((array) DB::table(ReferenceCatalog::type($kind)['table'])->find($id));
    }

    public function test_admin_and_bkad_can_manage_periods_and_all_reference_types(): void
    {
        foreach (['admin', 'bkad'] as $role) {
            $this->flushSession(); $this->actingAs($this->actor($role));
            $id = $this->postJson('/periode', $this->periodData())->assertCreated()->json('id');
            $this->get('/periode')->assertOk()->assertSee('Periode simulasi');
            $this->get('/periode/'.$id.'/ubah')->assertOk()->assertSee('2026-12-01');
            $this->patchJson('/periode/'.$id, array_replace($this->periodData(), ['status' => 'closed', 'record_version' => $this->periodVersion($id)]))->assertOk();
            $this->assertSame('closed', SubmissionPeriod::findOrFail($id)->status);
            foreach (ReferenceCatalog::TYPES as $kind => $type) {
                $data = ['code' => '001.020-'.$role, 'name' => 'Referensi simulasi', 'is_active' => true];
                $ref = $this->postJson('/referensi/'.$kind, $data)->assertCreated()->json('id');
                $this->get('/referensi/'.$kind.'/'.$ref.'/ubah')->assertOk();
                $this->patchJson('/referensi/'.$kind.'/'.$ref, array_replace($data, ['name' => 'Referensi berubah', 'is_active' => false, 'record_version' => $this->referenceVersion($kind, $ref)]))->assertOk();
                $this->assertDatabaseHas($type['table'], ['id' => $ref, 'code' => $data['code'], 'name' => 'Referensi berubah', 'is_active' => false]);
            }
        }
    }

    public function test_other_roles_and_guests_cannot_manage_periods_or_references(): void
    {
        $this->get('/periode')->assertRedirect('/masuk'); $this->get('/referensi/barang')->assertRedirect('/masuk');
        $owner = $this->actor('skpd_operator', $this->organization()); $fixture = $this->submission($owner);
        $referenceIds = ['barang' => $fixture['version']->item_classification_id, 'satuan' => $fixture['version']->unit_id,
            'rekening' => DB::table('account_codes')->insertGetId(['code' => 'SIM', 'name' => 'Rekening simulasi'])];
        foreach (['examiner', 'team_lead', 'technical_controller', 'skpd_operator'] as $role) {
            $this->flushSession(); $this->actingAs($this->actor($role, $role === 'skpd_operator' ? $this->organization() : null));
            $this->get('/periode')->assertForbidden(); $this->get('/periode/buat')->assertForbidden();
            $this->postJson('/periode', $this->periodData())->assertForbidden();
            $this->get('/periode/'.$fixture['period_id'].'/ubah')->assertForbidden();
            $this->patchJson('/periode/'.$fixture['period_id'], $this->periodData())->assertForbidden();
            foreach (array_keys(ReferenceCatalog::TYPES) as $kind) {
                $this->get('/referensi/'.$kind)->assertForbidden(); $this->get('/referensi/'.$kind.'/buat')->assertForbidden();
                $this->postJson('/referensi/'.$kind, ['code' => '001', 'name' => 'Simulasi', 'is_active' => true])->assertForbidden();
                $this->get('/referensi/'.$kind.'/'.$referenceIds[$kind].'/ubah')->assertForbidden();
                $this->patchJson('/referensi/'.$kind.'/'.$referenceIds[$kind], ['name' => 'Ubah ilegal'])->assertForbidden();
                $this->deleteJson('/referensi/'.$kind.'/'.$referenceIds[$kind], [])->assertForbidden();
            }
        }
    }

    public function test_period_date_order_invalid_dates_and_status_are_rejected(): void
    {
        $this->actingAs($this->actor('admin'));
        $this->postJson('/periode', array_replace($this->periodData(), ['closes_on' => '2026-11-30']))->assertUnprocessable()->assertJsonValidationErrors('closes_on');
        $this->postJson('/periode', array_replace($this->periodData(), ['opens_on' => '2026-02-30']))->assertUnprocessable()->assertJsonValidationErrors('opens_on');
        $this->postJson('/periode', array_replace($this->periodData(), ['opens_on' => '0001-01-01']))->assertUnprocessable()->assertJsonValidationErrors('opens_on');
        $this->postJson('/periode', array_replace($this->periodData(), ['status' => 'unsupported']))->assertUnprocessable();
        $this->postJson('/periode', array_replace($this->periodData(), ['budget_year' => 'invalid']))->assertUnprocessable();
        $this->assertDatabaseCount('submission_periods', 0);
    }

    public function test_wib_date_storage_and_submission_window_handle_exact_boundaries_and_subseconds(): void
    {
        $this->actingAs($this->actor('admin'));
        $id = $this->postJson('/periode', ['name' => 'Satu hari simulasi', 'budget_year' => 2027, 'opens_on' => '2027-01-01', 'closes_on' => '2027-01-01', 'status' => 'open'])->assertCreated()->json('id');
        $period = SubmissionPeriod::findOrFail($id);
        $this->assertSame('2026-12-31 17:00:00', $period->getRawOriginal('opens_at'));
        $this->assertSame('2027-01-01 16:59:59', $period->getRawOriginal('closes_at'));
        foreach (['2026-12-31T16:59:59.999999Z' => false, '2026-12-31T17:00:00Z' => true, '2027-01-01T16:59:59.999999Z' => true, '2027-01-01T17:00:00Z' => false] as $moment => $expected) {
            $this->assertSame($expected, $period->acceptsSubmissions(CarbonImmutable::parse($moment)), $moment);
        }
        SubmissionWindow::assertOpen($period, CarbonImmutable::parse('2027-01-01T00:00:00Z'));
        $period->status = 'closed';
        try { SubmissionWindow::assertOpen($period, CarbonImmutable::parse('2027-01-01T00:00:00Z')); $this->fail('Closed window accepted submission.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('submission_period_id', $error->errors()); }
        $period->status = 'draft'; $this->assertFalse($period->acceptsSubmissions(CarbonImmutable::parse('2027-01-01T00:00:00Z')));
    }

    public function test_period_year_is_frozen_after_use_and_stale_period_updates_are_rejected(): void
    {
        $owner = $this->actor('skpd_operator', $this->organization()); $submission = $this->submission($owner);
        $id = $submission['period_id']; $this->actingAs($this->actor('bkad'));
        $this->patchJson('/periode/'.$id, $this->periodData() + ['record_version' => $this->periodVersion($id)])->assertUnprocessable()->assertJsonValidationErrors('budget_year');
        $data = array_replace($this->periodData(), ['budget_year' => 2026, 'record_version' => $this->periodVersion($id)]);
        $this->patchJson('/periode/'.$id, $data)->assertOk();
        $this->patchJson('/periode/'.$id, array_replace($data, ['status' => 'closed']))->assertStatus(409);
        $this->assertSame('open', SubmissionPeriod::findOrFail($id)->status);
    }

    public function test_reference_codes_remain_text_and_duplicates_are_rejected_per_type(): void
    {
        $this->actingAs($this->actor('admin'));
        foreach (ReferenceCatalog::TYPES as $kind => $type) {
            $data = ['code' => '001.020', 'name' => 'Kode simulasi', 'is_active' => true];
            $id = $this->postJson('/referensi/'.$kind, $data)->assertCreated()->json('id');
            $this->assertSame('001.020', DB::table($type['table'])->where('id', $id)->value('code'));
            $this->postJson('/referensi/'.$kind, $data)->assertUnprocessable()->assertJsonValidationErrors('code');
            $this->postJson('/referensi/'.$kind, array_replace($data, ['code' => 1020]))->assertUnprocessable()->assertJsonValidationErrors('code');
            $this->get('/referensi/'.$kind.'?q=001.020')->assertOk()->assertSee('001.020');
        }
        $this->get('/referensi/users')->assertNotFound();
    }

    public function test_unused_reference_deletion_is_audited_and_rejects_stale_forms(): void
    {
        $this->actingAs($this->actor('admin'));
        foreach (ReferenceCatalog::TYPES as $kind => $type) {
            $data = ['code' => '001', 'name' => 'Referensi hapus simulasi', 'is_active' => true];
            $id = $this->postJson('/referensi/'.$kind, $data)->assertCreated()->json('id');
            $oldVersion = $this->referenceVersion($kind, $id);
            $this->patchJson('/referensi/'.$kind.'/'.$id, array_replace($data, ['name' => 'Nama baru', 'record_version' => $oldVersion]))->assertOk();
            $this->deleteJson('/referensi/'.$kind.'/'.$id, ['record_version' => $oldVersion])->assertStatus(409);
            $this->deleteJson('/referensi/'.$kind.'/'.$id, ['record_version' => $this->referenceVersion($kind, $id)])->assertOk();
            $this->assertDatabaseMissing($type['table'], ['id' => $id]);
            $this->assertDatabaseHas('activity_logs', ['action' => 'reference.deleted', 'subject_type' => $type['table'], 'subject_id' => $id]);
        }
    }

    public function test_used_references_cannot_be_deleted_and_edits_leave_submitted_and_final_snapshots_intact(): void
    {
        $owner = $this->actor('skpd_operator', $this->organization()); $f = $this->submission($owner);
        $examiner = $this->actor('examiner'); $scope = $this->scope($f, $examiner); $kkr = $this->kkr($scope, $examiner);
        $snapshot = ['code' => '001.020', 'name' => 'Nama arsip simulasi'];
        DB::table('proposal_item_versions')->where('id', $f['version']->id)->update(['reference_snapshot' => json_encode($snapshot)]);
        DB::table('kkr_versions')->where('id', $kkr['version']->id)->update(['snapshot' => json_encode($snapshot), 'finalized_at' => now()]);
        $storedItem = DB::table('proposal_item_versions')->where('id', $f['version']->id)->value('reference_snapshot');
        $storedKkr = DB::table('kkr_versions')->where('id', $kkr['version']->id)->value('snapshot');
        $account = DB::table('account_codes')->insertGetId(['code' => '001.020', 'name' => 'Rekening simulasi']);
        DB::table('item_version_accounts')->insert(['item_version_id' => $f['version']->id, 'account_code_id' => $account, 'code_snapshot' => '001.020', 'name_snapshot' => 'Rekening arsip']);
        $ids = ['barang' => $f['version']->item_classification_id, 'satuan' => $f['version']->unit_id, 'rekening' => $account];
        $this->actingAs($this->actor('bkad'));
        foreach ($ids as $kind => $id) {
            $version = $this->referenceVersion($kind, $id);
            $this->deleteJson('/referensi/'.$kind.'/'.$id, ['record_version' => $version])->assertUnprocessable()->assertJsonValidationErrors('reference');
            $this->patchJson('/referensi/'.$kind.'/'.$id, ['code' => '009.000', 'name' => 'Nama terbaru', 'is_active' => false, 'record_version' => $version])->assertOk();
        }
        $this->assertSame($storedItem, DB::table('proposal_item_versions')->where('id', $f['version']->id)->value('reference_snapshot'));
        $this->assertSame($storedKkr, DB::table('kkr_versions')->where('id', $kkr['version']->id)->value('snapshot'));
        $this->assertDatabaseHas('item_version_accounts', ['item_version_id' => $f['version']->id, 'code_snapshot' => '001.020', 'name_snapshot' => 'Rekening arsip']);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'reference.deleted']);
    }

    public function test_filter_pagination_empty_states_and_no_change_updates(): void
    {
        $this->actingAs($this->actor('admin'));
        for ($i = 0; $i < 17; $i++) { DB::table('units')->insert(['code' => 'U'.str_pad((string)$i, 3, '0', STR_PAD_LEFT), 'name' => 'Satuan simulasi', 'is_active' => $i === 0 ? false : true]); }
        $this->get('/referensi/satuan')->assertOk()->assertSee('Berikutnya');
        $this->get('/referensi/satuan?status=inactive')->assertOk()->assertSee('U000')->assertDontSee('U001');
        $this->get('/referensi/satuan?q=missing')->assertOk()->assertSee('Belum ada referensi yang sesuai');
        DB::table('units')->insert(['code' => 'ABC', 'name' => 'Nama huruf saja']);
        $this->get('/referensi/satuan?page=100')->assertOk()->assertSee('Menampilkan 0 dari 18 referensi')->assertSee('Halaman pertama');
        $this->get('/referensi/satuan?q=0')->assertOk()->assertDontSee('Nama huruf saja');
        $this->get('/referensi/satuan?q=%25')->assertOk()->assertSee('Belum ada referensi yang sesuai');
        $row = DB::table('units')->first();
        $this->patchJson('/referensi/satuan/'.$row->id, ['code' => $row->code, 'name' => $row->name, 'is_active' => $row->is_active, 'record_version' => $this->referenceVersion('satuan',$row->id)])->assertOk();
        $this->get('/periode?q=missing')->assertOk()->assertSee('Belum ada periode yang sesuai');
        DB::table('submission_periods')->insert(['name' => 'Alpha simulasi', 'budget_year' => 2027, 'opens_at' => '2026-12-01', 'closes_at' => '2027-01-31', 'created_by' => auth()->id()]);
        $this->get('/periode?q=2027junk')->assertOk()->assertSee('Belum ada periode yang sesuai');
        $this->get('/periode?q=2027')->assertOk()->assertSee('Alpha simulasi');
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_html_validation_preserves_values_and_creation_version_errors_do_not_render_invalid_reload_links(): void
    {
        $this->actingAs($this->actor('admin'));
        $this->from('/periode/buat')->post('/periode', $this->periodData() + ['record_version' => 'invalid'])->assertRedirect('/periode/buat')->assertSessionHasErrors('record_version');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/periode/buat')->assertOk();
        $this->assertTrue(str_contains($response->getContent(), 'Periksa kembali isian Anda.'));
        $this->assertTrue(str_contains($response->getContent(), 'value="2026-12-01"'));
        $this->from('/referensi/barang/buat')->post('/referensi/barang', ['code' => '001', 'name' => 'Nama simulasi', 'is_active' => true, 'record_version' => 'invalid'])->assertRedirect('/referensi/barang/buat')->assertSessionHasErrors('record_version');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/referensi/barang/buat')->assertOk();
    }

    public function test_catalog_mutations_require_csrf(): void
    {
        $this->actingAs($this->actor('admin')); $this->app->instance('env', 'production');
        $this->post('/periode', $this->periodData())->assertStatus(419);
        $this->post('/referensi/barang', ['code' => '001', 'name' => 'Simulasi', 'is_active' => true])->assertStatus(419);
    }
}
