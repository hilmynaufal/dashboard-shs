<?php

namespace Tests\Feature;

use App\Models\{ItemReview, Proposal, ProposalItem, ReviewAssignment};
use App\Support\{AssignmentWork, ItemVerification, ProposalDraft, RecordVersion};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class AssignmentWorkflowTest extends MySqlAccessTestCase
{
    private $lead;
    private $first;
    private $second;
    private $controller;
    private $owner;
    private int $period;
    private int $org;

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local');
        $this->org=$this->organization();$this->owner=$this->actor('skpd_operator',$this->org);
        $this->lead=$this->actor('team_lead');$this->first=$this->actor('examiner');$this->second=$this->actor('examiner');$this->controller=$this->actor('technical_controller');
        $this->first->name='Pemeriksa Alfa';$this->first->save();$this->second->name='Pemeriksa Beta';$this->second->save();
        $this->period=DB::table('submission_periods')->insertGetId(['budget_year'=>2027,'name'=>'Periode penugasan simulasi','opens_at'=>now()->subDay(),'closes_at'=>now()->addDay(),'status'=>'open','created_by'=>$this->owner->id]);
        $this->actingAs($this->lead);
    }

    private function actorAs($user): void { $this->flushSession();$this->actingAs($user); }
    private function token(int $assignment): string { return AssignmentWork::token(ReviewAssignment::findOrFail($assignment)); }

    private function createAssignment(array $changes=[]): int
    {
        return $this->postJson('/penugasan',array_replace(['number'=>'TUGAS/'.Str::uuid(),'title'=>'Penugasan simulasi','submission_period_id'=>$this->period,'object_organization_id'=>$this->org,
            'examiners'=>[$this->first->id,$this->second->id],'technical_controller_id'=>$this->controller->id],$changes))->assertCreated()->json('id');
    }

    private function member(int $assignment, $user): int
    {
        return DB::table('assignment_members')->where('review_assignment_id',$assignment)->where('user_id',$user->id)->where('role','examiner')->value('id');
    }

    private function ready(string $name='Item siap simulasi', ?int $org=null, ?int $period=null): array
    {
        $org??=$this->org;$period??=$this->period;
        $proposal=DB::table('proposals')->insertGetId(['organization_id'=>$org,'submission_period_id'=>$period,'type'=>'SHS','number'=>'USL/'.Str::uuid(),'title'=>'Paket penugasan simulasi','created_by'=>$this->owner->id,'status'=>'verification','submitted_at'=>now()]);
        $item=DB::table('proposal_items')->insertGetId(['proposal_id'=>$proposal,'public_id'=>Str::uuid(),'status'=>'ready']);
        $classification=DB::table('item_classifications')->insertGetId(['code'=>Str::uuid(),'name'=>'Klasifikasi simulasi']);$unit=DB::table('units')->insertGetId(['code'=>Str::uuid(),'name'=>'Satuan simulasi']);
        $version=DB::table('proposal_item_versions')->insertGetId(['proposal_item_id'=>$item,'version_number'=>1,'item_classification_id'=>$classification,'unit_id'=>$unit,
            'description'=>$name,'specification'=>'Spesifikasi penugasan simulasi','proposed_price'=>'1000.00','reference_snapshot'=>'{}','created_by'=>$this->owner->id,'submitted_at'=>now()]);
        DB::table('verification_results')->insert(['item_version_id'=>$version,'verified_by'=>$this->lead->id,'result'=>'ready','verified_at'=>now()]);
        $path='evidence/'.Str::uuid().'.pdf';Storage::disk('local')->put($path,"%PDF-1.4\n% synthetic");
        $file=DB::table('evidence_files')->insertGetId(['organization_id'=>$org,'uploaded_by'=>$this->owner->id,'original_name'=>'bukti-simulasi.pdf','disk'=>'local','path'=>$path,'mime_type'=>'application/pdf','byte_size'=>20,'sha256'=>hash('sha256',$path)]);
        DB::table('item_version_evidence')->insert(['item_version_id'=>$version,'evidence_file_id'=>$file,'purpose'=>'simulasi']);

        return compact('proposal','item','version','file');
    }

    private function allocate(int $assignment,array $selection,?string $token=null)
    {
        return $this->postJson('/penugasan/'.$assignment.'/item',['items'=>$selection,'record_version'=>$token??$this->token($assignment)]);
    }

    private function assignedScope(int $assignment,int $item): object
    {
        return DB::table('assignment_items')->where('review_assignment_id',$assignment)->where('proposal_item_id',$item)->first();
    }

    private function transfer(int $assignment,int $scope,int $target,string $reason='Pembagian ulang simulasi',?string $token=null)
    {
        return $this->postJson('/penugasan/'.$assignment.'/item/'.$scope.'/alihkan',['examiner_member_id'=>$target,'reason'=>$reason,'record_version'=>$token??$this->token($assignment)]);
    }

    public function test_lead_creates_scoped_team_with_distinct_active_roles_and_validates_identity(): void
    {
        $this->get('/penugasan/buat')->assertOk()->assertSee('Pemeriksa Alfa');
        $id=$this->createAssignment(['number'=>'0']);$this->assertDatabaseHas('review_assignments',['id'=>$id,'number'=>'0','status'=>'active','created_by'=>$this->lead->id]);
        $this->assertDatabaseCount('assignment_members',4);$this->get('/penugasan/'.$id)->assertOk();$this->get('/penugasan?q=0&sort=number')->assertOk()->assertSee('Penugasan simulasi');
        $this->postJson('/penugasan',['number'=>'0','title'=>'Duplikat','submission_period_id'=>$this->period,'object_organization_id'=>$this->org,'examiners'=>[$this->first->id]])->assertUnprocessable()->assertJsonValidationErrors('number');
        $this->postJson('/penugasan',['number'=>'BAD','title'=>'Simulasi','submission_period_id'=>$this->period,'object_organization_id'=>$this->org,'examiners'=>[$this->lead->id]])->assertUnprocessable();
        $this->first->is_active=false;$this->first->save();
        $this->postJson('/penugasan',['number'=>'BAD2','title'=>'Simulasi','submission_period_id'=>$this->period,'object_organization_id'=>$this->org,'examiners'=>[$this->first->id]])->assertUnprocessable()->assertJsonValidationErrors('examiners');
        $this->assertDatabaseCount('review_assignments',1);
    }

    public function test_candidates_are_limited_to_ready_items_in_the_assignment_period_and_skpd_without_unassigned_document_access(): void
    {
        $id=$this->createAssignment();$eligible=$this->ready('Item lingkup tepat');$other=$this->ready('Item SKPD lain',$this->organization());
        $period=DB::table('submission_periods')->insertGetId(['budget_year'=>2028,'name'=>'Periode lain','opens_at'=>now()->subDay(),'closes_at'=>now()->addDay(),'created_by'=>$this->lead->id]);$this->ready('Item periode lain',null,$period);
        $returned=$this->ready('Item dikembalikan');DB::table('proposal_items')->where('id',$returned['item'])->update(['status'=>'needs_completion']);
        $this->get('/penugasan/'.$id.'/pilih-item')->assertOk()->assertSee('Item lingkup tepat')->assertDontSee('Item SKPD lain')->assertDontSee('Item periode lain')->assertDontSee('Item dikembalikan');
        $this->get('/usulan/'.$eligible['proposal'])->assertForbidden();$this->get('/bukti/'.$eligible['file'])->assertForbidden();
        $this->allocate($id,[$other['item']=>$this->member($id,$this->first)])->assertUnprocessable()->assertJsonValidationErrors('items');$this->assertDatabaseCount('assignment_items',0);
    }

    public function test_allocated_items_appear_for_the_selected_examiner_with_scoped_versions_and_files(): void
    {
        $id=$this->createAssignment();$one=$this->ready('Item Alfa saja');$two=$this->ready('Item Beta saja');$unselected=$this->ready('Item belum dipilih');
        $this->allocate($id,[$one['item']=>$this->member($id,$this->first),$two['item']=>$this->member($id,$this->second)])->assertOk();
        $this->assertDatabaseHas('assignment_items',['proposal_item_id'=>$one['item'],'initial_version_id'=>$one['version'],'examiner_member_id'=>$this->member($id,$this->first)]);
        $this->assertDatabaseHas('proposal_items',['id'=>$one['item'],'status'=>'assigned']);$this->assertDatabaseHas('proposals',['id'=>$one['proposal'],'status'=>'review']);
        $this->get('/penugasan/'.$id)->assertOk()->assertSee('Item Alfa saja')->assertSee('Item Beta saja');
        $this->actorAs($this->first);$this->get('/pekerjaan')->assertOk()->assertSee('Item Alfa saja')->assertDontSee('Item Beta saja')->assertDontSee('Item belum dipilih')->assertSee(ReviewAssignment::find($id)->number);
        $this->get('/penugasan/'.$id)->assertOk()->assertSee('Item Alfa saja')->assertDontSee('Item Beta saja');
        $this->get('/usulan/'.$one['proposal'].'/item/'.$one['item'].'/versi/'.$one['version'])->assertOk();$this->get('/bukti/'.$one['file'])->assertOk();$this->get('/bukti/'.$two['file'])->assertForbidden();
        $this->actorAs($this->controller);$this->get('/penugasan/'.$id)->assertOk()->assertSee('Item Alfa saja')->assertSee('Item Beta saja');$this->get('/penugasan/'.$id.'/pilih-item')->assertForbidden();
    }

    public function test_batch_allocation_is_atomic_and_rejects_foreign_members_repeated_items_and_stale_forms(): void
    {
        $id=$this->createAssignment();$otherAssignment=$this->createAssignment();$one=$this->ready();$bad=$this->ready('Item belum lengkap');DB::table('proposal_items')->where('id',$bad['item'])->update(['status'=>'submitted']);
        $this->allocate($id,[$one['item']=>$this->member($otherAssignment,$this->first)])->assertUnprocessable()->assertJsonValidationErrors('examiner_member_id');
        $this->allocate($id,[$one['item']=>$this->member($id,$this->first),$bad['item']=>$this->member($id,$this->second)])->assertUnprocessable();
        $this->assertDatabaseCount('assignment_items',0);$this->assertDatabaseHas('proposal_items',['id'=>$one['item'],'status'=>'ready']);
        $old=$this->token($id);$this->allocate($id,[$one['item']=>$this->member($id,$this->first)])->assertOk();
        $this->allocate($id,[$one['item']=>$this->member($id,$this->second)],$old)->assertStatus(409);
        $this->allocate($id,[$one['item']=>$this->member($id,$this->second)])->assertUnprocessable();
        DB::table('proposal_items')->where('id',$one['item'])->update(['status'=>'ready']);
        $this->allocate($otherAssignment,[$one['item']=>$this->member($otherAssignment,$this->first)])->assertUnprocessable();$this->assertDatabaseCount('assignment_items',1);
        $this->allocate($id,[0=>$this->member($id,$this->first)])->assertUnprocessable();
    }

    public function test_transfer_keeps_scope_and_version_records_history_and_revokes_old_examiner_access(): void
    {
        $id=$this->createAssignment();$one=$this->ready();$this->allocate($id,[$one['item']=>$this->member($id,$this->first)])->assertOk();$scope=$this->assignedScope($id,$one['item']);
        $review=DB::table('item_reviews')->insertGetId(['assignment_item_id'=>$scope->id,'proposal_item_id'=>$one['item'],'item_version_id'=>$one['version'],'reviewed_by'=>$this->first->id]);
        $this->get('/penugasan/'.$id.'/item/'.$scope->id.'/alihkan')->assertOk()->assertSee('Pemeriksa Beta');
        $this->transfer($id,$scope->id,$this->member($id,$this->second),'Penyesuaian beban simulasi')->assertOk();
        $this->assertDatabaseCount('assignment_items',1);$this->assertDatabaseHas('assignment_items',['id'=>$scope->id,'initial_version_id'=>$one['version'],'examiner_member_id'=>$this->member($id,$this->second)]);
        $this->assertDatabaseHas('assignment_transfers',['assignment_item_id'=>$scope->id,'from_member_id'=>$this->member($id,$this->first),'to_member_id'=>$this->member($id,$this->second),'reason'=>'Penyesuaian beban simulasi']);
        $this->get('/penugasan/'.$id)->assertOk()->assertSee('Penyesuaian beban simulasi')->assertSee('Pemeriksa Alfa')->assertSee('Pemeriksa Beta');
        $this->actorAs($this->first);$this->get('/pekerjaan')->assertOk()->assertDontSee('Item siap simulasi');$this->get('/bukti/'.$one['file'])->assertForbidden();$this->get('/usulan/'.$one['proposal'])->assertForbidden();
        $this->assertFalse(Gate::forUser($this->first)->allows('update',ItemReview::findOrFail($review)));
        $this->actorAs($this->second);$this->get('/pekerjaan')->assertOk()->assertSee('Item siap simulasi');$this->get('/bukti/'.$one['file'])->assertOk();
        $this->assertTrue(Gate::forUser($this->second)->allows('update',ItemReview::findOrFail($review)));
        $this->assertDatabaseHas('item_reviews',['id'=>$review,'reviewed_by'=>$this->first->id]);
    }

    public function test_transfers_require_reason_valid_target_and_current_state_including_a_b_a_token_changes(): void
    {
        $id=$this->createAssignment();$other=$this->createAssignment();$one=$this->ready();$this->allocate($id,[$one['item']=>$this->member($id,$this->first)])->assertOk();$scope=$this->assignedScope($id,$one['item']);
        $this->transfer($id,$scope->id,$this->member($id,$this->second),'   ')->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->transfer($id,$scope->id,$this->member($id,$this->first))->assertUnprocessable();
        $this->transfer($id,$scope->id,$this->member($other,$this->second))->assertUnprocessable();
        $this->second->is_active=false;$this->second->save();$this->transfer($id,$scope->id,$this->member($id,$this->second))->assertUnprocessable();$this->second->is_active=true;$this->second->save();
        $old=$this->token($id);$this->transfer($id,$scope->id,$this->member($id,$this->second))->assertOk();$this->transfer($id,$scope->id,$this->member($id,$this->first))->assertOk();
        $this->transfer($id,$scope->id,$this->member($id,$this->second),'Stale ABA',$old)->assertStatus(409);$this->assertDatabaseCount('assignment_transfers',2);
        $this->transfer($other,$scope->id,$this->member($other,$this->second))->assertNotFound();
        DB::table('item_reviews')->insert(['assignment_item_id'=>$scope->id,'proposal_item_id'=>$one['item'],'item_version_id'=>$one['version'],'reviewed_by'=>$this->first->id,'status'=>'completed']);
        $this->transfer($id,$scope->id,$this->member($id,$this->second))->assertUnprocessable();
        $this->get('/penugasan/'.$id.'/item/'.$scope->id.'/alihkan')->assertForbidden();
    }

    public function test_search_sort_and_reordered_id_mapping_never_reassign_existing_scope(): void
    {
        $id=$this->createAssignment();$z=$this->ready('Zeta %_0');$a=$this->ready('Alfa simulasi');
        $this->get('/penugasan/'.$id.'/pilih-item?sort=name')->assertOk()->assertSeeInOrder(['Alfa simulasi','Zeta %_0']);
        $this->get('/penugasan/'.$id.'/pilih-item?q=%25_0')->assertOk()->assertSee('Zeta %_0')->assertDontSee('Alfa simulasi');
        $this->allocate($id,[$a['item']=>$this->member($id,$this->second),$z['item']=>$this->member($id,$this->first)])->assertOk();
        $before=DB::table('assignment_items')->orderBy('id')->get()->toJson();
        $this->get('/penugasan/'.$id.'?sort=name')->assertOk();$this->get('/penugasan/'.$id.'?q=Alfa&sort=assignment')->assertOk()->assertSee('Alfa simulasi')->assertDontSee('Zeta %_0');
        $this->actorAs($this->first);$this->get('/pekerjaan?sort=name')->assertOk()->assertSee('Zeta %_0')->assertDontSee('Alfa simulasi');
        $this->assertSame($before,DB::table('assignment_items')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('assignment_items',['proposal_item_id'=>$a['item'],'examiner_member_id'=>$this->member($id,$this->second)]);
    }

    public function test_only_own_active_lead_can_manage_and_roles_memberships_are_rechecked_on_every_request(): void
    {
        $id=$this->createAssignment();$one=$this->ready();
        foreach ([$this->actor('team_lead'),$this->actor('admin'),$this->actor('bkad'),$this->owner,$this->first,$this->controller] as $user) {
            $this->actorAs($user);$this->get('/penugasan/'.$id.'/pilih-item')->assertForbidden();$this->allocate($id,[$one['item']=>$this->member($id,$this->first)])->assertForbidden();
        }
        $this->actorAs($this->lead);$this->allocate($id,[$one['item']=>$this->member($id,$this->first)])->assertOk();
        DB::table('assignment_members')->where('review_assignment_id',$id)->where('user_id',$this->lead->id)->update(['is_active'=>false]);
        $this->get('/penugasan/'.$id)->assertForbidden();
        $this->actorAs($this->first);DB::table('role_user')->where('user_id',$this->first->id)->delete();$this->get('/pekerjaan')->assertForbidden();$this->get('/bukti/'.$one['file'])->assertForbidden();
    }

    public function test_completed_assignment_and_csrf_reject_mutations_and_invalid_html_can_recover(): void
    {
        $id=$this->createAssignment();$one=$this->ready();$this->allocate($id,[$one['item']=>$this->member($id,$this->first)])->assertOk();$scope=$this->assignedScope($id,$one['item']);
        DB::table('review_assignments')->where('id',$id)->update(['status'=>'completed']);$this->get('/penugasan/'.$id)->assertOk();$this->transfer($id,$scope->id,$this->member($id,$this->second))->assertForbidden();
        $this->from('/penugasan/buat')->post('/penugasan',['title'=>['bad'],'number'=>['bad'],'examiners'=>'bad'])->assertRedirect('/penugasan/buat')->assertSessionHasErrors(['title','number','examiners']);
        $this->get('/penugasan/buat')->assertOk()->assertSee('Pemeriksa Alfa');
        $this->app->instance('env','production');$this->post('/penugasan',['number'=>'CSRF'])->assertStatus(419);$this->post('/penugasan/'.$id.'/item',[])->assertStatus(419);
    }

    public function test_verified_item_can_be_actually_assigned_while_its_sibling_is_returned(): void
    {
        $this->actorAs($this->owner);
        DB::table('item_classifications')->insert(['code'=>'001','name'=>'Klasifikasi simulasi']);DB::table('units')->insert(['code'=>'BH','name'=>'Buah']);DB::table('account_codes')->insert(['code'=>'5.001','name'=>'Rekening simulasi']);
        $proposal=$this->postJson('/usulan',['title'=>'Paket campuran simulasi','type'=>'SBU','submission_period_id'=>$this->period])->assertCreated()->json('id');$ids=[];
        foreach (['Item benar-benar siap','Item masih dilengkapi'] as $name) {
            $v=$this->postJson('/usulan/'.$proposal.'/item',['name'=>$name,'spec'=>'Spesifikasi','code'=>'001','unit'=>'BH','account'=>'5.001','price'=>'1000','record_version'=>ProposalDraft::version(Proposal::findOrFail($proposal))])->assertCreated()->json('version_id');
            $item=DB::table('proposal_item_versions')->where('id',$v)->value('proposal_item_id');
            $this->post('/usulan/'.$proposal.'/item/'.$item.'/bukti',['file'=>UploadedFile::fake()->createWithContent('simulasi.pdf',"%PDF-1.4\n% synthetic\n1 0 obj <</Type /Catalog>> endobj\n%%EOF"),'record_version'=>RecordVersion::of((array)ItemVerification::latest($item))],['Accept'=>'application/json'])->assertCreated();$ids[]=$item;
        }
        $this->postJson('/usulan/'.$proposal.'/kirim',['confirm'=>true,'record_version'=>ProposalDraft::version(Proposal::findOrFail($proposal))])->assertOk();
        $this->actorAs($this->actor('bkad'));
        foreach ($ids as $index=>$item) { $this->postJson('/verifikasi/'.$item,['result'=>$index===0?'ready':'needs_completion','reason'=>'Hasil simulasi','record_version'=>ItemVerification::token(ProposalItem::findOrFail($item))])->assertOk(); }
        $this->actorAs($this->lead);$assignment=$this->createAssignment();$this->get('/penugasan/'.$assignment.'/pilih-item')->assertOk()->assertSee('Item benar-benar siap')->assertDontSee('Item masih dilengkapi');
        $this->allocate($assignment,[$ids[0]=>$this->member($assignment,$this->first)])->assertOk();
        $this->assertDatabaseHas('proposal_items',['id'=>$ids[1],'status'=>'needs_completion']);
        $this->actorAs($this->first);$this->get('/pekerjaan')->assertOk()->assertSee('Item benar-benar siap')->assertDontSee('Item masih dilengkapi');
    }
}
