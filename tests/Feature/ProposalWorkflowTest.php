<?php

namespace Tests\Feature;

use App\Models\{Proposal, SubmissionPeriod};
use App\Support\{ProposalDraft, ProposalImport, RecordVersion};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class ProposalWorkflowTest extends MySqlAccessTestCase
{
    private $operator;
    private int $periodId;

    protected function setUp():void
    {
        parent::setUp();Storage::fake('local');
        $this->operator=$this->actor('skpd_operator',$this->organization());$this->actingAs($this->operator);
        $this->periodId=DB::table('submission_periods')->insertGetId(['budget_year'=>2027,'name'=>'Periode alur simulasi','opens_at'=>now()->subDay(),'closes_at'=>now()->addDay(),'status'=>'open','created_by'=>$this->operator->id]);
        DB::table('item_classifications')->insert(['code'=>'001.020','name'=>'Klasifikasi simulasi']);
        DB::table('units')->insert(['code'=>'BH','name'=>'Buah']);
        DB::table('account_codes')->insert(['code'=>'5.01.001','name'=>'Rekening simulasi']);
        DB::table('account_codes')->insert(['code'=>'5.01.002','name'=>'Rekening simulasi kedua']);
    }

    private function package(string $type='SHS'):int
    {
        return $this->postJson('/usulan',['title'=>'Paket '.$type.' simulasi','type'=>$type,'submission_period_id'=>$this->periodId])->assertCreated()->json('id');
    }

    private function fields():array
    {
        return ['name'=>'Item simulasi','spec'=>'Spesifikasi simulasi','code'=>'001.020','unit'=>'BH','account'=>'5.01.001; 5.01.002','price'=>'1.234,50'];
    }

    private function item(int $proposal,array $overrides=[]):object
    {
        $id=$this->postJson('/usulan/'.$proposal.'/item',array_replace($this->fields(),$overrides)+['record_version'=>ProposalDraft::version(Proposal::findOrFail($proposal))])->assertCreated()->json('version_id');

        return DB::table('proposal_item_versions')->find($id);
    }

    private function latest(int $item):object
    {
        return DB::table('proposal_item_versions')->where('proposal_item_id',$item)->orderByDesc('version_number')->first();
    }

    private function pdf(string $suffix='one'):UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bukti-'.$suffix.'.pdf',"%PDF-1.4\n% synthetic ".$suffix."\n1 0 obj <</Type /Catalog>> endobj\n%%EOF");
    }

    private function evidence(int $proposal,int $item,string $suffix='one'):int
    {
        return $this->post('/usulan/'.$proposal.'/item/'.$item.'/bukti',['file'=>$this->pdf($suffix),'record_version'=>RecordVersion::of((array)$this->latest($item))],['Accept'=>'application/json'])->assertCreated()->json('id');
    }

    private function send(int $id)
    {
        return $this->postJson('/usulan/'.$id.'/kirim',['confirm'=>true,'record_version'=>ProposalDraft::version(Proposal::findOrFail($id))]);
    }

    private function xlsx(array $rows,string $name='simulasi.xlsx',?string $workbook=null):UploadedFile
    {
        $path=tempnam(sys_get_temp_dir(),'shs-xlsx-');$zip=new \ZipArchive;$zip->open($path,\ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml',$workbook??'<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data simulasi" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $xml='<worksheet><sheetData>';
        foreach($rows as$index=>$values){$number=$index+1;$xml.='<row r="'.$number.'">';foreach($values as$column=>$value){$ref=chr(65+$column).$number;
            if(is_array($value)){$xml.='<c r="'.$ref.'"><v>'.htmlspecialchars($value['number'],ENT_XML1).'</v></c>';}
            else{$xml.='<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string)$value,ENT_XML1).'</t></is></c>';}
        }$xml.='</row>';}$xml.='</sheetData></worksheet>';$zip->addFromString('xl/worksheets/sheet1.xml',$xml);$zip->close();$bytes=file_get_contents($path);unlink($path);

        return UploadedFile::fake()->createWithContent($name,$bytes);
    }

    private function upload(int $proposal,UploadedFile $file):int
    {
        return $this->post('/usulan/'.$proposal.'/impor',['file'=>$file,'sheet_index'=>0],['Accept'=>'application/json'])->assertCreated()->json('batch_id');
    }

    private function batchToken(int $id):string
    {
        return RecordVersion::of(ProposalImport::state(DB::table('import_batches')->find($id)));
    }

    public function test_shs_and_sbu_drafts_and_exact_money_survive_logout_and_login():void
    {
        $password=Str::random(36).'Aa9';$this->operator->password=$password;$this->operator->save();
        foreach(['SHS','SBU']as$type){$id=$this->package($type);$first=$this->item($id,['price'=>'999999999999999999.99']);$this->item($id,['name'=>'Item simulasi kedua']);
            $this->assertSame('999999999999999999.99',$first->proposed_price);
            $this->postJson('/auth/logout')->assertOk();$this->getJson('/usulan/'.$id)->assertUnauthorized();
            $this->postJson('/auth/login',['email'=>$this->operator->email,'password'=>$password])->assertOk();
            $this->get('/usulan/'.$id)->assertOk()->assertSee('Item simulasi kedua')->assertSee('999.999.999.999.999.999,99');
            $this->assertSame(2,DB::table('proposal_items')->where('proposal_id',$id)->count());
        }
    }

    public function test_incomplete_items_and_missing_evidence_block_submission_with_item_errors():void
    {
        $id=$this->package();$item=$this->item($id,['spec'=>'','price'=>'0','account'=>'']);
        $this->send($id)->assertUnprocessable()->assertJsonValidationErrors('items.'.$item->proposal_item_id);
        $this->assertDatabaseHas('proposals',['id'=>$id,'status'=>'draft','submitted_at'=>null]);
        $this->get('/usulan/'.$id.'/periksa')->assertOk()->assertSee('Bukti pendukung belum dilampirkan.');
        $this->get('/usulan/'.$id.'/item/'.$item->proposal_item_id.'/ubah')->assertOk();
        $this->get('/usulan/buat')->assertOk();$this->get('/usulan')->assertOk();
    }

    public function test_local_demo_can_submit_without_evidence_and_bkad_can_verify_it():void
    {
        // These tests change the app environment; keep the usual test-only CSRF bypass.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->app->instance('env','local'); config(['demo.allow_missing_evidence'=>true]);
        $id=$this->package(); $version=$this->item($id);
        $this->get('/usulan/'.$id.'/periksa')->assertOk()->assertSee('Demo lokal: bukti pendukung boleh dikosongkan.')
            ->assertSee('Data siap dikirim')->assertDontSee('Data dan bukti sudah lengkap')->assertDontSee('Bukti pendukung belum dilampirkan.');
        $this->send($id)->assertOk();
        $this->assertDatabaseHas('proposals',['id'=>$id,'status'=>'submitted']);
        $this->assertDatabaseCount('evidence_files',0);
        $event=DB::table('activity_logs')->where('action','proposal.submitted')->where('subject_id',$id)->first();
        $this->assertTrue(json_decode($event->after_values,true)['evidence_optional_demo']);
        $this->flushSession(); $this->actingAs($this->actor('bkad'));
        $this->get('/verifikasi/'.$version->proposal_item_id)->assertOk()->assertSee('Demo lokal: bukti pendukung boleh dikosongkan.');
        $this->postJson('/verifikasi/'.$version->proposal_item_id,[
            'result'=>'ready','record_version'=>\App\Support\ItemVerification::token(\App\Models\ProposalItem::findOrFail($version->proposal_item_id)),
        ])->assertOk();
        $this->assertDatabaseHas('proposal_items',['id'=>$version->proposal_item_id,'status'=>'ready']);
    }

    public function test_demo_flag_cannot_relax_evidence_outside_local_or_when_disabled():void
    {
        // These tests change the app environment; keep the usual test-only CSRF bypass.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $id=$this->package(); $item=$this->item($id);
        foreach(['production','staging','testing'] as $environment) {
            $this->app->instance('env',$environment); config(['demo.allow_missing_evidence'=>true]);
            $this->get('/usulan/'.$id.'/periksa')->assertOk()->assertSee('Bukti pendukung belum dilampirkan.')
                ->assertDontSee('Demo lokal: bukti pendukung boleh dikosongkan.');
            $this->send($id)->assertUnprocessable()->assertJsonValidationErrors('items.'.$item->proposal_item_id);
        }
        $this->app->instance('env','local'); config(['demo.allow_missing_evidence'=>false]);
        $this->postJson('/usulan/'.$id.'/kirim',['confirm'=>true,'demo_allow_missing_evidence'=>true,
            'record_version'=>ProposalDraft::version(Proposal::findOrFail($id))])->assertUnprocessable();
        $this->assertDatabaseHas('proposals',['id'=>$id,'status'=>'draft','submitted_at'=>null]);
    }

    public function test_local_demo_still_requires_item_fields_and_an_open_period():void
    {
        // These tests change the app environment; keep the usual test-only CSRF bypass.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->app->instance('env','local'); config(['demo.allow_missing_evidence'=>true]);
        $id=$this->package(); $version=$this->item($id,['account'=>'','price'=>'0','spec'=>'']);
        $this->send($id)->assertUnprocessable()->assertJsonValidationErrors('items.'.$version->proposal_item_id);
        $this->assertDatabaseHas('proposals',['id'=>$id,'status'=>'draft']);
        $complete=$this->package(); $this->item($complete);
        DB::table('submission_periods')->where('id',$this->periodId)->update(['status'=>'closed']);
        $this->send($complete)->assertUnprocessable();
        $this->assertDatabaseHas('proposals',['id'=>$complete,'status'=>'draft']);
    }

    public function test_local_demo_does_not_ignore_missing_physical_files_already_attached():void
    {
        // These tests change the app environment; keep the usual test-only CSRF bypass.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->app->instance('env','local'); config(['demo.allow_missing_evidence'=>true]);
        $id=$this->package(); $version=$this->item($id); $file=$this->evidence($id,$version->proposal_item_id);
        Storage::disk('local')->delete(DB::table('evidence_files')->where('id',$file)->value('path'));
        $this->send($id)->assertUnprocessable()->assertJsonValidationErrors('evidence');
        $this->assertDatabaseHas('proposals',['id'=>$id,'status'=>'draft']);
    }

    public function test_editing_items_appends_immutable_versions_and_rejects_stale_edits():void
    {
        $id=$this->package();$old=$this->item($id);$token=RecordVersion::of((array)$old);
        $this->patchJson('/usulan/'.$id.'/item/'.$old->proposal_item_id,array_replace($this->fields(),['name'=>'Versi revisi','record_version'=>$token]))->assertOk();
        $this->patchJson('/usulan/'.$id.'/item/'.$old->proposal_item_id,$this->fields()+['record_version'=>$token])->assertStatus(409);
        $this->assertDatabaseHas('proposal_item_versions',['id'=>$old->id,'description'=>'Item simulasi','proposed_price'=>'1234.50']);
        $this->assertSame(2,$this->latest($old->proposal_item_id)->version_number);
        $this->get('/usulan/'.$id.'/item/'.$old->proposal_item_id.'/versi/'.$old->id)->assertOk()->assertSee('Item simulasi');
    }

    public function test_multiple_private_evidence_files_and_older_version_links_survive_replacement_and_relogin():void
    {
        $id=$this->package();$item=$this->item($id);$itemId=$item->proposal_item_id;
        $first=$this->evidence($id,$itemId,'one');$versionWithOne=$this->latest($itemId);
        $second=$this->evidence($id,$itemId,'two');$versionWithBoth=$this->latest($itemId);
        $this->assertSame(2,DB::table('item_version_evidence')->where('item_version_id',$versionWithBoth->id)->count());
        $this->deleteJson('/usulan/'.$id.'/item/'.$itemId.'/bukti/'.$first,['record_version'=>RecordVersion::of((array)$versionWithBoth)])->assertOk();
        $this->assertSame(2,DB::table('item_version_evidence')->where('item_version_id',$versionWithBoth->id)->count());
        $this->assertSame(1,DB::table('item_version_evidence')->where('item_version_id',$this->latest($itemId)->id)->count());
        $this->assertCount(2,Storage::disk('local')->allFiles());
        $this->get('/usulan/'.$id.'/item/'.$itemId.'/versi/'.$versionWithOne->id)->assertOk()->assertSee('bukti-one.pdf');
        $password=Str::random(36).'Aa9';$this->operator->password=$password;$this->operator->save();$this->postJson('/auth/logout');
        $this->getJson('/bukti/'.$first)->assertUnauthorized();$this->postJson('/auth/login',['email'=>$this->operator->email,'password'=>$password])->assertOk();
        $response=$this->get('/bukti/'.$first)->assertOk()->assertHeader('Content-Type','application/pdf')->assertHeader('X-Content-Type-Options','nosniff');
        $this->assertStringContainsString('attachment',$response->headers->get('Content-Disposition'));
        $this->get('/bukti/'.$second)->assertOk();
    }

    public function test_submitted_versions_are_stamped_locked_and_visible_to_bkad_but_not_other_skpd():void
    {
        $id=$this->package();$item=$this->item($id);$file=$this->evidence($id,$item->proposal_item_id);$version=$this->latest($item->proposal_item_id);
        $this->send($id)->assertOk();$this->assertDatabaseHas('proposals',['id'=>$id,'status'=>'submitted']);
        $this->assertNotNull(DB::table('proposal_item_versions')->where('id',$version->id)->value('submitted_at'));
        $this->patchJson('/usulan/'.$id.'/item/'.$item->proposal_item_id,$this->fields()+['record_version'=>RecordVersion::of((array)$version)])->assertForbidden();
        $this->post('/usulan/'.$id.'/item/'.$item->proposal_item_id.'/bukti',['file'=>$this->pdf('new'),'record_version'=>RecordVersion::of((array)$version)],['Accept'=>'application/json'])->assertForbidden();
        $this->get('/usulan/'.$id.'/periksa')->assertOk()->assertSee('Usulan diajukan');
        $this->flushSession();$this->actingAs($this->actor('bkad'));$this->get('/usulan/'.$id)->assertOk();$this->get('/bukti/'.$file)->assertOk();
        $this->flushSession();$this->actingAs($this->actor('skpd_operator',$this->organization()));
        $this->get('/usulan/'.$id)->assertForbidden();$this->get('/bukti/'.$file)->assertForbidden();$this->get('/usulan')->assertOk()->assertDontSee('Paket SHS simulasi');
    }

    public function test_real_submission_rejects_closed_future_and_expired_windows_then_accepts_open_period():void
    {
        $id=$this->package();$item=$this->item($id);$this->evidence($id,$item->proposal_item_id);
        foreach([['status'=>'closed'],['status'=>'draft'],['status'=>'open','opens_at'=>now()->addDay(),'closes_at'=>now()->addDays(2)],['status'=>'open','opens_at'=>now()->subDays(2),'closes_at'=>now()->subDay()]]as$change){DB::table('submission_periods')->where('id',$this->periodId)->update($change);$this->send($id)->assertUnprocessable()->assertJsonValidationErrors('submission_period_id');}
        DB::table('submission_periods')->where('id',$this->periodId)->update(['status'=>'open','opens_at'=>now()->subDay(),'closes_at'=>now()->addDay()]);
        $this->send($id)->assertOk();
    }

    public function test_evidence_type_size_duplicates_and_stale_uploads_do_not_leave_orphan_files():void
    {
        $id=$this->package();$item=$this->item($id);$token=RecordVersion::of((array)$item);$url='/usulan/'.$id.'/item/'.$item->proposal_item_id.'/bukti';
        $this->post($url,['file'=>UploadedFile::fake()->createWithContent('bad.txt','plain text'),'record_version'=>$token],['Accept'=>'application/json'])->assertUnprocessable();
        $this->post($url,['file'=>UploadedFile::fake()->create('too-large.pdf',11000,'application/pdf'),'record_version'=>$token],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertCount(0,Storage::disk('local')->allFiles());$this->evidence($id,$item->proposal_item_id);
        $this->post($url,['file'=>$this->pdf('new'),'record_version'=>$token],['Accept'=>'application/json'])->assertStatus(409);
        $this->post($url,['file'=>$this->pdf(),'record_version'=>RecordVersion::of((array)$this->latest($item->proposal_item_id))],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertCount(1,Storage::disk('local')->allFiles());
    }

    public function test_excel_preview_skips_headers_groups_and_requires_corrections_before_atomic_deduplicated_commit():void
    {
        $id=$this->package();$headers=['Uraian','Spesifikasi','Kode barang','Rekening','Satuan','Harga'];
        $file=$this->xlsx([['Judul usulan'], $headers,['Kelompok contoh','','001.020'],['Item Excel satu','Spesifikasi','001.020','5.01.001','BH','1.234,50'],
            ['Item Excel dua','Spesifikasi','001.020','5.01.002','BH',''], $headers,['Item kode angka','Spesifikasi',['number'=>'1'],'5.01.001','BH','1000']]);
        $batch=$this->upload($id,$file);$summary=json_decode(DB::table('import_batches')->where('id',$batch)->value('result_summary'),true);
        $this->assertSame(1,$summary['valid']);$this->assertSame(2,$summary['invalid']);$this->assertSame(4,$summary['ignored']);$this->assertDatabaseCount('proposal_items',0);
        $this->get('/usulan/'.$id.'/impor/'.$batch)->assertOk()->assertSee('Item Excel dua');
        $this->postJson('/usulan/'.$id.'/impor/'.$batch.'/simpan',['record_version'=>$this->batchToken($batch)])->assertUnprocessable();$this->assertDatabaseCount('proposal_items',0);
        $row=DB::table('import_rows')->where('import_batch_id',$batch)->where('row_number',5)->first();
        $this->get('/usulan/'.$id.'/impor/'.$batch.'/baris/'.$row->id)->assertOk();
        $this->patchJson('/usulan/'.$id.'/impor/'.$batch.'/baris/'.$row->id,['operation'=>'correct','record_version'=>$this->batchToken($batch),'fields'=>array_replace($this->fields(),['name'=>'Item Excel dua','price'=>'125000'])])->assertOk();
        $numeric=DB::table('import_rows')->where('import_batch_id',$batch)->where('row_number',7)->first();
        $this->patchJson('/usulan/'.$id.'/impor/'.$batch.'/baris/'.$numeric->id,['operation'=>'skip','record_version'=>$this->batchToken($batch)])->assertOk();
        $token=$this->batchToken($batch);$this->postJson('/usulan/'.$id.'/impor/'.$batch.'/simpan',['record_version'=>$token])->assertOk();$this->assertDatabaseCount('proposal_items',2);
        $this->assertSame(2,DB::table('import_rows')->where('import_batch_id',$batch)->whereNotNull('item_version_id')->count());
        $this->postJson('/usulan/'.$id.'/impor/'.$batch.'/simpan',['record_version'=>$token])->assertOk();$this->assertSame($batch,$this->upload($id,$file));$this->assertDatabaseCount('proposal_items',2);
        $this->assertSame('1.234,50',json_decode(DB::table('import_rows')->where('import_batch_id',$batch)->where('row_number',4)->value('raw_values'),true)['cells'][5]['value']);
    }

    public function test_cancelled_preview_can_resume_and_stale_mapping_is_rejected():void
    {
        $id=$this->package();$file=$this->xlsx([['Uraian','Spesifikasi','Kode barang','Rekening','Satuan','Harga'],['Item impor','Spec','001.020','5.01.001','BH','1000']]);
        $batch=$this->upload($id,$file);$old=$this->batchToken($batch);
        $this->postJson('/usulan/'.$id.'/impor/'.$batch.'/batal',['record_version'=>$old])->assertOk();$this->assertSame($batch,$this->upload($id,$file));
        $map=['name'=>0,'spec'=>1,'code'=>2,'account'=>3,'unit'=>4,'price'=>5];
        $this->patchJson('/usulan/'.$id.'/impor/'.$batch.'/pemetaan',['record_version'=>$old,'header'=>1,'columns'=>$map])->assertStatus(409);
        $this->patchJson('/usulan/'.$id.'/impor/'.$batch.'/pemetaan',['record_version'=>$this->batchToken($batch),'header'=>1,'columns'=>$map])->assertOk();
        $this->postJson('/usulan/'.$id.'/impor/'.$batch.'/simpan',['record_version'=>$this->batchToken($batch)])->assertOk();$this->assertDatabaseCount('proposal_items',1);
    }

    public function test_invalid_excel_archive_and_unsafe_xml_are_rejected_without_preview_rows():void
    {
        $id=$this->package();$this->post('/usulan/'.$id.'/impor',['file'=>UploadedFile::fake()->createWithContent('invalid.xlsx','not a ZIP'),'sheet_index'=>0],['Accept'=>'application/json'])->assertUnprocessable();
        $file=$this->xlsx([['Uraian']],'unsafe.xlsx','<!DOCTYPE workbook [<!ENTITY external SYSTEM "https://example.invalid/not-read">]><workbook>&external;</workbook>');
        $this->post('/usulan/'.$id.'/impor',['file'=>$file,'sheet_index'=>0],['Accept'=>'application/json'])->assertUnprocessable();$this->assertDatabaseCount('import_batches',0);
    }

    public function test_cross_package_item_and_import_mutations_cannot_escape_owner_scope():void
    {
        $first=$this->package();$item=$this->item($first);$file=$this->evidence($first,$item->proposal_item_id);
        $batch=$this->upload($first,$this->xlsx([['Uraian','Spesifikasi','Kode barang','Rekening','Satuan','Harga'],['Impor aman','Spec','001.020','5.01.001','BH','1000']]));
        $second=$this->package();$this->patchJson('/usulan/'.$second.'/item/'.$item->proposal_item_id,$this->fields()+['record_version'=>RecordVersion::of((array)$item)])->assertNotFound();
        $this->flushSession();$this->actingAs($this->actor('skpd_operator',$this->organization()));
        $this->get('/usulan/'.$first.'/impor/'.$batch)->assertForbidden();$this->postJson('/usulan/'.$first.'/impor/'.$batch.'/simpan',['record_version'=>$this->batchToken($batch)])->assertForbidden();
        $this->get('/bukti/'.$file)->assertForbidden();$this->get('/usulan/'.$first.'/item/'.$item->proposal_item_id.'/versi/'.$item->id)->assertForbidden();
    }

    public function test_assigned_examiner_reads_only_its_items_and_bkad_cannot_read_drafts():void
    {
        $id=$this->package();$first=$this->item($id,['name'=>'Item ditugaskan']);$second=$this->item($id,['name'=>'Item belum ditugaskan']);
        $firstFile=$this->evidence($id,$first->proposal_item_id,'first');$secondFile=$this->evidence($id,$second->proposal_item_id,'second');$this->send($id)->assertOk();
        $examiner=$this->actor('examiner');$f=['period_id'=>$this->periodId,'proposal'=>Proposal::findOrFail($id),'item'=>\App\Models\ProposalItem::findOrFail($first->proposal_item_id),'version'=>\App\Models\ProposalItemVersion::findOrFail($this->latest($first->proposal_item_id)->id)];$this->scope($f,$examiner);
        $this->flushSession();$this->actingAs($examiner);$this->get('/usulan/'.$id)->assertOk()->assertSee('Item ditugaskan')->assertDontSee('Item belum ditugaskan');$this->get('/bukti/'.$firstFile)->assertOk();$this->get('/bukti/'.$secondFile)->assertForbidden();
        $this->flushSession();$this->actingAs($this->operator);$draft=$this->package();$this->flushSession();$this->actingAs($this->actor('bkad'));$this->get('/usulan/'.$draft)->assertForbidden();
    }

    public function test_submit_permission_is_independent_of_edit_permission():void
    {
        $id=$this->package();$item=$this->item($id);$this->evidence($id,$item->proposal_item_id);
        DB::table('permission_role')->where('role_id',DB::table('roles')->where('code','skpd_operator')->value('id'))
            ->where('permission_id',DB::table('permissions')->where('code','proposals.update_own')->value('id'))->delete();
        $this->get('/usulan/'.$id.'/item/'.$item->proposal_item_id.'/ubah')->assertForbidden();
        $this->get('/usulan/'.$id.'/periksa')->assertOk()->assertSee('name="record_version"',false)->assertSee(ProposalDraft::version(Proposal::findOrFail($id)));
        $this->send($id)->assertOk();$this->assertDatabaseHas('proposals',['id'=>$id,'status'=>'submitted']);
    }

    public function test_mapping_includes_data_columns_without_header_cells():void
    {
        $id=$this->package();$batch=$this->upload($id,$this->xlsx([['Uraian','Spesifikasi','Kode barang'],['Item kolom tanpa judul','Spec','001.020','5.01.001','BH','1000']]));
        $this->get('/usulan/'.$id.'/impor/'.$batch)->assertOk()->assertSee('value="5"',false)->assertSee('Kolom 6');
        $this->patchJson('/usulan/'.$id.'/impor/'.$batch.'/pemetaan',['record_version'=>$this->batchToken($batch),'header'=>1,
            'columns'=>['name'=>0,'spec'=>1,'code'=>2,'account'=>3,'unit'=>4,'price'=>5]])->assertOk();
        $this->postJson('/usulan/'.$id.'/impor/'.$batch.'/simpan',['record_version'=>$this->batchToken($batch)])->assertOk();
        $this->assertDatabaseCount('proposal_items',1);$this->assertDatabaseHas('proposal_item_versions',['description'=>'Item kolom tanpa judul','proposed_price'=>'1000.00']);
    }

    public function test_server_ignores_client_scope_and_requires_csrf_for_business_mutations():void
    {
        $this->postJson('/usulan',['title'=>'Scope ilegal','type'=>'SHS','submission_period_id'=>$this->periodId,'organization_id'=>$this->organization()])->assertUnprocessable();
        $id=$this->package();$this->app->instance('env','production');$this->post('/usulan/'.$id.'/item',$this->fields())->assertStatus(419);$this->post('/usulan/'.$id.'/kirim',['confirm'=>true])->assertStatus(419);
    }
}
