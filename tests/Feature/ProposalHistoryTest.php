<?php

namespace Tests\Feature;

use App\Models\{Proposal, ProposalItemVersion, ReviewNote};
use App\Support\{ItemVerification, NoteWork, ProposalDraft, ProposalItemInput, RecordVersion};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class ProposalHistoryTest extends MySqlAccessTestCase
{
    private $owner; private $bkad; private array $f;

    protected function setUp(): void
    {
        parent::setUp();Storage::fake('local');
        $this->owner=$this->actor('skpd_operator',$this->organization());$this->owner->name='Penyimpan simulasi';$this->owner->save();
        $this->bkad=$this->actor('bkad');$this->bkad->name='Verifikator simulasi';$this->bkad->save();$this->f=$this->submission($this->owner);
        $this->f['proposal']->status='draft';$this->f['proposal']->save();$this->f['item']->status='draft';$this->f['item']->save();
        $code=DB::table('item_classifications')->find($this->f['version']->item_classification_id);$unit=DB::table('units')->find($this->f['version']->unit_id);
        $account=DB::table('account_codes')->insertGetId(['code'=>'005.01','name'=>'Rekening simulasi']);
        DB::table('proposal_item_versions')->where('id',$this->f['version']->id)->update(['submitted_at'=>null,'created_at'=>'2026-10-01 00:00:00',
            'reference_snapshot'=>json_encode(['classification'=>['id'=>$code->id,'code'=>$code->code,'name'=>$code->name],'unit'=>['id'=>$unit->id,'code'=>$unit->code,'name'=>$unit->name],'accounts'=>[['id'=>$account,'code'=>'005.01','name'=>'Rekening simulasi']]])]);
        DB::table('submission_periods')->where('id',$this->f['period_id'])->update(['status'=>'open']);
        DB::table('item_version_accounts')->insert(['item_version_id'=>$this->f['version']->id,'account_code_id'=>$account,'code_snapshot'=>'005.01','name_snapshot'=>'Rekening simulasi']);
        $this->f['version']=$this->f['version']->fresh();$this->asActor($this->owner);
    }

    private function asActor($actor): void { $this->flushSession();$this->actingAs($actor); }
    private function history(array $query=[]): string { return '/usulan/'.$this->f['proposal']->id.'/item/'.$this->f['item']->id.'/riwayat'.($query?'?'.http_build_query($query):''); }
    private function itemUrl(): string { return '/usulan/'.$this->f['proposal']->id.'/item/'.$this->f['item']->id; }
    private function upload(string $name): int
    {
        $version=ItemVerification::latest($this->f['item']->id);
        $upload=UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj\n<< /Name (".$name.") >>\nendobj\n%%EOF");
        return $this->post($this->itemUrl().'/bukti',['record_version'=>RecordVersion::of((array)$version),'file'=>$upload],['Accept'=>'application/json'])->assertCreated()->json('id');
    }
    private function save(string $name='Item sesudah simulasi'): int
    {
        $v=ItemVerification::latest($this->f['item']->id);
        return $this->patchJson($this->itemUrl(),array_replace(ProposalItemInput::fields($v),['record_version'=>RecordVersion::of((array)$v),'name'=>$name,'price'=>'1.250,50']))->assertOk()->json('version_id');
    }
    private function submit(): void
    {
        $proposal=$this->f['proposal']->fresh();
        $this->postJson('/usulan/'.$proposal->id.'/kirim',['record_version'=>ProposalDraft::version($proposal),'confirm'=>1])->assertOk();
    }
    private function returnItem(string $reason='Lengkapi bukti simulasi'): void
    {
        $this->asActor($this->bkad);$this->postJson('/verifikasi/'.$this->f['item']->id,['record_version'=>ItemVerification::token($this->f['item']->fresh()),'result'=>'needs_completion','reason'=>$reason])->assertOk();$this->asActor($this->owner);
    }
    private function resubmit(): void
    {
        $this->postJson($this->itemUrl().'/kelengkapan',['record_version'=>ItemVerification::token($this->f['item']->fresh()),'confirm'=>1])->assertOk();
    }

    public function test_history_shows_real_before_after_actor_time_and_original_documents_after_replacement(): void
    {
        $old=$this->upload('bukti-asal-simulasi.pdf');$submitted=ItemVerification::latest($this->f['item']->id)->id;$this->submit();$this->returnItem();
        $changed=$this->save();$new=$this->upload('bukti-baru-simulasi.pdf');$version=ItemVerification::latest($this->f['item']->id);
        $this->deleteJson($this->itemUrl().'/bukti/'.$old,['record_version'=>RecordVersion::of((array)$version)])->assertOk();$this->resubmit();
        $this->get($this->history(['version'=>$changed]))->assertOk()->assertSee('Rp 1.000')->assertSee('Rp 1.250,50')->assertSee('Item simulasi')->assertSee('Item sesudah simulasi')->assertSee('Penyimpan simulasi')->assertSee('WIB')->assertSee('Berubah');
        $this->get($this->history(['version'=>$submitted]))->assertOk()->assertSee('bukti-asal-simulasi.pdf')->assertSee('Usulan dikirim')->assertSee('Dikembalikan untuk dilengkapi')->assertSee('Lengkapi bukti simulasi')->assertSee('Verifikator simulasi');
        $latest=ItemVerification::latest($this->f['item']->id);
        $this->get($this->history(['version'=>$latest->id]))->assertOk()->assertSee('Kelengkapan diajukan ulang')->assertSee('bukti-baru-simulasi.pdf');
        $this->get('/bukti/'.$old)->assertOk();$this->get('/bukti/'.$new)->assertOk();
        $this->assertDatabaseHas('item_version_evidence',['item_version_id'=>$submitted,'evidence_file_id'=>$old]);
        $this->assertDatabaseMissing('item_version_evidence',['item_version_id'=>$latest->id,'evidence_file_id'=>$old]);
    }

    public function test_verifier_sees_only_submitted_versions_and_comparison_skips_private_versions(): void
    {
        $this->upload('asal.pdf');$origin=ItemVerification::latest($this->f['item']->id)->id;$this->submit();$this->returnItem();
        $private=$this->save('Rahasia draf simulasi');$final=$this->save('Publik revisi simulasi');
        $this->asActor($this->bkad);$this->get($this->history())->assertOk()->assertDontSee('Rahasia draf simulasi')->assertDontSee('Publik revisi simulasi')->assertViewHas('versions',fn($rows)=>$rows->total()===1 && (int)$rows->first()->id===(int)$origin);
        $this->get($this->history(['version'=>$private]))->assertNotFound();$this->get($this->history(['version'=>$final]))->assertNotFound();
        $this->asActor($this->owner);$this->resubmit();$this->asActor($this->bkad);
        $this->get($this->history(['version'=>$final]))->assertOk()->assertSee('Publik revisi simulasi')->assertDontSee('Rahasia draf simulasi')->assertSee('Dibandingkan dengan versi 2 yang dapat Anda akses.');
        $this->get($this->history(['version'=>$origin]))->assertOk();
    }

    public function test_history_requires_own_or_assigned_scope_and_separate_history_permission(): void
    {
        $this->upload('asal.pdf');$this->submit();$examiner=$this->actor('examiner');$lead=$this->actor('team_lead');$controller=$this->actor('technical_controller');
        $f=$this->f;$f['version']=ProposalItemVersion::findOrFail(ItemVerification::latest($f['item']->id)->id);$scope=$this->scope($f,$examiner,$lead,$controller);
        foreach([$this->owner,$this->bkad,$examiner,$lead,$controller] as $actor){$this->asActor($actor);$this->get($this->history())->assertOk();}
        foreach([$this->actor('admin'),$this->actor('skpd_operator',$this->organization()),$this->actor('examiner'),$this->actor('team_lead')] as $actor){$this->asActor($actor);$this->get($this->history())->assertForbidden();}
        $role=DB::table('roles')->where('code','examiner')->value('id');$permission=DB::table('permissions')->where('code','history.assigned')->value('id');
        DB::table('permission_role')->where('role_id',$role)->where('permission_id',$permission)->delete();$this->asActor($examiner);$this->get($this->history())->assertForbidden();
        DB::table('permission_role')->insert(['role_id'=>$role,'permission_id'=>$permission]);
        $other=$this->actor('examiner');$member=DB::table('assignment_members')->insertGetId(['review_assignment_id'=>$scope['assignment_id'],'user_id'=>$other->id,'role'=>'examiner']);
        DB::table('assignment_items')->where('id',$scope['scope_id'])->update(['examiner_member_id'=>$member]);
        $this->asActor($examiner);$this->get($this->history())->assertForbidden();$this->asActor($other);$this->get($this->history())->assertOk();
    }

    public function test_history_does_not_leak_internal_notes_or_unrelated_audit_payloads(): void
    {
        $this->upload('asal.pdf');$this->submit();$examiner=$this->actor('examiner');$lead=$this->actor('team_lead');$f=$this->f;$f['version']=ProposalItemVersion::findOrFail(ItemVerification::latest($f['item']->id)->id);$scope=$this->scope($f,$examiner,$lead);
        $note=ReviewNote::findOrFail(DB::table('review_notes')->insertGetId(['item_version_id'=>$f['version']->id,'item_review_id'=>$scope['review']->id,'created_by'=>$examiner->id,'type'=>'substantive','category'=>'Simulasi internal','description'=>'Isi internal simulasi','status'=>'internal','created_at'=>now(),'updated_at'=>now()]));
        DB::table('activity_logs')->insert(['actor_id'=>$this->owner->id,'action'=>'unrelated.simulation','subject_type'=>'proposal','subject_id'=>$this->f['proposal']->id,'after_values'=>json_encode(['private'=>'Payload bukan untuk riwayat simulasi']),'occurred_at'=>now()]);
        $this->get($this->history())->assertOk()->assertDontSee('Isi internal simulasi')->assertDontSee('Payload bukan untuk riwayat simulasi');
        $this->asActor($lead);$this->postJson('/catatan/'.$note->id.'/kirim',['record_version'=>NoteWork::token($note),'confirm'=>1])->assertOk();
        $this->asActor($this->owner);$this->get($this->history())->assertOk()->assertSee('Catatan reviu resmi dikirim')->assertSee('Isi internal simulasi');
        $this->asActor($this->bkad);$this->get($this->history())->assertOk()->assertDontSee('Isi internal simulasi');
    }

    public function test_official_response_republication_and_return_reasons_appear_without_rewriting_versions(): void
    {
        $this->upload('asal.pdf');$this->submit();$examiner=$this->actor('examiner');$lead=$this->actor('team_lead');$f=$this->f;$f['version']=ProposalItemVersion::findOrFail(ItemVerification::latest($f['item']->id)->id);$scope=$this->scope($f,$examiner,$lead);
        $note=ReviewNote::findOrFail(DB::table('review_notes')->insertGetId(['item_version_id'=>$f['version']->id,'item_review_id'=>$scope['review']->id,'created_by'=>$examiner->id,'type'=>'substantive','category'=>'Simulasi','description'=>'Lengkapi simulasi','status'=>'internal','created_at'=>now(),'updated_at'=>now()]));
        $this->asActor($lead);$this->postJson('/catatan/'.$note->id.'/kirim',['record_version'=>NoteWork::token($note),'confirm'=>1])->assertOk();$this->asActor($this->owner);$revised=$this->save();
        $this->postJson('/catatan/'.$note->id.'/tanggapan',['record_version'=>NoteWork::token($note->fresh()),'response'=>'Jawaban dengan revisi simulasi','submit_revision'=>true])->assertOk();
        $this->asActor($examiner);$this->postJson('/catatan/'.$note->id.'/keputusan',['record_version'=>NoteWork::token($note->fresh()),'decision'=>'needs_revision','reason'=>'Perbaikan lanjutan simulasi'])->assertOk();
        $this->get($this->history(['version'=>$revised]))->assertOk()->assertSee('Revisi dikirim bersama tanggapan')->assertSee('Jawaban dengan revisi simulasi')->assertSee('Tanggapan dikembalikan untuk diperbaiki')->assertSee('Perbaikan lanjutan simulasi');
        $this->assertSame('1000.00',$f['version']->fresh()->proposed_price);
    }

    public function test_sender_is_distinct_from_version_creator_and_missing_legacy_actor_is_not_invented(): void
    {
        $this->upload('asal.pdf');$v=ItemVerification::latest($this->f['item']->id)->id;
        $sender=$this->actor('skpd_operator',$this->owner->organization_id);$sender->name='Pengirim berbeda simulasi';$sender->save();$this->asActor($sender);$this->submit();
        $this->get($this->history(['version'=>$v]))->assertOk()->assertSee('Pengirim berbeda simulasi')->assertSee('Penyimpan simulasi');
        DB::table('activity_logs')->where('subject_type','proposal')->where('subject_id',$this->f['proposal']->id)->where('action','proposal.submitted')->delete();
        $this->get($this->history(['version'=>$v]))->assertOk()->assertSee('Pelaku tidak tercatat')->assertSee('Versi dikirim');
    }

    public function test_versions_and_events_are_paginated_and_selected_versions_must_belong_to_item(): void
    {
        $this->upload('asal.pdf');$this->submit();$this->returnItem();
        for($i=0;$i<22;$i++){ProposalDraft::copyVersion($this->f['proposal']->fresh(),$this->owner,$this->f['item']->id);}
        $this->get($this->history())->assertOk()->assertViewHas('versions',fn($rows)=>$rows->total()===24 && $rows->count()===20 && $rows->currentPage()===1);$this->get($this->history(['page'=>2]))->assertOk()->assertViewHas('versions',fn($rows)=>$rows->count()===4 && $rows->currentPage()===2);
        $version=ItemVerification::latest($this->f['item']->id,true)->id;
        for($i=0;$i<22;$i++){DB::table('verification_results')->insert(['item_version_id'=>$version,'verified_by'=>$this->bkad->id,'result'=>'needs_completion','reason'=>'Putaran simulasi '.$i,'verified_at'=>now()]);}
        $this->get($this->history(['version'=>$version,'event_page'=>2]))->assertOk()->assertViewHas('events',fn($rows)=>$rows->total()>20 && $rows->currentPage()===2);
        $other=$this->submission($this->owner);$this->get($this->history(['version'=>$other['version']->id]))->assertNotFound();
        $this->getJson($this->history(['version'=>['invalid']]))->assertUnprocessable();$this->getJson($this->history(['event_page'=>0]))->assertUnprocessable();
        $this->get('/usulan/'.$other['proposal']->id.'/item/'.$this->f['item']->id.'/riwayat')->assertNotFound();
    }

    public function test_history_and_previous_versions_have_no_mutation_actions(): void
    {
        $this->upload('asal.pdf');$this->submit();$before=DB::table('proposal_item_versions')->where('proposal_item_id',$this->f['item']->id)->get()->toJson();$count=DB::table('activity_logs')->count();
        $response=$this->get($this->history())->assertOk()->assertDontSee('name="_method"',false)->assertDontSee('Hapus versi')->assertDontSee('Ubah riwayat');
        $response->assertHeader('Cache-Control','no-store, private');
        $this->get('/usulan/'.$this->f['proposal']->id)->assertOk()->assertSee('Riwayat');
        $this->get($this->itemUrl().'/versi/'.$this->f['version']->id)->assertOk()->assertSee('Riwayat &amp; perubahan',false);
        foreach(['postJson','patchJson','deleteJson'] as $method){$this->$method($this->history(),['description'=>'Tamper simulasi'])->assertStatus(405);}
        $this->deleteJson($this->itemUrl().'/versi/'.$this->f['version']->id)->assertStatus(405);
        $this->assertSame($before,DB::table('proposal_item_versions')->where('proposal_item_id',$this->f['item']->id)->get()->toJson());$this->assertSame($count,DB::table('activity_logs')->count());
    }
}
