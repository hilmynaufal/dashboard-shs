<?php

namespace Tests\Feature;

use App\Models\{KkrDocument,ReviewAssignment};
use App\Support\{ItemVerification,KkrWork};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class ProgressBoardTest extends MySqlAccessTestCase
{
    private $owner; private $other; private $examiner; private $lead; private $controller; private $bkad; private array $f; private array $scope;
    protected function setUp(): void
    {
        parent::setUp();Storage::fake('local');
        $this->owner=$this->actor('skpd_operator',$this->organization());$this->other=$this->actor('skpd_operator',$this->organization());
        $this->examiner=$this->actor('examiner');$this->lead=$this->actor('team_lead');$this->controller=$this->actor('technical_controller');$this->bkad=$this->actor('bkad');
        $this->f=$this->submission($this->owner);$this->scope=$this->scope($this->f,$this->examiner,$this->lead,$this->controller);
        DB::table('proposals')->where('id',$this->f['proposal']->id)->update(['number'=>'SIM-OWN','title'=>'Paket simulasi pemantauan']);
        DB::table('proposal_item_versions')->where('id',$this->f['version']->id)->update(['description'=>'Item terlihat simulasi']);
    }
    private function asActor($actor): void { $this->flushSession();$this->actingAs($actor); }
    private function board($actor,string $query='')
    {
        $this->asActor($actor);return $this->get('/pemantauan'.$query)->assertOk();
    }
    private function note(string $status='internal',string $type='substantive',?array $f=null,?array $scope=null): int
    {
        $f??=$this->f;$scope??=$this->scope;
        return DB::table('review_notes')->insertGetId(['item_version_id'=>$f['version']->id,'item_review_id'=>$type==='substantive'?$scope['review']->id:null,
            'created_by'=>$this->examiner->id,'type'=>$type,'category'=>'Catatan simulasi '.$status,'description'=>'Uraian simulasi','status'=>$status,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function addResult(string $result='compliant',string $reason='Alasan simulasi'): void
    {
        $aspect=DB::table('review_aspects')->where('is_active',true)->first();
        DB::table('review_results')->insert(['item_review_id'=>$this->scope['review']->id,'review_aspect_id'=>$aspect->id,'result'=>$result,
            'criterion_snapshot'=>json_encode(['code'=>$aspect->code,'name'=>$aspect->name,'is_required'=>true]),'reason'=>$reason]);
    }

    public function test_own_scope_counts_empty_drafts_and_never_counts_other_skpd_or_internal_notes(): void
    {
        $foreign=$this->submission($this->other);$draft=$this->submission($this->owner);
        DB::table('proposals')->where('id',$draft['proposal']->id)->update(['status'=>'draft','number'=>'SIM-DRAFT']);DB::table('proposal_items')->where('id',$draft['item']->id)->update(['status'=>'draft']);
        DB::table('proposals')->insert(['organization_id'=>$this->owner->organization_id,'submission_period_id'=>$this->f['period_id'],'type'=>'SBU','title'=>'Draf kosong simulasi','status'=>'draft','created_by'=>$this->owner->id]);
        $this->note();$this->note('sent');$this->note('resolved');
        $page=$this->board($this->owner,'?view=packages');$groups=$page->viewData('groups');
        $this->assertEquals(['draft'=>2,'submitted'=>1],$groups['packages']);$this->assertEquals(['draft'=>1,'assigned'=>1],$groups['items']);
        $this->assertSame(3,$page->viewData('rows')->total());$this->assertSame(1,$page->viewData('openNotes'));$this->assertSame([],$page->viewData('metrics'));
        $page->assertSee('Draf kosong simulasi')->assertDontSee('Reviu selesai');
        $notes=$this->board($this->owner,'?view=notes&status=open');$this->assertSame(1,$notes->viewData('rows')->total());$notes->assertDontSee('Catatan simulasi internal');
    }

    public function test_assigned_scope_deduplicates_packages_and_excludes_unassigned_sibling_items_and_private_drafts(): void
    {
        $sibling=DB::table('proposal_items')->insertGetId(['proposal_id'=>$this->f['proposal']->id,'public_id'=>Str::uuid(),'status'=>'submitted']);
        $version=$this->f['version']->getAttributes();unset($version['id']);
        DB::table('proposal_item_versions')->insert(array_replace($version,['proposal_item_id'=>$sibling,'version_number'=>1,'description'=>'Item saudara rahasia','submitted_at'=>now()]));
        DB::table('proposal_item_versions')->insert(array_replace($version,['version_number'=>2,'description'=>'Draf privat belum terkirim','submitted_at'=>null]));
        $this->submission($this->other);
        foreach([$this->examiner,$this->lead,$this->controller] as $actor){
            $page=$this->board($actor,'?view=packages');$this->assertSame(1,$page->viewData('rows')->total());$this->assertSame(1,(int)$page->viewData('rows')->first()->item_count);
            $items=$this->board($actor);$this->assertSame(1,$items->viewData('rows')->total());$items->assertSee('Item terlihat simulasi')->assertDontSee('Draf privat belum terkirim')->assertDontSee('Item saudara rahasia');
        }
        $this->board($this->owner)->assertSee('Draf privat belum terkirim');
    }

    public function test_bkad_counts_submitted_packages_but_never_reads_substantive_results_or_internal_notes(): void
    {
        $foreign=$this->submission($this->other);DB::table('proposals')->where('id',$foreign['proposal']->id)->update(['status'=>'draft','number'=>'PRIVATE-DRAFT']);
        $this->note('internal');$this->note('sent','administrative');$this->note('sent');$this->addResult();
        $page=$this->board($this->bkad,'?view=packages');$this->assertSame(1,$page->viewData('rows')->total());$this->assertSame([],$page->viewData('metrics'));$this->assertSame(1,$page->viewData('openNotes'));
        $page->assertDontSee('PRIVATE-DRAFT')->assertDontSee('Reviu selesai')->assertDontSee('KKR &amp; supervisi',false);
        $this->asActor($this->bkad);$this->getJson('/pemantauan?view=kkr')->assertForbidden();$this->getJson('/pemantauan?metric=compliant')->assertForbidden();
    }

    public function test_status_summary_and_drilldown_totals_match_for_every_visible_package_item_and_note_status(): void
    {
        $this->note('internal');$this->note('sent');$this->note('responded');$this->note('resolved');
        foreach(['packages','items','notes'] as $view){
            $groups=$this->board($this->lead,'?view='.$view)->viewData('groups')[$view];
            foreach($groups as $status=>$count){$rows=$this->board($this->lead,'?view='.$view.'&status='.$status)->viewData('rows');$this->assertSame($count,$rows->total());foreach($rows as $row){$this->assertSame($status,$row->status);}}
        }
    }

    public function test_completed_compliant_and_open_notes_are_independent_and_follow_latest_visible_review(): void
    {
        $this->note();$this->addResult('non_compliant');DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'completed']);
        $page=$this->board($this->lead);$this->assertSame(['completed'=>1,'compliant'=>0],$page->viewData('metrics'));$this->assertSame(1,$page->viewData('openNotes'));
        $completed=$this->board($this->lead,'?metric=completed');$this->assertSame(1,$completed->viewData('rows')->total());$completed->assertSee(route('reviews.show',$this->scope['review']->id),false)->assertSee('Buka reviu');
        $this->assertSame(0,$this->board($this->lead,'?metric=compliant')->viewData('rows')->total());
        DB::table('review_results')->where('item_review_id',$this->scope['review']->id)->update(['result'=>'compliant']);
        $this->assertSame(['completed'=>1,'compliant'=>1],$this->board($this->lead)->viewData('metrics'));
        $this->assertSame(1,$this->board($this->lead,'?metric=compliant')->viewData('rows')->total());
        $this->scope($this->f,$this->actor('examiner')); // Another team's newer review is outside this lead's scope.
        $this->assertSame(['completed'=>1,'compliant'=>1],$this->board($this->lead)->viewData('metrics'));
        $version=$this->f['version']->getAttributes();unset($version['id']);$newVersion=DB::table('proposal_item_versions')->insertGetId(array_replace($version,['version_number'=>2,'submitted_at'=>now()]));
        DB::table('item_reviews')->insert(['assignment_item_id'=>$this->scope['scope_id'],'proposal_item_id'=>$this->f['item']->id,'item_version_id'=>$newVersion,'reviewed_by'=>$this->examiner->id,'status'=>'recheck']);
        $this->assertSame(['completed'=>0,'compliant'=>0],$this->board($this->lead)->viewData('metrics'));
    }

    public function test_empty_unassessed_or_only_not_applicable_results_are_never_labelled_compliant(): void
    {
        DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'completed']);
        $this->assertSame(0,$this->board($this->lead)->viewData('metrics')['compliant']);$this->addResult('not_assessed');
        $this->assertSame(0,$this->board($this->lead)->viewData('metrics')['compliant']);
        DB::table('review_results')->where('item_review_id',$this->scope['review']->id)->update(['result'=>'not_applicable','reason'=>'Alasan simulasi']);
        $this->assertSame(0,$this->board($this->lead)->viewData('metrics')['compliant']);
        DB::table('review_results')->where('item_review_id',$this->scope['review']->id)->update(['result'=>'compliant']);$this->assertSame(1,$this->board($this->lead)->viewData('metrics')['compliant']);
    }

    public function test_verification_action_and_note_resolution_are_reflected_on_the_next_page_load(): void
    {
        DB::table('proposal_items')->where('id',$this->f['item']->id)->update(['status'=>'submitted']);
        $this->assertSame(1,$this->board($this->bkad)->viewData('groups')['items']['submitted']);
        $this->asActor($this->bkad);$item=$this->f['item']->fresh();
        $this->postJson('/verifikasi/'.$item->id,['record_version'=>ItemVerification::token($item),'result'=>'needs_completion','reason'=>'Lengkapi simulasi'])->assertOk();
        $groups=$this->board($this->owner)->viewData('groups');$this->assertSame(1,$groups['items']['needs_completion']);$this->assertArrayNotHasKey('submitted',$groups['items']);
        $id=$this->note('sent','administrative');$this->assertSame(1,$this->board($this->owner)->viewData('openNotes'));
        DB::table('review_notes')->where('id',$id)->update(['status'=>'resolved']);$this->assertSame(0,$this->board($this->owner)->viewData('openNotes'));
    }

    public function test_supervision_queues_refresh_from_real_kkr_transitions_and_remain_team_scoped(): void
    {
        DB::table('proposals')->where('id',$this->f['proposal']->id)->update(['status'=>'review']);DB::table('proposal_items')->where('id',$this->f['item']->id)->update(['status'=>'reviewed']);
        DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'completed','completed_at'=>now()]);$this->addResult();
        $this->asActor($this->examiner);$assignment=ReviewAssignment::findOrFail($this->scope['assignment_id']);
        $id=$this->postJson('/penugasan/'.$assignment->id.'/kkr',['record_version'=>KkrWork::createToken($this->examiner,$assignment),'number'=>'SIM-KKR-'.Str::uuid(),'title'=>'KKR pemantauan simulasi',
            'conclusion'=>'Kesimpulan simulasi','recommendation'=>'Rekomendasi simulasi','reviews'=>[$this->scope['review']->id]])->assertCreated()->json('id');
        $doc=KkrDocument::findOrFail($id);$v=KkrWork::latest($doc);
        $this->assertSame(['draft'=>1],$this->board($this->lead,'?view=kkr')->viewData('groups')['kkr']);
        $this->asActor($this->examiner);$this->postJson('/kkr/'.$id.'/ajukan',['record_version'=>KkrWork::token($doc->fresh()),'version_id'=>$v->id,'confirm'=>1])->assertOk();
        $this->assertSame(1,$this->board($this->lead,'?view=kkr&status=awaiting_lead')->viewData('rows')->total());
        foreach(['team_lead'=>$this->lead,'technical_controller'=>$this->controller] as $level=>$actor){
            $this->asActor($actor);$this->postJson('/kkr/'.$id.'/versi/'.$v->id.'/keputusan',['record_version'=>KkrWork::token($doc->fresh()),'level'=>$level,'decision'=>'approved'])->assertOk();
            $status=$level==='team_lead'?'awaiting_controller':'final';$this->assertSame([$status=>1],$this->board($this->lead,'?view=kkr')->viewData('groups')['kkr']);
            $this->assertSame(1,$this->board($this->lead,'?view=kkr&status='.$status)->viewData('rows')->total());
        }
        $outsider=$this->actor('team_lead');$this->assertSame(0,$this->board($outsider,'?view=kkr')->viewData('rows')->total());
    }

    public function test_revoked_membership_removes_every_assigned_count_and_item_link(): void
    {
        $this->note();$this->addResult();DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'completed']);
        $this->assertSame(1,$this->board($this->examiner)->viewData('rows')->total());
        DB::table('assignment_members')->where('id',$this->scope['members']['examiner'])->update(['is_active'=>false]);
        $page=$this->board($this->examiner);$this->assertSame(0,$page->viewData('rows')->total());$this->assertSame(0,$page->viewData('openNotes'));$this->assertSame(['completed'=>0,'compliant'=>0],$page->viewData('metrics'));$page->assertDontSee('Item terlihat simulasi');
    }

    public function test_monitoring_grants_do_not_expand_resource_grants_or_other_monitoring_scopes(): void
    {
        $this->grantRole($this->owner,'bkad');
        $foreign=$this->submission($this->other);$this->grantRole($this->owner,'examiner');
        $role=DB::table('roles')->where('code','bkad')->value('id');$permission=DB::table('permissions')->where('code','monitoring.all')->value('id');
        DB::table('permission_role')->where('role_id',$role)->where('permission_id',$permission)->delete();
        $page=$this->board($this->owner);$this->assertSame(1,$page->viewData('rows')->total());
        $this->assertSame(1,$this->board($this->owner,'?view=packages')->viewData('rows')->total());
        $admin=$this->actor('admin');$this->asActor($admin);$this->getJson('/pemantauan')->assertForbidden();
        $this->get('/aplikasi')->assertOk()->assertDontSee('href="http://localhost/pemantauan"',false);
    }

    public function test_pagination_totals_match_summary_without_loading_private_text_and_invalid_filters_recover(): void
    {
        for($i=0;$i<23;$i++){$this->submission($this->owner);}
        $page=$this->board($this->owner);$this->assertSame(24,$page->viewData('rows')->total());$this->assertCount(20,$page->viewData('rows')->items());
        $next=$this->board($this->owner,'?page=2');$this->assertCount(4,$next->viewData('rows')->items());$this->assertSame(24,array_sum($next->viewData('groups')['items']));
        $this->asActor($this->owner);foreach(['view[]=items','status[]=draft','metric[]=completed','page=-1','view=items&status=completed','metric=compliant&status=reviewed'] as $query){$this->getJson('/pemantauan?'.$query)->assertUnprocessable();}
        $this->from('/pemantauan')->get('/pemantauan?status[]=draft')->assertRedirect('/pemantauan')->assertSessionHasErrors('status');
        $this->withCookie(config('session.cookie'),session()->getId())->get('/pemantauan')->assertOk()->assertSee('Periksa kembali isian Anda.');
    }
}
