<?php

namespace Tests\Feature;

use App\Models\{ItemReview, ProposalItem, ReviewNote};
use App\Support\{ItemVerification, NoteWork, RecordVersion, ReviewWork};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class NoteWorkflowTest extends MySqlAccessTestCase
{
    private $owner; private $examiner; private $lead; private $bkad; private array $f; private array $scope;

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local');
        $this->owner=$this->actor('skpd_operator',$this->organization()); $this->examiner=$this->actor('examiner');
        $this->lead=$this->actor('team_lead'); $this->bkad=$this->actor('bkad');
        $this->f=$this->completeSubmission(); $this->scope=$this->scope($this->f,$this->examiner,$this->lead);
        DB::table('proposals')->where('id',$this->f['proposal']->id)->update(['status'=>'review']);
        DB::table('proposal_items')->where('id',$this->f['item']->id)->update(['status'=>'reviewing']);
    }

    private function asActor($actor): void { $this->flushSession(); $this->actingAs($actor); }
    private function note(string $status='internal'): ReviewNote
    {
        return ReviewNote::findOrFail(DB::table('review_notes')->insertGetId(['item_version_id'=>$this->f['version']->id,'item_review_id'=>$this->scope['review']->id,
            'created_by'=>$this->examiner->id,'type'=>'substantive','category'=>'Bukti simulasi','description'=>'Masalah khusus simulasi','status'=>$status,'created_at'=>now(),'updated_at'=>now()]));
    }
    private function token(ReviewNote $note): string { return NoteWork::token($note->fresh()); }
    private function send(ReviewNote $note): void
    {
        $this->asActor($this->lead); $this->postJson('/catatan/'.$note->id.'/kirim',['record_version'=>$this->token($note),'confirm'=>1])->assertOk();
    }
    private function reply(ReviewNote $note,array $extra=[])
    {
        $this->asActor($this->owner); return $this->postJson('/catatan/'.$note->id.'/tanggapan',array_replace(['record_version'=>$this->token($note),'response'=>'Jawaban simulasi'],$extra));
    }
    private function decide(ReviewNote $note,string $decision='resolved',string $reason='Alasan simulasi')
    {
        $this->asActor($this->examiner); return $this->postJson('/catatan/'.$note->id.'/keputusan',['record_version'=>$this->token($note),'decision'=>$decision,'reason'=>$reason]);
    }
    private function completeSubmission(): array
    {
        $f=$this->submission($this->owner);
        DB::table('submission_periods')->where('id',$f['period_id'])->update(['status'=>'open']);
        $code=DB::table('item_classifications')->where('id',$f['version']->item_classification_id)->first();
        $unit=DB::table('units')->where('id',$f['version']->unit_id)->first();
        $account=DB::table('account_codes')->insertGetId(['code'=>Str::uuid()->toString(),'name'=>'Rekening simulasi']);
        $accountRow=DB::table('account_codes')->where('id',$account)->first();
        DB::table('proposal_item_versions')->where('id',$f['version']->id)->update(['reference_snapshot'=>json_encode(['classification'=>['id'=>$code->id,'code'=>$code->code,'name'=>$code->name],
            'unit'=>['id'=>$unit->id,'code'=>$unit->code,'name'=>$unit->name],'accounts'=>[['id'=>$account,'code'=>$accountRow->code,'name'=>$accountRow->name]]])]);
        DB::table('item_version_accounts')->insert(['item_version_id'=>$f['version']->id,'account_code_id'=>$account,'code_snapshot'=>$accountRow->code,'name_snapshot'=>$accountRow->name]);
        Storage::disk('local')->put('evidence/'.($name=Str::uuid().'.pdf'),'%PDF-1.4 simulasi');
        $file=DB::table('evidence_files')->insertGetId(['organization_id'=>$this->owner->organization_id,'uploaded_by'=>$this->owner->id,'disk'=>'local','path'=>'evidence/'.$name,
            'original_name'=>'asal-simulasi.pdf','mime_type'=>'application/pdf','byte_size'=>17,'sha256'=>hash('sha256',$name)]);
        DB::table('item_version_evidence')->insert(['item_version_id'=>$f['version']->id,'evidence_file_id'=>$file,'purpose'=>'Bukti']);
        $f['version']=$f['version']->fresh(); return $f;
    }
    private function draft(array $changes=[]): int
    {
        $this->asActor($this->owner); $latest=ItemVerification::latest($this->f['item']->id);
        return $this->patchJson('/usulan/'.$this->f['proposal']->id.'/item/'.$this->f['item']->id,array_replace(
            \App\Support\ProposalItemInput::fields($latest),['record_version'=>RecordVersion::of((array)$latest),'name'=>'Uraian revisi simulasi','price'=>'1500.25'],$changes))->assertOk()->json('version_id');
    }

    public function test_internal_notes_are_hidden_on_detail_and_scoped_list_until_lead_sends(): void
    {
        $note=$this->note(); $this->asActor($this->owner);
        $this->get('/catatan')->assertOk()->assertDontSee('Masalah khusus simulasi')->assertDontSee('Bukti simulasi');
        $this->get('/catatan/'.$note->id)->assertForbidden();
        foreach([$this->examiner,$this->bkad,$this->actor('admin'),$this->actor('team_lead')] as $actor){$this->asActor($actor);$this->postJson('/catatan/'.$note->id.'/kirim',['record_version'=>$this->token($note),'confirm'=>1])->assertForbidden();}
        $this->send($note); $this->asActor($this->owner);
        $this->get('/catatan')->assertOk()->assertSee('Bukti simulasi');
        $this->get('/catatan/'.$note->id)->assertOk()->assertSee('Masalah khusus simulasi')->assertSee('Tanggapi catatan');
        $this->assertSame($this->lead->id,(int)$note->fresh()->sent_by);
        $this->assertSame('awaiting_response',$this->f['item']->fresh()->status);
        $this->asActor($this->actor('skpd_operator',$this->organization()));
        $this->get('/catatan')->assertOk()->assertDontSee('Bukti simulasi'); $this->get('/catatan/'.$note->id)->assertForbidden();
    }

    public function test_bkad_can_send_administrative_note_and_decide_own_item_response(): void
    {
        $f=$this->completeSubmission();DB::table('proposal_items')->where('id',$f['item']->id)->update(['status'=>'submitted']);
        $this->asActor($this->bkad); $item=$f['item']->fresh();
        $this->get('/verifikasi/'.$item->id)->assertOk()->assertSee('Kirim catatan administrasi');
        $id=$this->postJson('/verifikasi/'.$item->id.'/catatan',['record_version'=>ItemVerification::token($item),'category'=>'Administrasi simulasi','description'=>'Mohon lengkapi administrasi'])->assertCreated()->json('id');
        $note=ReviewNote::findOrFail($id);$this->reply($note)->assertOk();
        $this->asActor($this->bkad);$this->get('/catatan/'.$id)->assertOk()->assertSee('Jawaban simulasi');
        $this->postJson('/catatan/'.$id.'/keputusan',['record_version'=>$this->token($note),'decision'=>'resolved','reason'=>'Administrasi cukup'])->assertOk();
        $this->assertSame('resolved',$note->fresh()->status);$this->assertSame('submitted',$item->fresh()->status);
        $this->assertDatabaseCount('note_response_decisions',1);
    }

    public function test_reply_revision_upload_and_comparison_preserve_original_and_other_item(): void
    {
        $note=$this->note();$other=$this->completeSubmission();DB::table('proposal_items')->where('id',$other['item']->id)->update(['proposal_id'=>$this->f['proposal']->id]);$before=$other['item']->fresh()->getAttributes();$this->send($note);
        $versionId=$this->draft();
        $this->asActor($this->examiner);$this->get('/usulan/'.$this->f['proposal']->id.'/item/'.$this->f['item']->id.'/versi/'.$versionId)->assertForbidden();
        $this->asActor($this->owner);$latest=ItemVerification::latest($this->f['item']->id);
        $upload=UploadedFile::fake()->createWithContent('bukti-revisi.pdf',"%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $fileId=$this->post('/usulan/'.$this->f['proposal']->id.'/item/'.$this->f['item']->id.'/bukti',['record_version'=>RecordVersion::of((array)$latest),'file'=>$upload],['Accept'=>'application/json'])->assertCreated()->json('id');
        $this->asActor($this->examiner);$this->get('/bukti/'.$fileId)->assertForbidden();
        $this->reply($note,['submit_revision'=>true])->assertOk();
        $this->asActor($this->examiner);$this->get('/bukti/'.$fileId)->assertOk();
        $this->get('/catatan/'.$note->id)->assertOk()->assertSee('Perbandingan versi asal 1 dan versi tanggapan 3')->assertSee('1000.00')->assertSee('1500.25')->assertSee('bukti-revisi.pdf');
        $this->assertSame('1000.00',$this->f['version']->fresh()->proposed_price);$this->assertSame($before,$other['item']->fresh()->getAttributes());
        $this->assertDatabaseCount('proposal_item_versions',4); $this->assertSame('recheck',$this->f['item']->fresh()->status);
        $this->decide($note)->assertOk();$this->assertSame('resolved',$note->fresh()->status);
        $this->asActor($this->owner);$this->get('/catatan/'.$note->id)->assertOk()->assertSee('Alasan simulasi')->assertDontSee('name="response"',false);
    }

    public function test_repeated_rounds_append_responses_and_reasoned_decisions_without_overwriting(): void
    {
        $note=$this->note();$this->send($note);$this->reply($note)->assertOk();
        $this->decide($note,'needs_revision','')->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->decide($note,'needs_revision','Tambahkan keterangan simulasi')->assertOk();
        $this->reply($note,['response'=>'Jawaban kedua simulasi'])->assertOk();$this->decide($note)->assertOk();
        $this->assertDatabaseCount('note_responses',2);$this->assertDatabaseCount('note_response_decisions',2);
        $this->get('/catatan/'.$note->id)->assertOk()->assertSee('Jawaban kedua simulasi')->assertSee('Jawaban simulasi')->assertSee('Tambahkan keterangan simulasi');
        $this->reply($note)->assertForbidden();
    }

    public function test_text_only_reply_does_not_publish_private_draft(): void
    {
        $note=$this->note();$this->send($note);$draft=$this->draft();$this->reply($note)->assertOk();
        $this->assertNull(DB::table('proposal_item_versions')->where('id',$draft)->value('submitted_at'));
        $this->assertDatabaseHas('note_responses',['review_note_id'=>$note->id,'revised_version_id'=>$this->f['version']->id]);
        $this->asActor($this->examiner);$this->get('/catatan/'.$note->id)->assertOk()->assertDontSee('Uraian revisi simulasi');
    }

    public function test_stale_tokens_double_send_double_response_and_foreign_revision_are_rejected(): void
    {
        $note=$this->note();$token=$this->token($note);$this->send($note);$this->asActor($this->lead);
        $this->postJson('/catatan/'.$note->id.'/kirim',['record_version'=>$token,'confirm'=>1])->assertForbidden();
        $this->asActor($this->owner);$token=$this->token($note);$this->draft();
        $this->postJson('/catatan/'.$note->id.'/tanggapan',['record_version'=>$token,'response'=>'Stale'])->assertConflict();
        $this->reply($note,['revised_version_id'=>$this->f['version']->id])->assertUnprocessable()->assertJsonValidationErrors('revised_version_id');
        $this->reply($note)->assertOk();$this->reply($note)->assertForbidden();$this->assertDatabaseCount('note_responses',1);
    }

    public function test_revision_submission_guards_period_missing_file_and_invalid_data_atomically(): void
    {
        $note=$this->note();$this->send($note);$this->reply($note,['submit_revision'=>true])->assertUnprocessable();
        $this->draft();DB::table('submission_periods')->where('id',$this->f['period_id'])->update(['status'=>'closed']);
        $this->reply($note,['submit_revision'=>true])->assertUnprocessable();
        DB::table('submission_periods')->where('id',$this->f['period_id'])->update(['status'=>'open']);
        $this->draft(['spec'=>'']);$this->reply($note,['submit_revision'=>true])->assertUnprocessable();
        $this->draft(['spec'=>'Sudah lengkap']);Storage::disk('local')->deleteDirectory('evidence');
        $this->reply($note,['submit_revision'=>true])->assertUnprocessable();
        $this->assertDatabaseCount('note_responses',0);$this->assertSame('sent',$note->fresh()->status);
        $this->assertNull(ItemVerification::latest($this->f['item']->id)->submitted_at);
    }

    public function test_current_examiner_only_can_decide_after_transfer(): void
    {
        $note=$this->note();$this->send($note);$this->reply($note)->assertOk();
        foreach([$this->lead,$this->bkad,$this->owner,$this->actor('examiner'),$this->actor('admin')] as $actor){$this->asActor($actor);$this->postJson('/catatan/'.$note->id.'/keputusan',['record_version'=>$this->token($note),'decision'=>'resolved','reason'=>'Simulasi'])->assertForbidden();}
        $other=$this->actor('examiner');$member=DB::table('assignment_members')->insertGetId(['review_assignment_id'=>$this->scope['assignment_id'],'user_id'=>$other->id,'role'=>'examiner']);
        DB::table('assignment_items')->where('id',$this->scope['scope_id'])->update(['examiner_member_id'=>$member]);
        $this->decide($note)->assertForbidden();$this->get('/catatan/'.$note->id)->assertForbidden();
        $this->asActor($other);$this->get('/catatan/'.$note->id)->assertOk();$this->postJson('/catatan/'.$note->id.'/keputusan',['record_version'=>$this->token($note),'decision'=>'resolved','reason'=>'Pemeriksa pengganti'])->assertOk();
    }

    public function test_closed_assignment_and_completed_proposal_block_response_and_final_source_is_preserved(): void
    {
        $note=$this->note();$this->send($note);DB::table('review_assignments')->where('id',$this->scope['assignment_id'])->update(['status'=>'completed']);
        $this->reply($note)->assertUnprocessable();$this->get('/catatan/'.$note->id)->assertOk()->assertSee('Catatan ini hanya-baca')->assertDontSee('name="response"',false);
        $this->asActor($this->owner);$this->get('/usulan/'.$this->f['proposal']->id.'/item/'.$this->f['item']->id.'/ubah')->assertForbidden();
        DB::table('review_assignments')->where('id',$this->scope['assignment_id'])->update(['status'=>'active']);
        DB::table('proposals')->where('id',$this->f['proposal']->id)->update(['status'=>'completed']);$this->reply($note)->assertUnprocessable();
        $this->assertDatabaseCount('note_responses',0);
    }

    public function test_final_kkr_item_cannot_be_revised_or_receive_new_response(): void
    {
        $note=$this->note();$this->send($note);$kkr=$this->kkr($this->scope,$this->examiner);
        DB::table('kkr_versions')->where('id',$kkr['version']->id)->update(['finalized_at'=>now()]);
        DB::table('kkr_entries')->insert(['kkr_version_id'=>$kkr['version']->id,'item_review_id'=>$this->scope['review']->id,'display_order'=>1,'snapshot'=>'{}']);
        $this->reply($note)->assertUnprocessable();$this->get('/usulan/'.$this->f['proposal']->id.'/item/'.$this->f['item']->id.'/ubah')->assertForbidden();
        $this->get('/catatan/'.$note->id)->assertOk()->assertSee('Catatan ini hanya-baca')->assertDontSee('name="response"',false);
        $this->assertDatabaseCount('note_responses',0);$this->assertDatabaseCount('proposal_item_versions',1);
    }

    public function test_review_cannot_be_completed_while_official_note_is_open(): void
    {
        $note=$this->note();$this->send($note);$review=$this->scope['review']->fresh();
        $aspects=DB::table('review_aspects')->where('is_active',true)->get();
        foreach($aspects as $aspect){DB::table('review_results')->insert(['item_review_id'=>$review->id,'review_aspect_id'=>$aspect->id,'result'=>'compliant','criterion_snapshot'=>json_encode(['code'=>$aspect->code,'name'=>$aspect->name,'is_required'=>true])]);}
        $values=ReviewWork::results($review->id)->mapWithKeys(fn($row)=>[$row->id=>['result'=>'compliant','reason'=>'','recommendation'=>'']])->all();
        $this->asActor($this->examiner);$this->postJson('/reviu/'.$review->id,['record_version'=>ReviewWork::token($review),'intent'=>'complete','results'=>$values])->assertUnprocessable()->assertJsonValidationErrors('completion');
        $this->assertSame('in_progress',$review->fresh()->status);
        $this->reply($note)->assertOk();$this->decide($note)->assertOk();
        $this->postJson('/reviu/'.$review->id,['record_version'=>ReviewWork::token($review->fresh()),'intent'=>'complete','results'=>$values])->assertOk();
    }

    public function test_multiple_notes_keep_item_open_until_all_resolved_and_do_not_rewrite_completed_review(): void
    {
        $first=$this->note();$second=$this->note();$this->send($first);$this->send($second);
        $this->reply($first)->assertOk();$this->assertSame('awaiting_response',$this->f['item']->fresh()->status);
        $this->decide($first)->assertOk();$this->assertSame('awaiting_response',$this->f['item']->fresh()->status);
        $this->reply($second)->assertOk();DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'completed','completed_at'=>now()]);
        $before=ItemReview::findOrFail($this->scope['review']->id)->getAttributes();$this->decide($second)->assertOk();
        $this->assertSame('reviewed',$this->f['item']->fresh()->status);$this->assertSame($before,ItemReview::findOrFail($this->scope['review']->id)->getAttributes());
    }

    public function test_form_errors_render_safely_and_missing_csrf_does_not_send_note(): void
    {
        $note=$this->note();$this->send($note);$this->asActor($this->owner);
        $this->from('/catatan/'.$note->id)->post('/catatan/'.$note->id.'/tanggapan',['record_version'=>$this->token($note),'response'=>['invalid']])->assertRedirect('/catatan/'.$note->id)->assertSessionHasErrors('response');
        $this->withCookie(config('session.cookie'),session()->getId())->get('/catatan/'.$note->id)->assertOk()->assertSee('Periksa kembali isian Anda.');
        $this->app['env']='local';$this->post('/catatan/'.$note->id.'/tanggapan',['record_version'=>$this->token($note),'response'=>'Tanpa CSRF'])->assertStatus(419);
        $this->assertDatabaseCount('note_responses',0);
    }
}
