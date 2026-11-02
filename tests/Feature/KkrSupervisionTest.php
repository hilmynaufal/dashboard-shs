<?php

namespace Tests\Feature;

use App\Models\{KkrDocument,KkrVersion,ReviewAssignment,ReviewNote};
use App\Support\{KkrWork,NoteWork};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class KkrSupervisionTest extends MySqlAccessTestCase
{
    private $owner;private $examiner;private $lead;private $controller;private array $f;private array $scope;
    protected function setUp(): void
    {
        parent::setUp();Storage::fake('local');$this->owner=$this->actor('skpd_operator',$this->organization());$this->examiner=$this->actor('examiner');$this->lead=$this->actor('team_lead');$this->controller=$this->actor('technical_controller');
        $this->examiner->name='Pemeriksa awal simulasi';$this->examiner->save();$this->lead->name='Ketua awal simulasi';$this->lead->save();$this->controller->name='Pengendali awal simulasi';$this->controller->save();
        $this->f=$this->submission($this->owner);$this->scope=$this->scope($this->f,$this->examiner,$this->lead,$this->controller);
        DB::table('proposals')->where('id',$this->f['proposal']->id)->update(['status'=>'review']);DB::table('proposal_items')->where('id',$this->f['item']->id)->update(['status'=>'reviewed']);
        DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'completed','completed_at'=>now(),'comparison_price'=>'1200.25','recommended_price'=>'1100.25','recommendation'=>'Rekomendasi item simulasi']);
        foreach(DB::table('review_aspects')->where('is_active',true)->get() as $aspect){DB::table('review_results')->insert(['item_review_id'=>$this->scope['review']->id,'review_aspect_id'=>$aspect->id,'result'=>'compliant','criterion_snapshot'=>json_encode(['code'=>$aspect->code,'name'=>$aspect->name,'is_required'=>(bool)$aspect->is_required]),'reason'=>'Penjelasan aspek simulasi','recommendation'=>'Rekomendasi aspek simulasi']);}
        $this->asActor($this->examiner);
    }
    private function asActor($actor): void {$this->flushSession();$this->actingAs($actor);}
    private function assignment(): ReviewAssignment {return ReviewAssignment::findOrFail($this->scope['assignment_id']);}
    private function values(array $extra=[]): array
    {
        return array_replace(['record_version'=>KkrWork::createToken($this->examiner->fresh(),$this->assignment()),'number'=>'SIM-'.Str::uuid(),'title'=>'KKR supervisi simulasi','conclusion'=>'Kesimpulan simulasi','recommendation'=>'Rekomendasi KKR simulasi','reviews'=>[$this->scope['review']->id]],$extra);
    }
    private function document(array $extra=[]): KkrDocument
    {
        $this->asActor($this->examiner);$this->get('/penugasan/'.$this->assignment()->id.'/kkr/buat')->assertOk()->assertSee('Siapkan KKR');
        $id=$this->postJson('/penugasan/'.$this->assignment()->id.'/kkr',$this->values($extra))->assertCreated()->json('id');return KkrDocument::findOrFail($id);
    }
    private function latest(KkrDocument $doc): KkrVersion {return KkrWork::latest($doc->fresh());}
    private function submit(KkrDocument $doc,array $extra=[])
    {
        $this->asActor($this->examiner);return $this->postJson('/kkr/'.$doc->id.'/ajukan',array_replace(['record_version'=>KkrWork::token($doc->fresh()),'version_id'=>$this->latest($doc)->id,'confirm'=>1,'acknowledge_open_notes'=>1],$extra));
    }
    private function decision(KkrDocument $doc,string $level='team_lead',string $decision='approved',array $extra=[])
    {
        $this->asActor($level==='team_lead'?$this->lead:$this->controller);
        return $this->postJson('/kkr/'.$doc->id.'/versi/'.$this->latest($doc)->id.'/keputusan',array_replace(['record_version'=>KkrWork::token($doc->fresh()),'level'=>$level,'decision'=>$decision,'notes'=>'Catatan supervisi simulasi','acknowledge_open_notes'=>1],$extra));
    }
    private function revise(KkrDocument $doc,array $extra=[])
    {
        $this->asActor($this->examiner);return $this->postJson('/kkr/'.$doc->id.'/versi',array_replace($this->values(),['record_version'=>KkrWork::token($doc->fresh()),'title'=>'KKR perbaikan simulasi','change_reason'=>'Alasan perbaikan simulasi'],$extra));
    }
    private function note(): ReviewNote
    {
        return ReviewNote::findOrFail(DB::table('review_notes')->insertGetId(['item_version_id'=>$this->f['version']->id,'item_review_id'=>$this->scope['review']->id,'created_by'=>$this->examiner->id,'type'=>'substantive','category'=>'Catatan terbuka simulasi','description'=>'Masalah yang belum selesai simulasi','recommendation'=>'Rekomendasi catatan simulasi','status'=>'internal','created_at'=>now(),'updated_at'=>now()]));
    }

    public function test_exact_version_moves_through_lead_then_controller_and_is_sealed_final(): void
    {
        $doc=$this->document();$version=$this->latest($doc);$hash=$version->snapshot_sha256;$this->assertTrue(KkrWork::integrity($version));$this->assertTrue(KkrWork::current($doc,$version));
        $this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Rp 1.000')->assertSee('Rp 1.200,25')->assertSee('Rp 1.100,25')->assertSee('Ajukan ke Ketua Tim');
        $this->submit($doc)->assertOk();$this->asActor($this->lead);$this->get('/kkr?queue=lead')->assertOk()->assertSee('KKR supervisi simulasi');
        $this->asActor($this->controller);$this->get('/kkr?queue=technical')->assertOk()->assertDontSee('KKR supervisi simulasi');$this->decision($doc,'technical_controller')->assertForbidden();
        $this->decision($doc)->assertOk();$this->get('/kkr?queue=lead')->assertOk()->assertDontSee('KKR supervisi simulasi');
        $this->asActor($this->controller);$this->get('/kkr?queue=technical')->assertOk()->assertSee('KKR supervisi simulasi');$this->decision($doc,'technical_controller')->assertOk();
        $this->assertSame('final',$doc->fresh()->status);$this->assertNotNull($version->fresh()->finalized_at);$this->assertSame($hash,$version->fresh()->snapshot_sha256);$this->assertDatabaseCount('kkr_approvals',2);
        $this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Versi final dikunci')->assertDontSee('name="decision"',false);$this->decision($doc,'technical_controller')->assertForbidden();
    }

    public function test_return_requires_reason_and_revision_does_not_reuse_old_decisions(): void
    {
        $doc=$this->document();$old=$this->latest($doc);$hash=$old->snapshot_sha256;$this->submit($doc)->assertOk();
        $this->decision($doc,'team_lead','returned',['notes'=>''])->assertUnprocessable()->assertJsonValidationErrors('notes');$this->assertDatabaseCount('kkr_approvals',0);
        $this->decision($doc,'team_lead','returned',['notes'=>'Perbaiki kesimpulan simulasi'])->assertOk();$this->asActor($this->examiner);$this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Perbaiki kesimpulan simulasi')->assertSee('Siapkan versi baru');
        $this->submit($doc)->assertUnprocessable();$this->revise($doc,['change_reason'=>''])->assertUnprocessable();$this->revise($doc)->assertOk();$new=$this->latest($doc);
        $this->assertSame(2,(int)$new->version_number);$this->assertSame((int)$old->id,(int)$new->supersedes_version_id);$this->assertNull($new->submitted_at);$this->assertSame($hash,$old->fresh()->snapshot_sha256);
        $this->submit($doc)->assertOk();$this->decision($doc,'technical_controller')->assertForbidden();$this->decision($doc)->assertOk();$this->decision($doc,'technical_controller')->assertOk();
        $this->assertDatabaseCount('kkr_approvals',3);$this->assertNull($old->fresh()->finalized_at);$this->assertNotNull($new->fresh()->finalized_at);
        $this->get('/kkr/'.$doc->id.'?version='.$old->id)->assertOk()->assertSee('KKR supervisi simulasi')->assertSee('Perbaiki kesimpulan simulasi')->assertDontSee('name="decision"',false);
    }

    public function test_technical_return_after_lead_approval_starts_both_levels_again(): void
    {
        $doc=$this->document();$first=$this->latest($doc);$this->submit($doc)->assertOk();$this->decision($doc)->assertOk();
        $this->decision($doc,'technical_controller','returned',['notes'=>null])->assertUnprocessable();$this->decision($doc,'technical_controller','returned',['notes'=>'Perbaiki teknis simulasi'])->assertOk();
        $this->revise($doc)->assertOk();$this->submit($doc)->assertOk();$this->decision($doc,'technical_controller')->assertForbidden();
        $this->assertDatabaseHas('kkr_approvals',['kkr_version_id'=>$first->id,'level'=>'team_lead','decision'=>'approved']);$this->assertNull($first->fresh()->finalized_at);
        $this->decision($doc)->assertOk();$this->decision($doc,'technical_controller')->assertOk();
    }

    public function test_open_notes_are_explicit_and_require_acknowledgement_at_each_approval(): void
    {
        $note=$this->note();$doc=$this->document();$version=$this->latest($doc);$this->assertSame(1,KkrWork::openCount($version));
        $this->get('/kkr/'.$doc->id)->assertOk()->assertSee('1 catatan masih terbuka')->assertSee('Masalah yang belum selesai simulasi')->assertSee('Internal tim');
        $this->submit($doc,['acknowledge_open_notes'=>0])->assertUnprocessable();$this->submit($doc)->assertOk();
        $this->decision($doc,'team_lead','approved',['acknowledge_open_notes'=>0])->assertUnprocessable();$this->decision($doc)->assertOk();
        $this->decision($doc,'technical_controller','approved',['acknowledge_open_notes'=>0])->assertUnprocessable();$this->assertNull($version->fresh()->finalized_at);
        $this->decision($doc,'technical_controller')->assertOk();$this->assertSame('internal',$note->fresh()->status);$this->assertDatabaseHas('kkr_entries',['kkr_version_id'=>$version->id,'has_open_notes'=>1]);
        $this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Masalah yang belum selesai simulasi')->assertSee('1 catatan masih terbuka');
    }

    public function test_source_note_changes_require_new_snapshot_and_stale_version_can_still_be_returned(): void
    {
        $note=$this->note();$doc=$this->document();$this->asActor($this->lead);$this->postJson('/catatan/'.$note->id.'/kirim',['record_version'=>NoteWork::token($note),'confirm'=>1])->assertOk();
        $this->submit($doc)->assertConflict();$this->revise($doc)->assertOk();$this->submit($doc)->assertOk();$this->decision($doc)->assertOk();
        $this->asActor($this->owner);$this->postJson('/catatan/'.$note->id.'/tanggapan',['record_version'=>NoteWork::token($note->fresh()),'response'=>'Tanggapan terbaru simulasi'])->assertOk();
        $this->decision($doc,'technical_controller')->assertConflict();$this->assertNull($this->latest($doc)->finalized_at);
        $this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Sumber hasil, catatan atau identitas tim berubah');
        $this->decision($doc,'technical_controller','returned',['notes'=>'Muat tanggapan terbaru'])->assertOk();$this->revise($doc)->assertOk();
        $this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Tanggapan terbaru simulasi');$this->submit($doc)->assertOk();$this->decision($doc)->assertOk();$this->decision($doc,'technical_controller')->assertOk();
    }

    public function test_scoped_lists_and_direct_access_exclude_other_teams_skpd_and_technical_admin(): void
    {
        $doc=$this->document();$this->submit($doc)->assertOk();
        foreach([$this->actor('admin'),$this->actor('bkad'),$this->owner,$this->actor('skpd_operator',$this->organization()),$this->actor('examiner'),$this->actor('team_lead'),$this->actor('technical_controller')] as $actor){
            $this->asActor($actor);$this->get('/kkr/'.$doc->id)->assertForbidden();
            $this->postJson('/kkr/'.$doc->id.'/versi/'.$this->latest($doc)->id.'/keputusan',['record_version'=>KkrWork::token($doc->fresh()),'level'=>$actor->hasRole('technical_controller')?'technical_controller':'team_lead','decision'=>'returned','notes'=>'Percobaan di luar penugasan simulasi'])->assertForbidden();
            if($actor->hasPermission('kkr.view')){$this->get('/kkr?queue=all')->assertOk()->assertViewHas('documents',fn($rows)=>$rows->total()===0);}else{$this->get('/kkr')->assertForbidden();}
        }
        $this->assertDatabaseCount('kkr_approvals',0);
        $other=$this->actor('examiner');$member=DB::table('assignment_members')->insertGetId(['review_assignment_id'=>$this->assignment()->id,'user_id'=>$other->id,'role'=>'examiner']);DB::table('assignment_items')->where('id',$this->scope['scope_id'])->update(['examiner_member_id'=>$member]);
        $this->asActor($this->examiner);$this->get('/kkr/'.$doc->id)->assertForbidden();$this->get('/kkr?queue=mine')->assertOk()->assertViewHas('documents',fn($rows)=>$rows->total()===0);
        $this->asActor($this->lead);$this->get('/kkr/'.$doc->id)->assertOk();
    }

    public function test_author_cannot_approve_own_and_same_person_cannot_approve_both_levels(): void
    {
        $this->grantRole($this->examiner,'team_lead');DB::table('assignment_members')->insert(['review_assignment_id'=>$this->assignment()->id,'user_id'=>$this->examiner->id,'role'=>'team_lead']);
        $this->grantRole($this->lead,'technical_controller');DB::table('assignment_members')->insert(['review_assignment_id'=>$this->assignment()->id,'user_id'=>$this->lead->id,'role'=>'technical_controller']);
        $doc=$this->document();$this->submit($doc)->assertOk();$this->asActor($this->examiner);
        $this->postJson('/kkr/'.$doc->id.'/versi/'.$this->latest($doc)->id.'/keputusan',['record_version'=>KkrWork::token($doc->fresh()),'level'=>'team_lead','decision'=>'approved'])->assertForbidden();
        $this->decision($doc)->assertOk();$this->asActor($this->lead);$this->postJson('/kkr/'.$doc->id.'/versi/'.$this->latest($doc)->id.'/keputusan',['record_version'=>KkrWork::token($doc->fresh()),'level'=>'technical_controller','decision'=>'approved'])->assertForbidden();
    }

    public function test_stale_forms_duplicate_decisions_and_mismatched_versions_do_not_add_approvals(): void
    {
        $doc=$this->document();$token=KkrWork::token($doc->fresh());$old=$this->latest($doc);$this->revise($doc)->assertOk();
        $this->asActor($this->examiner);$this->postJson('/kkr/'.$doc->id.'/ajukan',['record_version'=>$token,'version_id'=>$old->id,'confirm'=>1])->assertConflict();
        $this->submit($doc,['version_id'=>$old->id])->assertUnprocessable();$this->submit($doc)->assertOk();$this->decision($doc)->assertOk();$this->decision($doc)->assertForbidden();$this->assertDatabaseCount('kkr_approvals',1);
        $other=$this->document(['number'=>'OTHER-'.Str::uuid()]);$this->asActor($this->controller);$this->postJson('/kkr/'.$other->id.'/versi/'.$this->latest($doc)->id.'/keputusan',['record_version'=>KkrWork::token($other->fresh()),'level'=>'technical_controller','decision'=>'approved'])->assertNotFound();
    }

    public function test_server_rejects_tampered_snapshot_fields_ineligible_reviews_and_corrupt_entries(): void
    {
        $this->postJson('/penugasan/'.$this->assignment()->id.'/kkr',$this->values(['snapshot'=>['forged'=>true],'status'=>'final','prepared_by'=>$this->lead->id]))->assertUnprocessable();
        $this->postJson('/penugasan/'.$this->assignment()->id.'/kkr',$this->values(['reviews'=>[999999]]))->assertUnprocessable();$this->assertDatabaseCount('kkr_documents',0);
        DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'in_progress']);$this->postJson('/penugasan/'.$this->assignment()->id.'/kkr',$this->values())->assertUnprocessable();DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'completed']);
        $this->note();$doc=$this->document();$version=$this->latest($doc);DB::table('kkr_entries')->where('kkr_version_id',$version->id)->update(['has_open_notes'=>0]);
        $this->assertFalse(KkrWork::integrity($version));$this->submit($doc)->assertConflict();$this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Rekaman KKR tidak dapat diverifikasi');$this->assertDatabaseCount('kkr_approvals',0);
    }

    public function test_final_snapshot_and_identity_are_unchanged_after_reference_or_account_names_change(): void
    {
        $doc=$this->document();$this->submit($doc)->assertOk();$this->decision($doc)->assertOk();$this->decision($doc,'technical_controller')->assertOk();$version=$this->latest($doc);$before=$version->snapshot;$hash=$version->snapshot_sha256;
        $this->lead->name='Ketua terbaru simulasi';$this->lead->save();DB::table('organizations')->where('id',$this->owner->organization_id)->update(['name'=>'Organisasi terbaru simulasi']);
        DB::table('units')->where('id',$this->f['version']->unit_id)->update(['name'=>'Satuan terbaru simulasi']);
        $this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Ketua awal simulasi')->assertDontSee('Ketua terbaru simulasi')->assertDontSee('Organisasi terbaru simulasi');
        $this->assertSame($before,$version->fresh()->snapshot);$this->assertSame($hash,$version->fresh()->snapshot_sha256);$this->revise($doc)->assertForbidden();$this->submit($doc)->assertForbidden();$this->assertDatabaseCount('kkr_versions',1);
    }

    public function test_inactive_members_or_closed_assignments_cannot_continue_supervision(): void
    {
        $doc=$this->document();$this->submit($doc)->assertOk();DB::table('assignment_members')->where('id',$this->scope['members']['team_lead'])->update(['is_active'=>false]);$this->decision($doc)->assertForbidden();
        DB::table('assignment_members')->where('id',$this->scope['members']['team_lead'])->update(['is_active'=>true]);DB::table('review_assignments')->where('id',$this->assignment()->id)->update(['status'=>'completed']);$this->decision($doc)->assertForbidden();
        $this->get('/kkr/'.$doc->id)->assertOk()->assertSee('Penugasan ditutup; KKR hanya-baca')->assertDontSee('name="decision"',false);$this->assertDatabaseCount('kkr_approvals',0);
    }

    public function test_bad_html_form_values_recover_safely_and_csrf_is_required(): void
    {
        $url='/penugasan/'.$this->assignment()->id.'/kkr/buat';$this->from($url)->post('/penugasan/'.$this->assignment()->id.'/kkr',$this->values(['number'=>['bad'],'title'=>['bad'],'reviews'=>[['bad']]]))->assertRedirect($url)->assertSessionHasErrors(['number','title','reviews.0']);
        $this->withCookie(config('session.cookie'),session()->getId())->get($url)->assertOk()->assertSee('Periksa kembali isian Anda.');
        $this->app['env']='local';$this->post('/penugasan/'.$this->assignment()->id.'/kkr',$this->values())->assertStatus(419);$this->assertDatabaseCount('kkr_documents',0);
    }
}
