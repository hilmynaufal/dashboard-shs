<?php

namespace Tests\Feature;

use App\Models\{ItemReview, ReviewAssignment, ReviewNote};
use App\Support\{AssignmentWork, ReviewWork};
use Illuminate\Support\Facades\{DB, Gate, Schema, Storage};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class ReviewWorkflowTest extends MySqlAccessTestCase
{
    private $examiner;
    private $lead;
    private $controller;
    private $owner;
    private array $submissionData;
    private array $assignmentData;

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local');
        $this->owner=$this->actor('skpd_operator',$this->organization());$this->examiner=$this->actor('examiner');$this->lead=$this->actor('team_lead');$this->controller=$this->actor('technical_controller');
        $this->submissionData=$this->submission($this->owner);
        DB::table('proposals')->where('id',$this->submissionData['proposal']->id)->update(['status'=>'review']);
        $this->assignmentData=$this->scope($this->submissionData,$this->examiner,$this->lead,$this->controller);
        DB::table('item_reviews')->where('id',$this->assignmentData['review']->id)->delete();
        $this->actingAs($this->examiner);
    }

    private function actorAs($user): void { $this->flushSession();$this->actingAs($user); }
    private function assignment(): ReviewAssignment { return ReviewAssignment::findOrFail($this->assignmentData['assignment_id']); }
    private function desk(): string { return '/penugasan/'.$this->assignment()->id.'/item/'.$this->assignmentData['scope_id'].'/reviu'; }
    private function token(int $id): string { return ReviewWork::token(ItemReview::findOrFail($id)); }

    private function startReview(): int
    {
        return $this->postJson($this->desk(),['record_version'=>AssignmentWork::token($this->assignment())])->assertCreated()->json('id');
    }

    private function values(int $review,string $result='compliant'): array
    {
        return ReviewWork::results($review)->mapWithKeys(fn($row)=>[$row->id=>['result'=>$result,'reason'=>'Catatan aspek simulasi','recommendation'=>'Rekomendasi aspek simulasi']])->all();
    }

    private function saveReview(int $id,array $changes=[])
    {
        return $this->postJson('/reviu/'.$id,array_replace(['record_version'=>$this->token($id),'intent'=>'save','results'=>$this->values($id),
            'comparison_price'=>'1.250,50','recommended_price'=>'1200,25','recommendation'=>'Rekomendasi item simulasi'],$changes));
    }

    private function note(int $review,string $description='Masalah simulasi',?string $token=null)
    {
        return $this->postJson('/reviu/'.$review.'/catatan',['record_version'=>$token??$this->token($review),'category'=>'Bukti','description'=>$description,'note_recommendation'=>'Lengkapi bukti simulasi']);
    }

    public function test_approved_prototype_aspects_and_actual_mysql_recommendation_columns_are_available(): void
    {
        $aspects=DB::table('review_aspects')->where('code','like','PROTO\_%')->get();
        $this->assertCount(5,$aspects);$this->assertTrue($aspects->every(fn($row)=>(bool)$row->is_required && (bool)$row->is_active));
        $resultColumn=collect(Schema::getColumns('review_results'))->firstWhere('name','recommendation');
        $lockColumn=collect(Schema::getColumns('item_reviews'))->firstWhere('name','lock_version');
        $this->assertSame('text',$resultColumn['type']);$this->assertTrue($resultColumn['nullable']);
        $this->assertSame('int unsigned',$lockColumn['type']);$this->assertFalse($lockColumn['nullable']);
        $this->get($this->desk())->assertOk()->assertSee('Aspek prototipe')->assertSee('Kelengkapan bukti');
        $this->assertDatabaseCount('item_reviews',0);
    }

    public function test_start_is_version_bound_idempotent_and_never_marks_unassessed_as_compliant(): void
    {
        $id=$this->startReview();$this->assertSame($id,$this->startReview());
        $this->assertDatabaseCount('item_reviews',1);$this->assertDatabaseCount('review_results',5);
        $this->assertSame(5,DB::table('review_results')->where('item_review_id',$id)->where('result','not_assessed')->count());
        $this->assertDatabaseHas('item_reviews',['id'=>$id,'item_version_id'=>$this->submissionData['version']->id,'reviewed_by'=>$this->examiner->id,'status'=>'in_progress']);
        $this->assertDatabaseHas('proposal_items',['id'=>$this->submissionData['item']->id,'status'=>'reviewing']);
        $this->get($this->desk())->assertRedirect('/reviu/'.$id);$this->get('/reviu/'.$id)->assertOk()->assertSee('Simpan draf')->assertSee('Simpan &amp; selesai',false);
    }

    public function test_aspect_notes_recommendations_and_prices_persist_after_logout_and_login(): void
    {
        $password=Str::random(36).'Aa9';$this->examiner->password=$password;$this->examiner->save();
        $id=$this->startReview();$this->saveReview($id)->assertOk();
        foreach (ReviewWork::results($id) as $row) { $this->assertSame('compliant',$row->result);$this->assertSame('Rekomendasi aspek simulasi',$row->recommendation); }
        $this->postJson('/auth/logout')->assertOk();$this->getJson('/reviu/'.$id)->assertUnauthorized();
        $this->postJson('/auth/login',['email'=>$this->examiner->email,'password'=>$password])->assertOk();
        $this->get('/reviu/'.$id)->assertOk()->assertSee('Catatan aspek simulasi')->assertSee('Rekomendasi aspek simulasi')->assertSee('Rekomendasi item simulasi')->assertSee('1250.50')->assertSee('1200.25');
    }

    public function test_multiple_internal_notes_append_without_overwriting_and_are_hidden_from_skpd_bkad_and_admin(): void
    {
        $id=$this->startReview();$old=$this->token($id);$first=$this->note($id,'Masalah pertama')->assertCreated()->json('id');
        $this->note($id,'Duplikat form lama',$old)->assertStatus(409);
        $this->note($id,'<script>simulasi</script> Masalah kedua')->assertCreated();$this->assertDatabaseCount('review_notes',2);
        $this->saveReview($id)->assertOk();$this->assertDatabaseHas('review_notes',['id'=>$first,'description'=>'Masalah pertama','status'=>'internal']);
        $this->get('/reviu/'.$id)->assertOk()->assertSee('Masalah pertama')->assertSee('Masalah kedua')->assertDontSee('<script>simulasi</script>',false);
        foreach ([$this->owner,$this->actor('bkad'),$this->actor('admin')] as $user) {
            $this->actorAs($user);$this->get('/reviu/'.$id)->assertForbidden();
            $this->assertFalse(Gate::forUser($user)->allows('view',ReviewNote::findOrFail($first)));
        }
        $this->actorAs($this->lead);$this->get('/reviu/'.$id)->assertOk()->assertSee('Masalah pertama')->assertDontSee('name="intent"',false);
        $this->note($id)->assertForbidden();$this->saveReview($id)->assertForbidden();
    }

    public function test_prices_use_exact_separate_decimals_and_never_modify_the_proposed_version(): void
    {
        $id=$this->startReview();$original=DB::table('proposal_item_versions')->where('id',$this->submissionData['version']->id)->first();
        $this->saveReview($id,['comparison_price'=>'999999999999999999.99','recommended_price'=>'0'])->assertOk();
        $this->assertDatabaseHas('item_reviews',['id'=>$id,'comparison_price'=>'999999999999999999.99','recommended_price'=>'0.00']);
        $this->get('/reviu/'.$id)->assertOk()->assertSee('Rp 1.000')->assertSee('Harga pembanding')->assertSee('Rekomendasi harga');
        foreach (['-1','1e5','1,123',1.25,'1000000000000000000'] as $price) { $this->saveReview($id,['comparison_price'=>$price])->assertUnprocessable()->assertJsonValidationErrors('comparison_price'); }
        $this->saveReview($id,['comparison_price'=>null,'recommended_price'=>''])->assertOk();$this->assertDatabaseHas('item_reviews',['id'=>$id,'comparison_price'=>null,'recommended_price'=>null]);
        $this->assertSame((array)$original,(array)DB::table('proposal_item_versions')->find($original->id));
    }

    public function test_completion_requires_assessed_required_aspects_and_a_reason_for_not_applicable(): void
    {
        DB::table('review_aspects')->insert(['code'=>'SIM_OPTIONAL','name'=>'Aspek opsional simulasi','is_required'=>false,'is_active'=>true]);
        $id=$this->startReview();$values=$this->values($id);$rows=ReviewWork::results($id);$required=$rows->first();$optional=$rows->last();
        $values[$required->id]['result']='not_assessed';$values[$optional->id]['result']='not_assessed';
        $this->saveReview($id,['intent'=>'complete','results'=>$values])->assertUnprocessable()->assertJsonValidationErrors('completion');
        $this->assertDatabaseHas('item_reviews',['id'=>$id,'status'=>'in_progress','comparison_price'=>null]);
        $values[$required->id]=['result'=>'not_applicable','reason'=>'','recommendation'=>''];
        $this->saveReview($id,['results'=>$values])->assertUnprocessable()->assertJsonValidationErrors('results.'.$required->id.'.reason');
        $values[$required->id]['reason']='Tidak berlaku pada item simulasi';$values[$rows[1]->id]['result']='non_compliant';
        $this->saveReview($id,['intent'=>'complete','results'=>$values])->assertOk();
        $this->assertDatabaseHas('item_reviews',['id'=>$id,'status'=>'completed']);$this->assertNotNull(DB::table('item_reviews')->where('id',$id)->value('completed_at'));
        $this->assertDatabaseHas('proposal_items',['id'=>$this->submissionData['item']->id,'status'=>'reviewed']);
        $this->assertDatabaseHas('review_assignments',['id'=>$this->assignment()->id,'status'=>'active']);
        $this->saveReview($id)->assertForbidden();$this->note($id)->assertForbidden();
        $this->get('/reviu/'.$id)->assertOk()->assertSee('Rekaman ini hanya-baca')->assertDontSee('name="intent"',false);
    }

    public function test_single_result_mode_applies_one_result_to_every_aspect_and_can_switch_back(): void
    {
        $id=$this->startReview();$this->assertDatabaseHas('item_reviews',['id'=>$id,'result_mode'=>'per_aspect']);
        $this->get('/reviu/'.$id)->assertOk()->assertSee('Mode penilaian')->assertSee('Satu hasil pemeriksaan')->assertSee('name="overall[result]"',false);
        $single=fn(array $overall,string $intent='save')=>$this->saveReview($id,['result_mode'=>'single','intent'=>$intent,'results'=>null,'overall'=>$overall]);

        // Satu hasil berlaku untuk seluruh aspek; catatan dan rekomendasi hanya pada aspek pertama.
        $single(['result'=>'non_compliant','reason'=>'Dasar harga belum ada','recommendation'=>'Lengkapi dasar harga'])->assertOk();
        $rows=ReviewWork::results($id);$this->assertCount(5,$rows);
        $this->assertSame(['non_compliant'],$rows->pluck('result')->unique()->values()->all());
        $this->assertSame('Dasar harga belum ada',$rows[0]->reason);$this->assertSame('Lengkapi dasar harga',$rows[0]->recommendation);
        $this->assertNull($rows[1]->reason);$this->assertNull($rows[4]->recommendation);
        $this->assertDatabaseHas('item_reviews',['id'=>$id,'result_mode'=>'single','status'=>'in_progress']);
        $this->get('/reviu/'.$id)->assertOk()->assertSee('Dasar harga belum ada')->assertSee('Berlaku untuk 5 aspek');

        // Belum dinilai tidak dapat diselesaikan; Tidak berlaku wajib beralasan.
        $single(['result'=>'not_assessed'],'complete')->assertUnprocessable()->assertJsonValidationErrors('completion');
        $single(['result'=>'not_applicable','reason'=>' '])->assertUnprocessable()->assertJsonValidationErrors('overall.reason');
        $single(['result'=>'bukan-pilihan'])->assertUnprocessable()->assertJsonValidationErrors('overall.result');
        $this->assertSame('non_compliant',ReviewWork::results($id)->first()->result);

        // Kembali ke mode per aspek: aspek dinilai sendiri-sendiri dan harus lengkap.
        $values=$this->values($id,'compliant');$this->saveReview($id,['result_mode'=>'per_aspect','results'=>$values])->assertOk();
        $this->assertDatabaseHas('item_reviews',['id'=>$id,'result_mode'=>'per_aspect']);
        $this->saveReview($id,['result_mode'=>'per_aspect','results'=>null])->assertUnprocessable()->assertJsonValidationErrors('results');
        $this->saveReview($id,['result_mode'=>'campuran'])->assertUnprocessable()->assertJsonValidationErrors('result_mode');

        // Penyelesaian mode tunggal.
        $single(['result'=>'compliant','reason'=>'Seluruh aspek sesuai','recommendation'=>'Lanjutkan'],'complete')->assertOk();
        $this->assertDatabaseHas('item_reviews',['id'=>$id,'status'=>'completed','result_mode'=>'single']);
        $this->assertSame(['compliant'],ReviewWork::results($id)->pluck('result')->unique()->values()->all());
        $this->get('/reviu/'.$id)->assertOk()->assertSee('Hasil pemeriksaan')->assertSee('Seluruh aspek sesuai')->assertDontSee('name="intent"',false);
    }

    public function test_review_without_mode_keeps_using_the_stored_mode(): void
    {
        $id=$this->startReview();$this->saveReview($id,['result_mode'=>'single','results'=>null,'overall'=>['result'=>'compliant','reason'=>'Satu hasil']])->assertOk();
        $payload=['record_version'=>$this->token($id),'intent'=>'save','overall'=>['result'=>'insufficient_evidence','reason'=>'Bukti kurang'],'comparison_price'=>null,'recommended_price'=>null,'recommendation'=>null];
        $this->postJson('/reviu/'.$id,$payload)->assertOk();
        $this->assertSame(['insufficient_evidence'],ReviewWork::results($id)->pluck('result')->unique()->values()->all());
        $this->assertDatabaseHas('item_reviews',['id'=>$id,'result_mode'=>'single']);
    }

    public function test_aspect_master_changes_do_not_rewrite_snapshot_or_required_completion_rules(): void
    {
        $id=$this->startReview();$row=ReviewWork::results($id)->first();
        DB::table('review_aspects')->where('id',$row->review_aspect_id)->update(['name'=>'Nama baru simulasi','is_required'=>false,'is_active'=>false]);
        DB::table('review_aspects')->insert(['code'=>'SIM_NEW','name'=>'Aspek baru simulasi','is_required'=>true]);
        $this->get('/reviu/'.$id)->assertOk()->assertSee($row->criterion['name'])->assertDontSee('Nama baru simulasi')->assertDontSee('Aspek baru simulasi');
        $values=$this->values($id);$values[$row->id]['result']='not_assessed';
        $this->saveReview($id,['intent'=>'complete','results'=>$values])->assertUnprocessable();
        $this->assertSame($row->criterion,ReviewWork::results($id)->first()->criterion);$this->assertCount(5,ReviewWork::results($id));
    }

    public function test_foreign_or_missing_aspect_ids_and_stale_forms_cannot_overwrite_review_results(): void
    {
        $id=$this->startReview();$old=$this->token($id);$values=$this->values($id);$first=array_key_first($values);unset($values[$first]);
        $this->saveReview($id,['results'=>$values])->assertUnprocessable()->assertJsonValidationErrors('results');
        $values=$this->values($id);$values[999999]=$values[$first];unset($values[$first]);
        $this->saveReview($id,['results'=>$values])->assertUnprocessable();
        $this->saveReview($id)->assertOk();$this->saveReview($id,['record_version'=>$old])->assertStatus(409);
        $this->saveReview($id,['recommendation'=>'B'])->assertOk();$this->saveReview($id,['recommendation'=>'Rekomendasi item simulasi'])->assertOk();
        $this->saveReview($id,['record_version'=>$old])->assertStatus(409);$this->assertCount(5,ReviewWork::results($id));
    }

    public function test_current_examiner_can_continue_after_transfer_but_old_examiner_and_old_tokens_are_rejected(): void
    {
        $id=$this->startReview();$this->note($id,'Catatan pemeriksa awal')->assertCreated();$old=$this->token($id);$replacement=$this->actor('examiner');
        $member=DB::table('assignment_members')->insertGetId(['review_assignment_id'=>$this->assignment()->id,'user_id'=>$replacement->id,'role'=>'examiner','is_active'=>true]);
        $this->actorAs($this->lead);
        $this->postJson('/penugasan/'.$this->assignment()->id.'/item/'.$this->assignmentData['scope_id'].'/alihkan',['record_version'=>AssignmentWork::token($this->assignment()),'examiner_member_id'=>$member,'reason'=>'Alih tugas simulasi'])->assertOk();
        $this->actorAs($this->examiner);$this->get('/reviu/'.$id)->assertForbidden();$this->saveReview($id)->assertForbidden();
        $this->actorAs($replacement);$this->get('/reviu/'.$id)->assertOk()->assertSee('Catatan pemeriksa awal');
        $this->saveReview($id,['record_version'=>$old])->assertStatus(409);$this->saveReview($id)->assertOk();
        $this->assertDatabaseHas('item_reviews',['id'=>$id,'reviewed_by'=>$this->examiner->id]);$this->assertDatabaseCount('review_notes',1);
    }

    public function test_only_assigned_active_examiner_can_start_and_completed_assignment_or_revoked_role_cannot_write(): void
    {
        foreach ([$this->owner,$this->lead,$this->controller,$this->actor('examiner'),$this->actor('admin'),$this->actor('bkad')] as $user) {
            $this->actorAs($user);$this->postJson($this->desk(),['record_version'=>AssignmentWork::token($this->assignment())])->assertForbidden();
        }
        $this->actorAs($this->examiner);$id=$this->startReview();
        DB::table('review_assignments')->where('id',$this->assignment()->id)->update(['status'=>'completed']);$this->get('/reviu/'.$id)->assertOk();$this->saveReview($id)->assertForbidden();
        DB::table('review_assignments')->where('id',$this->assignment()->id)->update(['status'=>'active']);DB::table('role_user')->where('user_id',$this->examiner->id)->delete();
        $this->get('/reviu/'.$id)->assertForbidden();$this->saveReview($id)->assertForbidden();
    }

    public function test_no_aspects_cannot_create_an_empty_or_automatically_complete_review(): void
    {
        DB::table('review_aspects')->update(['is_active'=>false]);$this->get($this->desk())->assertOk()->assertSee('Daftar aspek belum disiapkan');
        $this->postJson($this->desk(),['record_version'=>AssignmentWork::token($this->assignment())])->assertUnprocessable()->assertJsonValidationErrors('aspects');
        $this->assertDatabaseCount('item_reviews',0);$this->assertDatabaseHas('proposal_items',['id'=>$this->submissionData['item']->id,'status'=>'assigned']);
    }

    public function test_invalid_web_values_recover_without_render_errors_and_business_mutations_require_csrf(): void
    {
        $id=$this->startReview();$this->from('/reviu/'.$id)->post('/reviu/'.$id,['record_version'=>['invalid'],'results'=>['invalid'],'intent'=>['invalid'],'comparison_price'=>['invalid']])->assertRedirect('/reviu/'.$id)->assertSessionHasErrors();
        $this->withCookie(config('session.cookie'),session()->getId())->get('/reviu/'.$id)->assertOk()->assertSee('Periksa kembali isian');
        $this->app->instance('env','production');$this->post('/reviu/'.$id,[])->assertStatus(419);$this->post('/reviu/'.$id.'/catatan',[])->assertStatus(419);$this->post($this->desk(),[])->assertStatus(419);
    }
}
