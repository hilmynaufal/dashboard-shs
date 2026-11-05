<?php

namespace Tests\Feature;

use App\Models\{KkrDocument,KkrVersion,ReviewAssignment};
use App\Support\{AccountActivity,CatalogAudit,KkrWork};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class KkrArchiveActivityTest extends MySqlAccessTestCase
{
    private $owner;private $examiner;private $lead;private $controller;private $admin;private array $f;private array $scope;
    protected function setUp(): void
    {
        parent::setUp();Storage::fake('local');$this->owner=$this->actor('skpd_operator',$this->organization());$this->examiner=$this->actor('examiner');$this->lead=$this->actor('team_lead');$this->controller=$this->actor('technical_controller');$this->admin=$this->actor('admin');
        $this->examiner->name='Pemeriksa arsip simulasi';$this->examiner->save();$this->lead->name='Ketua arsip simulasi';$this->lead->save();$this->controller->name='Pengendali arsip simulasi';$this->controller->save();
        $this->f=$this->submission($this->owner);$this->scope=$this->scope($this->f,$this->examiner,$this->lead,$this->controller);
        DB::table('proposals')->where('id',$this->f['proposal']->id)->update(['status'=>'review']);DB::table('proposal_items')->where('id',$this->f['item']->id)->update(['status'=>'reviewed']);
        DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'completed','completed_at'=>now()]);
        foreach(DB::table('review_aspects')->where('is_active',true)->get() as $a){DB::table('review_results')->insert(['item_review_id'=>$this->scope['review']->id,'review_aspect_id'=>$a->id,'result'=>'compliant','criterion_snapshot'=>json_encode(['code'=>$a->code,'name'=>$a->name,'is_required'=>(bool)$a->is_required]),'reason'=>'Aspek arsip simulasi']);}
    }
    private function asActor($actor): void {$this->flushSession();$this->actingAs($actor);}
    private function version(KkrDocument $doc): KkrVersion {return KkrWork::latest($doc->fresh());}
    private function document(): KkrDocument
    {
        $this->asActor($this->examiner);$a=ReviewAssignment::findOrFail($this->scope['assignment_id']);
        $id=$this->postJson('/penugasan/'.$a->id.'/kkr',['record_version'=>KkrWork::createToken($this->examiner,$a),'number'=>'SIM-ARSIP-'.Str::uuid(),'title'=>'KKR final simulasi',
            'purpose'=>'Tujuan arsip simulasi','work_steps'=>'Langkah arsip simulasi','conclusion'=>'Kesimpulan awal simulasi','recommendation'=>'Rekomendasi awal simulasi','reviews'=>[$this->scope['review']->id]])->assertCreated()->json('id');
        return KkrDocument::findOrFail($id);
    }
    private function seal(KkrDocument $doc): void
    {
        $this->asActor($this->examiner);$this->postJson('/kkr/'.$doc->id.'/ajukan',['record_version'=>KkrWork::token($doc->fresh()),'version_id'=>$this->version($doc)->id,'confirm'=>1])->assertOk();
        foreach(['team_lead'=>$this->lead,'technical_controller'=>$this->controller] as $level=>$actor){$this->asActor($actor);$this->postJson('/kkr/'.$doc->id.'/versi/'.$this->version($doc)->id.'/keputusan',['record_version'=>KkrWork::token($doc->fresh()),'level'=>$level,'decision'=>'approved','notes'=>'Persetujuan arsip simulasi'])->assertOk();}
    }
    private function correction(KkrDocument $doc,array $extra=[])
    {
        $this->asActor($this->examiner);return $this->postJson('/kkr/'.$doc->id.'/koreksi',array_replace(['record_version'=>KkrWork::token($doc->fresh()),'title'=>'KKR koreksi simulasi','conclusion'=>'Kesimpulan koreksi simulasi','recommendation'=>'Rekomendasi koreksi simulasi',
            'purpose'=>'Tujuan koreksi simulasi','work_steps'=>'Langkah koreksi simulasi','change_reason'=>'Alasan koreksi simulasi','reviews'=>[$this->scope['review']->id]],$extra));
    }
    private function word(KkrDocument $doc,KkrVersion $version): string {return $this->get('/kkr/'.$doc->id.'/versi/'.$version->id.'/word')->assertOk()->getContent();}

    public function test_final_archive_contains_frozen_identity_assignment_date_and_same_version_approvers(): void
    {
        $doc=$this->document();$this->seal($doc);$v=$this->version($doc);$this->asActor($this->lead);
        $page=$this->get('/arsip-kkr')->assertOk()->assertSee('KKR final simulasi')->assertSee('Final berlaku');$this->assertSame(1,$page->viewData('versions')->total());
        $this->get('/arsip-kkr/'.$v->id)->assertOk()->assertSee($doc->number)->assertSee('Ketua arsip simulasi')->assertSee('Pengendali arsip simulasi')->assertSee('Finalisasi')->assertSee('Persetujuan versi ini');
        $this->assertDatabaseCount('kkr_exports',1);$this->assertDatabaseCount('kkr_approvals',2);
    }

    public function test_correction_appends_new_draft_keeps_old_final_and_requires_new_two_level_approval(): void
    {
        $doc=$this->document();$this->seal($doc);$first=$this->version($doc);$original=$first->snapshot;$hash=$first->snapshot_sha256;$before=$this->word($doc,$first);
        $this->asActor($this->examiner);$this->get('/kkr/'.$doc->id.'/koreksi')->assertOk()->assertSee('Siapkan koreksi KKR')->assertSee('Dipertahankan')->assertSee('name="change_reason"',false);
        $this->correction($doc)->assertOk();$next=$this->version($doc);$this->assertSame(2,(int)$next->version_number);$this->assertSame((int)$first->id,(int)$next->supersedes_version_id);
        $this->assertSame('draft',$doc->fresh()->status);$this->assertNull($next->finalized_at);$this->assertSame('Alasan koreksi simulasi',$next->change_reason);$this->assertDatabaseCount('kkr_approvals',2);
        $this->assertSame($before,$this->word($doc,$first));$this->assertSame($original,$first->fresh()->snapshot);$this->assertSame($hash,$first->fresh()->snapshot_sha256);
        $this->get('/arsip-kkr/'.$first->id)->assertOk()->assertSee('Koreksi sedang diproses')->assertSee('KKR final simulasi')->assertDontSee('KKR koreksi simulasi');
        $this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Versi koreksi ini belum final.');
        $this->asActor($this->controller);$this->postJson('/kkr/'.$doc->id.'/versi/'.$next->id.'/keputusan',['record_version'=>KkrWork::token($doc->fresh()),'level'=>'technical_controller','decision'=>'approved'])->assertForbidden();
        $this->seal($doc);$this->assertDatabaseCount('kkr_exports',2);$this->assertDatabaseCount('kkr_approvals',4);
        $this->get('/arsip-kkr')->assertOk()->assertSee('Digantikan versi 2')->assertSee('KKR final simulasi')->assertSee('KKR koreksi simulasi');
        $archivePage=$this->get('/arsip-kkr/'.$next->id)->assertOk()->assertSee('Alasan koreksi simulasi');$this->assertSame('Kesimpulan koreksi simulasi',$archivePage->viewData('print')['conclusion']);
        $this->assertSame($before,$this->word($doc,$first));$this->assertNotSame($before,$this->word($doc,$next));
    }

    public function test_correction_rejects_blank_reason_changed_item_scope_forged_snapshot_and_ordinary_final_edits(): void
    {
        $doc=$this->document();$this->seal($doc);$v=$this->version($doc);
        $this->correction($doc,['change_reason'=>''])->assertUnprocessable();$this->correction($doc,['reviews'=>[999999]])->assertUnprocessable();$this->correction($doc,['snapshot'=>['forged'=>true]])->assertUnprocessable();
        $this->assertDatabaseCount('kkr_versions',1);$this->assertSame('final',$doc->fresh()->status);
        $this->asActor($this->examiner);$this->get('/kkr/'.$doc->id.'/ubah')->assertForbidden();$this->postJson('/kkr/'.$doc->id.'/versi',['record_version'=>KkrWork::token($doc->fresh())])->assertForbidden();
        $this->deleteJson('/kkr/'.$doc->id)->assertMethodNotAllowed();$this->deleteJson('/arsip-kkr/'.$v->id)->assertMethodNotAllowed();$this->patchJson('/arsip-kkr/'.$v->id,[])->assertMethodNotAllowed();
        $this->assertDatabaseCount('kkr_exports',1);
    }

    public function test_correction_return_can_be_revised_without_reusing_old_or_intermediate_decisions(): void
    {
        $doc=$this->document();$this->seal($doc);$first=$this->version($doc);$this->correction($doc)->assertOk();$draft=$this->version($doc);
        $this->asActor($this->examiner);$this->postJson('/kkr/'.$doc->id.'/ajukan',['record_version'=>KkrWork::token($doc->fresh()),'version_id'=>$draft->id,'confirm'=>1])->assertOk();
        $this->asActor($this->lead);$this->postJson('/kkr/'.$doc->id.'/versi/'.$draft->id.'/keputusan',['record_version'=>KkrWork::token($doc->fresh()),'level'=>'team_lead','decision'=>'returned','notes'=>'Lengkapi narasi koreksi'])->assertOk();
        $this->get('/aktivitas')->assertOk()->assertSee('KKR dikembalikan dalam supervisi');
        $this->asActor($this->examiner);$this->get('/kkr/'.$doc->id.'/ubah')->assertOk()->assertSee('Dipertahankan');
        $this->postJson('/kkr/'.$doc->id.'/versi',['record_version'=>KkrWork::token($doc->fresh()),'title'=>'Koreksi diperbaiki','conclusion'=>'Kesimpulan diperbaiki','recommendation'=>'Rekomendasi diperbaiki','change_reason'=>'Tindak lanjut pengembalian','reviews'=>[$this->scope['review']->id]])->assertOk();
        $this->seal($doc);$this->assertSame(3,(int)$this->version($doc)->version_number);$this->assertDatabaseCount('kkr_approvals',5);$this->assertNotNull($first->fresh()->finalized_at);$this->assertNull($draft->fresh()->finalized_at);$this->assertDatabaseCount('kkr_exports',2);
    }

    public function test_outside_team_admin_bkad_and_skpd_cannot_open_archives_or_correct_final(): void
    {
        $doc=$this->document();$this->seal($doc);$v=$this->version($doc);
        foreach([$this->owner,$this->admin,$this->actor('bkad'),$this->actor('examiner'),$this->actor('team_lead')] as $actor){
            $this->asActor($actor);$this->getJson('/arsip-kkr/'.$v->id)->assertForbidden();$this->getJson('/kkr/'.$doc->id.'/koreksi')->assertForbidden();$this->postJson('/kkr/'.$doc->id.'/koreksi',[])->assertForbidden();
        }
        $this->asActor($this->lead);$this->get('/arsip-kkr')->assertOk();$this->getJson('/kkr/'.$doc->id.'/koreksi')->assertForbidden();
        $this->asActor($this->examiner);DB::table('assignment_members')->where('id',$this->scope['members']['examiner'])->update(['is_active'=>false]);
        $this->getJson('/arsip-kkr/'.$v->id)->assertForbidden();$this->assertSame(0,$this->get('/arsip-kkr')->assertOk()->viewData('versions')->total());
    }

    public function test_stale_or_duplicate_correction_and_revoked_archive_grant_do_not_create_extra_versions(): void
    {
        $doc=$this->document();$this->seal($doc);$token=KkrWork::token($doc->fresh());$this->correction($doc,['record_version'=>str_repeat('0',64)])->assertConflict();
        $this->correction($doc,['record_version'=>$token])->assertOk();$this->correction($doc,['record_version'=>$token])->assertForbidden();$this->assertDatabaseCount('kkr_versions',2);
        $role=DB::table('roles')->where('code','examiner')->value('id');$permission=DB::table('permissions')->where('code','archives.assigned')->value('id');DB::table('permission_role')->where('role_id',$role)->where('permission_id',$permission)->delete();
        $this->asActor($this->examiner);$this->getJson('/arsip-kkr')->assertForbidden();$this->getJson('/kkr/'.$doc->id.'/ubah')->assertForbidden();$this->get('/kkr/'.$doc->id)->assertOk()->assertDontSee('Siapkan versi baru');
    }

    public function test_closed_assignment_and_corrupt_archive_block_correction_without_mutating_final(): void
    {
        $doc=$this->document();$this->seal($doc);$v=$this->version($doc);$archive=DB::table('kkr_exports')->first();Storage::disk('local')->put($archive->path,'corrupt');
        $this->correction($doc)->assertConflict();$this->assertSame('final',$doc->fresh()->status);$this->assertDatabaseCount('kkr_versions',1);
        DB::table('review_assignments')->where('id',$doc->review_assignment_id)->update(['status'=>'completed']);$this->asActor($this->examiner);$this->getJson('/kkr/'.$doc->id.'/koreksi')->assertForbidden();
    }

    public function test_technical_admin_activity_projection_never_exposes_business_payload_or_kkrs(): void
    {
        $doc=$this->document();$this->seal($doc);AccountActivity::record($this->admin->id,'account.status_updated',$this->owner->id,null,['is_active'=>true,'password'=>'SYNTHETIC-SECRET-DO-NOT-RENDER']);
        CatalogAudit::record($this->examiner->id,'unrecognized.action','user',$this->owner->id,null,['secret'=>'UNKNOWN-SECRET']);
        $this->asActor($this->admin);$page=$this->get('/aktivitas')->assertOk()->assertSee('Status akun diubah')->assertDontSee('SYNTHETIC-SECRET-DO-NOT-RENDER')->assertDontSee('UNKNOWN-SECRET')->assertDontSee('KKR difinalisasi');
        $this->assertSame(1,$page->viewData('events')->total());$event=$page->viewData('events')->first();$this->assertFalse(property_exists($event,'after_values'));$this->assertFalse(property_exists($event,'before_values'));
        $this->getJson('/kkr/'.$doc->id)->assertForbidden();$this->getJson('/arsip-kkr')->assertForbidden();$this->getJson('/aktivitas?action=kkr.finalized')->assertUnprocessable();
    }

    public function test_assigned_activity_includes_lifecycle_actor_time_object_and_filters_without_raw_notes(): void
    {
        $doc=$this->document();$this->seal($doc);$this->asActor($this->lead);
        $page=$this->get('/aktivitas')->assertOk()->assertSee('KKR diajukan')->assertSee('KKR disetujui dalam supervisi')->assertSee('KKR difinalisasi')->assertSee('Ketua arsip simulasi')->assertSee('Versi KKR #')->assertSee('WIB')->assertDontSee('Persetujuan arsip simulasi');
        $this->assertGreaterThanOrEqual(6,$page->viewData('events')->total());
        $filtered=$this->get('/aktivitas?action=kkr.finalized')->assertOk();$this->assertSame(1,$filtered->viewData('events')->total());
        $this->asActor($this->actor('team_lead'));$this->assertSame(0,$this->get('/aktivitas')->assertOk()->viewData('events')->total());
    }

    public function test_assigned_activity_hides_private_versions_unassigned_siblings_and_technical_account_events(): void
    {
        $version=$this->f['version']->getAttributes();unset($version['id']);$private=DB::table('proposal_item_versions')->insertGetId(array_replace($version,['version_number'=>2,'description'=>'Private audit simulasi','submitted_at'=>null]));
        CatalogAudit::record($this->owner->id,'proposal.item_saved','proposal_item_version',$private,null,['secret'=>'PRIVATE-AUDIT-SECRET']);
        $other=$this->submission($this->owner);CatalogAudit::record($this->owner->id,'proposal.item_saved','proposal_item_version',$other['version']->id,null,[]);
        CatalogAudit::record($this->owner->id,'proposal.item_saved','proposal_item_version',$this->f['version']->id,null,['secret'=>'VISIBLE-PAYLOAD-NOT-FOR-HTML']);
        AccountActivity::record($this->admin->id,'account.access_updated',$this->owner->id);
        $this->asActor($this->examiner);$page=$this->get('/aktivitas')->assertOk()->assertDontSee('PRIVATE-AUDIT-SECRET')->assertDontSee('VISIBLE-PAYLOAD-NOT-FOR-HTML')->assertDontSee('Akses akun diubah');$this->assertSame(1,$page->viewData('events')->total());
        DB::table('assignment_members')->where('id',$this->scope['members']['examiner'])->update(['is_active'=>false]);$this->assertSame(0,$this->get('/aktivitas')->assertOk()->viewData('events')->total());
    }

    public function test_archive_and_activity_inputs_recover_and_no_edit_delete_routes_exist(): void
    {
        $this->asActor($this->lead);$this->getJson('/arsip-kkr?page=-1')->assertUnprocessable();$this->getJson('/aktivitas?action[]=bad')->assertUnprocessable();$this->getJson('/aktivitas?action=unknown')->assertUnprocessable();
        $this->deleteJson('/aktivitas')->assertMethodNotAllowed();$this->patchJson('/aktivitas',['action'=>'forged'])->assertMethodNotAllowed();
        $this->from('/aktivitas')->get('/aktivitas?action[]=bad')->assertRedirect('/aktivitas')->assertSessionHasErrors('action');$this->withCookie(config('session.cookie'),session()->getId())->get('/aktivitas')->assertOk()->assertSee('Periksa kembali isian Anda.');
    }
}
