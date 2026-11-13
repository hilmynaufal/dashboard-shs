<?php

namespace Tests\Feature;

use App\Models\{Proposal, ProposalItem};
use App\Support\{ItemVerification, ProposalDraft, RecordVersion};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\Support\MySqlAccessTestCase;

class VerificationWorkflowTest extends MySqlAccessTestCase
{
    private $operator;
    private $bkad;
    private int $period;
    private int $proposal;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->operator = $this->actor('skpd_operator', $this->organization());
        $this->bkad = $this->actor('bkad');
        $this->actingAs($this->operator);
        $this->period = DB::table('submission_periods')->insertGetId(['budget_year'=>2027, 'name'=>'Periode verifikasi simulasi', 'opens_at'=>now()->subDay(), 'closes_at'=>now()->addDay(), 'status'=>'open', 'created_by'=>$this->operator->id]);
        DB::table('item_classifications')->insert(['code'=>'000.010', 'name'=>'Klasifikasi simulasi']);
        DB::table('units')->insert(['code'=>'BH', 'name'=>'Buah']);
        DB::table('account_codes')->insert(['code'=>'0', 'name'=>'Rekening simulasi']);
        $this->proposal = $this->postJson('/usulan', ['title'=>'Paket verifikasi simulasi', 'type'=>'SHS', 'submission_period_id'=>$this->period])->assertCreated()->json('id');
    }

    private function fields(array $changes = []): array
    {
        return array_replace(['name'=>'Item verifikasi simulasi', 'spec'=>'Spesifikasi lengkap', 'code'=>'000.010', 'unit'=>'BH', 'account'=>'0', 'price'=>'1250,50'], $changes);
    }

    private function latest(int $item): object { return ItemVerification::latest($item); }
    private function actorAs($user): void { $this->flushSession(); $this->actingAs($user); }
    private function token(int $item): string { return ItemVerification::token(ProposalItem::findOrFail($item)); }

    private function upload(int $item, string $name = 'awal'): int
    {
        return $this->post('/usulan/'.$this->proposal.'/item/'.$item.'/bukti', [
            'file'=>UploadedFile::fake()->createWithContent($name.'.pdf', "%PDF-1.4\n% synthetic ".$name."\n1 0 obj <</Type /Catalog>> endobj\n%%EOF"),
            'record_version'=>RecordVersion::of((array)$this->latest($item)),
        ], ['Accept'=>'application/json'])->assertCreated()->json('id');
    }

    private function item(string $name = 'Item verifikasi simulasi'): int
    {
        $version = $this->postJson('/usulan/'.$this->proposal.'/item', $this->fields(['name'=>$name]) + [
            'record_version'=>ProposalDraft::version(Proposal::findOrFail($this->proposal)),
        ])->assertCreated()->json('version_id');
        $item = DB::table('proposal_item_versions')->where('id',$version)->value('proposal_item_id');
        $this->upload($item);

        return $item;
    }

    private function submit(): void
    {
        $this->postJson('/usulan/'.$this->proposal.'/kirim', ['confirm'=>true, 'record_version'=>ProposalDraft::version(Proposal::findOrFail($this->proposal))])->assertOk();
    }

    private function decide(int $item, string $result = 'needs_completion', string $reason = 'Lengkapi bukti survei simulasi')
    {
        return $this->postJson('/verifikasi/'.$item, ['result'=>$result, 'reason'=>$reason, 'record_version'=>$this->token($item)]);
    }

    private function edit(int $item, array $changes = []): void
    {
        $this->patchJson('/usulan/'.$this->proposal.'/item/'.$item, $this->fields($changes) + [
            'record_version'=>RecordVersion::of((array)$this->latest($item)),
        ])->assertOk();
    }

    private function resubmit(int $item, ?string $token = null)
    {
        return $this->postJson('/usulan/'.$this->proposal.'/item/'.$item.'/kelengkapan', ['confirm'=>true, 'record_version'=>$token ?? $this->token($item)]);
    }

    public function test_bkad_queue_and_detail_show_only_submitted_versions_and_private_evidence(): void
    {
        $item=$this->item();$file=DB::table('evidence_files')->first();
        $this->actorAs($this->bkad);
        $this->get('/verifikasi')->assertOk()->assertDontSee('Item verifikasi simulasi');
        $this->get('/verifikasi/'.$item)->assertForbidden();
        $this->actorAs($this->operator);$this->submit();$this->actorAs($this->bkad);
        $this->get('/verifikasi')->assertOk()->assertSee('Item verifikasi simulasi')->assertSee('Versi terkirim');
        $this->get('/verifikasi/'.$item)->assertOk()->assertSee('awal.pdf')->assertSee('Spesifikasi lengkap')->assertSee('1.250,50');
        $this->get('/bukti/'.$file->id)->assertOk();
        $this->get('/usulan/'.$this->proposal)->assertOk()->assertSee('Verifikasi');
    }

    public function test_local_demo_completion_can_be_resubmitted_without_evidence(): void
    {
        // These tests change the app environment; keep the usual test-only CSRF bypass.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->app->instance('env','local'); config(['demo.allow_missing_evidence'=>true]);
        $version=$this->postJson('/usulan/'.$this->proposal.'/item',$this->fields()+[
            'record_version'=>ProposalDraft::version(Proposal::findOrFail($this->proposal)),
        ])->assertCreated()->json('version_id');
        $item=(int)DB::table('proposal_item_versions')->where('id',$version)->value('proposal_item_id');
        $this->submit(); $this->actorAs($this->bkad); $this->decide($item)->assertOk();
        $this->actorAs($this->operator); $this->edit($item,['name'=>'Perbaikan data demo']);
        $this->get('/usulan/'.$this->proposal.'/item/'.$item.'/kelengkapan')->assertOk()
            ->assertSee('Demo lokal: bukti pendukung boleh dikosongkan.')->assertSee('Saya telah memeriksa data kelengkapan item ini.');
        $this->resubmit($item)->assertOk();
        $this->assertDatabaseCount('evidence_files',0);
        $this->actorAs($this->bkad); $this->decide($item,'ready','Pemeriksaan demo')->assertOk();
        $this->assertDatabaseHas('proposal_items',['id'=>$item,'status'=>'ready']);
    }

    public function test_return_requires_reason_and_stale_or_invalid_decisions_never_write_history(): void
    {
        $item=$this->item();$this->submit();$this->actorAs($this->bkad);
        foreach (['', '   '] as $reason) { $this->decide($item,'needs_completion',$reason)->assertUnprocessable()->assertJsonValidationErrors('reason'); }
        $this->postJson('/verifikasi/'.$item,['result'=>'invalid','record_version'=>$this->token($item)])->assertUnprocessable();
        $this->postJson('/verifikasi/'.$item,['result'=>'ready','record_version'=>str_repeat('0',64)])->assertStatus(409);
        $this->assertDatabaseCount('verification_results',0);
        $this->decide($item,'needs_completion','<script>simulasi</script> Lengkapi sumber harga')->assertOk();
        $this->decide($item,'ready')->assertForbidden();$this->assertDatabaseCount('verification_results',1);
        $this->actorAs($this->operator);
        $this->get('/usulan/'.$this->proposal)->assertOk()->assertSee('Lengkapi sumber harga')->assertDontSee('<script>simulasi</script>',false);
        $this->get('/usulan/'.$this->proposal.'/item/'.$item.'/ubah')->assertOk()->assertSee('Item dikembalikan');
        $this->get('/usulan/'.$this->proposal.'/item/'.$item.'/kelengkapan')->assertOk()->assertSee('Simpan perbaikan data atau bukti');
    }

    public function test_ready_items_are_independent_of_returned_siblings_and_only_the_returned_item_can_change(): void
    {
        $ready=$this->item('Item siap');$returned=$this->item('Item perlu bukti');$waiting=$this->item('Item belum diperiksa');$this->submit();
        $this->actorAs($this->bkad);$this->decide($ready,'ready','Lengkap')->assertOk();$this->decide($returned)->assertOk();
        $this->assertSame([$ready],ItemVerification::readyForAssignment()->pluck('item.id')->all());
        $this->get('/verifikasi?status=ready')->assertOk()->assertSee('<strong>Item siap</strong>',false)->assertDontSee('<strong>Item perlu bukti</strong>',false);
        $this->get('/verifikasi?status=needs_completion')->assertOk()->assertSee('<strong>Item perlu bukti</strong>',false)->assertDontSee('<strong>Item siap</strong>',false);
        $this->actorAs($this->operator);
        $this->get('/usulan/'.$this->proposal.'/item/'.$returned.'/ubah')->assertOk();
        foreach ([$ready,$waiting] as $id) {
            $this->get('/usulan/'.$this->proposal.'/item/'.$id.'/ubah')->assertForbidden();
            $this->patchJson('/usulan/'.$this->proposal.'/item/'.$id,$this->fields()+['record_version'=>RecordVersion::of((array)$this->latest($id))])->assertForbidden();
            $this->resubmit($id)->assertForbidden();
        }
        $this->postJson('/usulan/'.$this->proposal.'/item',$this->fields()+['record_version'=>ProposalDraft::version(Proposal::findOrFail($this->proposal))])->assertForbidden();
        $this->get('/usulan/'.$this->proposal.'/impor')->assertForbidden();
        $this->postJson('/usulan/'.$this->proposal.'/kirim',['confirm'=>true,'record_version'=>ProposalDraft::version(Proposal::findOrFail($this->proposal))])->assertForbidden();
        $this->assertDatabaseHas('proposal_items',['id'=>$ready,'status'=>'ready']);
    }

    public function test_completion_versions_and_evidence_remain_private_until_resubmitted_and_bkad_can_reverify(): void
    {
        $item=$this->item();$old=$this->latest($item);$oldFile=DB::table('evidence_files')->first();$this->submit();
        $this->actorAs($this->bkad);$this->decide($item)->assertOk();
        $this->actorAs($this->operator);$this->edit($item,['name'=>'Perbaikan belum dikirim']);$newFile=$this->upload($item,'tambahan');$new=$this->latest($item);
        $this->actorAs($this->bkad);
        $this->get('/verifikasi/'.$item)->assertOk()->assertSee('Item verifikasi simulasi')->assertDontSee('Perbaikan belum dikirim')->assertDontSee('tambahan.pdf');
        $this->get('/usulan/'.$this->proposal)->assertOk()->assertDontSee('Perbaikan belum dikirim');
        $this->get('/bukti/'.$newFile)->assertForbidden();$this->get('/bukti/'.$oldFile->id)->assertOk();
        $this->get('/usulan/'.$this->proposal.'/item/'.$item.'/versi/'.$new->id)->assertForbidden();
        $this->actorAs($this->operator);$this->get('/usulan/'.$this->proposal.'/item/'.$item.'/kelengkapan')->assertOk()->assertSee('tambahan.pdf');
        $this->resubmit($item)->assertOk();$this->resubmit($item)->assertForbidden();
        $this->assertDatabaseHas('proposal_items',['id'=>$item,'status'=>'submitted']);
        $this->assertNotNull(DB::table('proposal_item_versions')->where('id',$new->id)->value('submitted_at'));
        $this->assertDatabaseHas('verification_results',['item_version_id'=>$old->id,'result'=>'needs_completion']);
        $this->assertDatabaseHas('proposal_item_versions',['id'=>$old->id,'description'=>'Item verifikasi simulasi']);
        $this->actorAs($this->bkad);$this->get('/verifikasi/'.$item)->assertOk()->assertSee('Perbaikan belum dikirim')->assertSee('tambahan.pdf');$this->get('/bukti/'.$newFile)->assertOk();
        $this->decide($item,'ready','Lengkap setelah perbaikan')->assertOk();$this->assertDatabaseCount('verification_results',2);
        $this->get('/verifikasi/'.$item)->assertOk()->assertSee('Lengkap setelah perbaikan')->assertSee('Lengkapi bukti survei simulasi');
    }

    public function test_resubmission_rejects_unchanged_incomplete_stale_and_closed_period_requests(): void
    {
        $item=$this->item();$this->submit();$this->actorAs($this->bkad);$this->decide($item)->assertOk();$this->actorAs($this->operator);
        $this->resubmit($item)->assertUnprocessable()->assertJsonValidationErrors('completion');
        $stale=$this->token($item);$this->edit($item,['spec'=>'']);
        $this->resubmit($item,$stale)->assertStatus(409);$this->resubmit($item)->assertUnprocessable()->assertJsonValidationErrors('completion');
        $this->edit($item);DB::table('submission_periods')->where('id',$this->period)->update(['status'=>'closed']);
        $this->resubmit($item)->assertUnprocessable()->assertJsonValidationErrors('submission_period_id');
        $this->assertDatabaseHas('proposal_items',['id'=>$item,'status'=>'needs_completion']);
        $this->assertNull($this->latest($item)->submitted_at);
        DB::table('submission_periods')->where('id',$this->period)->update(['status'=>'open']);$this->resubmit($item)->assertOk();
    }

    public function test_non_bkad_roles_and_other_skpd_cannot_verify_or_change_returned_items_even_by_direct_url(): void
    {
        $item=$this->item();$this->submit();$this->actorAs($this->bkad);$this->decide($item)->assertOk();
        foreach (['admin','examiner','team_lead','technical_controller','skpd_operator'] as $role) {
            $user=$role==='skpd_operator'?$this->actor($role,$this->organization()):$this->actor($role);$this->actorAs($user);
            $this->get('/verifikasi')->assertForbidden();$this->get('/verifikasi/'.$item)->assertForbidden();$this->decide($item,'ready')->assertForbidden();
        }
        $this->get('/usulan/'.$this->proposal.'/item/'.$item.'/kelengkapan')->assertForbidden();
        $this->get('/usulan/'.$this->proposal.'/item/'.$item.'/ubah')->assertForbidden();
        $this->patchJson('/usulan/'.$this->proposal.'/item/'.$item,$this->fields()+['record_version'=>RecordVersion::of((array)$this->latest($item))])->assertForbidden();
        $this->resubmit($item)->assertForbidden();
        $this->assertDatabaseCount('verification_results',1);
    }

    public function test_ready_decision_requires_available_evidence_and_active_references(): void
    {
        $item=$this->item();$this->submit();$this->actorAs($this->bkad);
        DB::table('units')->where('code','BH')->update(['is_active'=>false]);$this->decide($item,'ready')->assertUnprocessable()->assertJsonValidationErrors('completion');
        DB::table('units')->where('code','BH')->update(['is_active'=>true]);$file=DB::table('evidence_files')->first();Storage::disk('local')->delete($file->path);
        $this->decide($item,'ready')->assertUnprocessable()->assertJsonValidationErrors('completion');
        $this->assertDatabaseCount('verification_results',0);$this->decide($item)->assertOk();
    }

    public function test_resubmit_permission_does_not_require_edit_permission_and_unchanged_siblings_keep_their_versions(): void
    {
        $item=$this->item();$sibling=$this->item('Item siap terpisah');$siblingVersion=$this->latest($sibling);$this->submit();
        $this->actorAs($this->bkad);$this->decide($item)->assertOk();$this->decide($sibling,'ready')->assertOk();
        $this->actorAs($this->operator);$this->edit($item,['spec'=>'Spesifikasi dilengkapi']);
        DB::table('permission_role')->where('role_id',DB::table('roles')->where('code','skpd_operator')->value('id'))
            ->where('permission_id',DB::table('permissions')->where('code','proposals.update_own')->value('id'))->delete();
        $this->get('/usulan/'.$this->proposal.'/item/'.$item.'/ubah')->assertForbidden();
        $this->get('/usulan/'.$this->proposal.'/item/'.$item.'/kelengkapan')->assertOk()->assertSee('Kirim kelengkapan');$this->resubmit($item)->assertOk();
        $this->assertSame($siblingVersion->id,$this->latest($sibling)->id);$this->assertDatabaseHas('proposal_items',['id'=>$sibling,'status'=>'ready']);
        $this->assertSame([$sibling],ItemVerification::readyForAssignment()->pluck('item.id')->all());
    }

    public function test_csrf_and_revoked_permissions_protect_decisions_and_resubmission(): void
    {
        $item=$this->item();$this->submit();$this->actorAs($this->bkad);$this->decide($item)->assertOk();
        $this->actorAs($this->operator);$this->edit($item);$this->app->instance('env','production');
        $this->post('/usulan/'.$this->proposal.'/item/'.$item.'/kelengkapan',['confirm'=>true,'record_version'=>$this->token($item)])->assertStatus(419);
        $this->actorAs($this->bkad);$this->post('/verifikasi/'.$item,['result'=>'ready','record_version'=>$this->token($item)])->assertStatus(419);
        $this->app->instance('env','testing');DB::table('role_user')->where('user_id',$this->bkad->id)->delete();
        $this->get('/verifikasi')->assertForbidden();
    }
}
