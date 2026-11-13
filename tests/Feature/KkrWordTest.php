<?php

namespace Tests\Feature;

use App\Models\{KkrDocument,KkrVersion,ReviewAssignment};
use App\Support\{KkrWork,KkrPrint};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class KkrWordTest extends MySqlAccessTestCase
{
    private $owner; private $examiner; private $lead; private $controller; private array $f; private array $scope;
    protected function setUp(): void
    {
        parent::setUp();Storage::fake('local');$this->owner=$this->actor('skpd_operator',$this->organization());$this->examiner=$this->actor('examiner');$this->lead=$this->actor('team_lead');$this->controller=$this->actor('technical_controller');
        $this->f=$this->submission($this->owner);$this->scope=$this->scope($this->f,$this->examiner,$this->lead,$this->controller);
        DB::table('proposals')->where('id',$this->f['proposal']->id)->update(['status'=>'review']);DB::table('proposal_items')->where('id',$this->f['item']->id)->update(['status'=>'reviewed']);
        DB::table('proposal_item_versions')->where('id',$this->f['version']->id)->update(['description'=>'Item <simulasi> & khusus','proposed_price'=>'123456789012345678.90',
            'reference_snapshot'=>json_encode(['classification'=>['code'=>'001.020'],'unit'=>['name'=>'Paket simulasi']])]);
        DB::table('item_reviews')->where('id',$this->scope['review']->id)->update(['status'=>'completed','completed_at'=>now(),'comparison_price'=>'1250.25','recommended_price'=>'1100.25','recommendation'=>"Rekomendasi item\nBaris kedua simulasi"]);
        foreach(DB::table('review_aspects')->where('is_active',true)->get() as $a){DB::table('review_results')->insert(['item_review_id'=>$this->scope['review']->id,'review_aspect_id'=>$a->id,'result'=>'compliant',
            'criterion_snapshot'=>json_encode(['code'=>$a->code,'name'=>$a->name,'is_required'=>(bool)$a->is_required]),'reason'=>'Alasan simulasi','recommendation'=>'Rekomendasi aspek simulasi']);}
        $this->asActor($this->examiner);
    }
    private function asActor($actor): void { $this->flushSession();$this->actingAs($actor); }
    private function document(array $extra=[]): KkrDocument
    {
        $this->asActor($this->examiner);$a=ReviewAssignment::findOrFail($this->scope['assignment_id']);
        $id=$this->postJson('/penugasan/'.$a->id.'/kkr',array_replace(['record_version'=>KkrWork::createToken($this->examiner,$a),'number'=>'SIM-'.Str::uuid(),
            'title'=>'KKR Word simulasi','template'=>KkrPrint::TEMPLATE,'purpose'=>'Tujuan simulasi','work_steps'=>"Langkah satu\nLangkah dua",'conclusion'=>'Kesimpulan simulasi',
            'recommendation'=>'Rekomendasi akhir simulasi','reviews'=>[$this->scope['review']->id]],$extra))->assertCreated()->json('id');
        return KkrDocument::findOrFail($id);
    }
    private function version(KkrDocument $doc): KkrVersion { return KkrWork::latest($doc->fresh()); }
    private function url(KkrDocument $doc,string $kind='word',?KkrVersion $version=null): string { return '/kkr/'.$doc->id.'/versi/'.($version??$this->version($doc))->id.'/'.$kind; }
    private function seal(KkrDocument $doc): void
    {
        $this->asActor($this->examiner);$v=$this->version($doc);
        $this->postJson('/kkr/'.$doc->id.'/ajukan',['record_version'=>KkrWork::token($doc->fresh()),'version_id'=>$v->id,'confirm'=>1])->assertOk();
        foreach (['team_lead'=>$this->lead,'technical_controller'=>$this->controller] as $level=>$actor) {
            $this->asActor($actor);$this->postJson('/kkr/'.$doc->id.'/versi/'.$v->id.'/keputusan',['record_version'=>KkrWork::token($doc->fresh()),'level'=>$level,'decision'=>'approved','notes'=>'Supervisi simulasi'])->assertOk();
        }
    }
    private function xml(string $bytes): \DOMDocument
    {
        $path=tempnam(sys_get_temp_dir(),'word-test-');file_put_contents($path,$bytes);$zip=new \ZipArchive;
        try {$this->assertTrue($zip->open($path));$xml=$zip->getFromName('word/document.xml');$zip->close();$dom=new \DOMDocument;$this->assertTrue($dom->loadXML($xml,LIBXML_NONET));return $dom;}
        finally {unlink($path);}
    }

    public function test_preview_and_word_share_all_eight_cells_and_narratives_without_rounding_or_html_injection(): void
    {
        $doc=$this->document();$v=$this->version($doc);$m=KkrPrint::model($v);
        $preview=$this->get($this->url($doc,'preview'))->assertOk()->assertSee('1 baris hasil reviu')->assertSee('Item <simulasi> & khusus')->assertSee('Rp 123.456.789.012.345.678,90')->assertSee('Unduh Word (.docx)');
        $this->assertSame($m,$preview->viewData('print'));
        $response=$this->get($this->url($doc))->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.wordprocessingml.document')->assertHeader('X-Content-Type-Options','nosniff');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $dom=$this->xml($response->getContent());$xpath=new \DOMXPath($dom);$xpath->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $this->assertSame(2,$xpath->query('//w:tbl[count(w:tblGrid/w:gridCol)=8]/w:tr')->length);
        foreach ($xpath->query('//w:tbl[count(w:tblGrid/w:gridCol)=8]/w:tr') as $i=>$row) {
            $cells=$xpath->query('w:tc',$row);$this->assertSame(8,$cells->length);
            foreach ($cells as $j=>$cell) {
                $value='';foreach($xpath->query('.//w:t|.//w:br',$cell) as $part){$value.=$part->localName==='br'?"\n":$part->textContent;}
                $this->assertSame(($i===0?$m['headers']:$m['rows'][0])[$j],$value);
            }
        }
        foreach (['Tujuan simulasi','Langkah satu','Langkah dua','Kesimpulan simulasi','Rekomendasi akhir simulasi','001.020','Paket simulasi'] as $text) { $this->assertStringContainsString($text,$dom->textContent); }
        $this->assertDatabaseCount('kkr_exports',0);$this->assertSame($response->getContent(),$this->get($this->url($doc))->getContent());
    }

    public function test_final_archive_is_created_with_approval_and_remains_byte_identical_after_source_changes(): void
    {
        $doc=$this->document();$this->seal($doc);$this->assertDatabaseCount('kkr_exports',1);$v=$this->version($doc);$export=DB::table('kkr_exports')->first();
        $first=$this->get($this->url($doc))->assertOk()->getContent();$before=$this->get($this->url($doc,'preview'))->viewData('print');
        $this->assertSame(hash('sha256',$first),$export->sha256);$this->assertSame($first,Storage::disk('local')->get($export->path));
        $this->assertCount(2,$before['supervision']);
        DB::table('organizations')->where('id',$this->owner->organization_id)->update(['name'=>'Nama master baru']);DB::table('users')->where('id',$this->lead->id)->update(['name'=>'Ketua baru']);
        DB::table('proposal_item_versions')->where('id',$this->f['version']->id)->update(['description'=>'Sumber berubah','proposed_price'=>'0.00']);
        DB::table('units')->where('id',$this->f['version']->unit_id)->update(['name'=>'Satuan baru']);
        $this->assertSame($first,$this->get($this->url($doc))->assertOk()->getContent());
        $this->assertSame($before,$this->get($this->url($doc,'preview'))->assertOk()->assertDontSee('Nama master baru')->assertDontSee('Sumber berubah')->viewData('print'));
        $this->assertDatabaseCount('kkr_exports',1);$this->assertTrue(KkrWork::integrity($v->fresh()));
    }

    public function test_corrupt_or_missing_archive_fails_closed_without_overwriting_or_regenerating(): void
    {
        $doc=$this->document();$this->seal($doc);$archive=DB::table('kkr_exports')->first();$original=Storage::disk('local')->get($archive->path);
        Storage::disk('local')->put($archive->path,'corrupt');$this->getJson($this->url($doc))->assertConflict();$this->getJson($this->url($doc,'preview'))->assertConflict();
        $this->assertSame('corrupt',Storage::disk('local')->get($archive->path));
        Storage::disk('local')->delete($archive->path);$this->getJson($this->url($doc))->assertConflict();$this->assertFalse(Storage::disk('local')->exists($archive->path));
        Storage::disk('local')->put($archive->path,$original);DB::table('kkr_exports')->where('id',$archive->id)->update(['render_snapshot'=>'{}']);
        $this->getJson($this->url($doc,'preview'))->assertConflict();$this->assertDatabaseCount('kkr_exports',1);
    }

    public function test_outsiders_inactive_members_and_revoked_download_permission_cannot_access_word(): void
    {
        $doc=$this->document();$other=$this->document(['number'=>'SIM-OTHER-'.Str::uuid()]);$otherVersion=$this->version($other);
        $this->getJson($this->url($doc,'word',$otherVersion))->assertNotFound();
        foreach ([$this->owner,$this->actor('admin'),$this->actor('bkad'),$this->actor('examiner'),$this->actor('team_lead'),$this->actor('technical_controller')] as $actor) {
            $this->asActor($actor);foreach(['preview','word'] as $kind){$this->getJson($this->url($doc,$kind))->assertForbidden();}
        }
        foreach ([$this->examiner,$this->lead,$this->controller] as $actor) {$this->asActor($actor);$this->get($this->url($doc))->assertOk();}
        DB::table('assignment_members')->where('id',$this->scope['members']['examiner'])->update(['is_active'=>false]);$this->asActor($this->examiner);$this->getJson($this->url($doc))->assertForbidden();
        $permission=DB::table('permissions')->where('code','kkr.download')->value('id');$role=DB::table('roles')->where('code','team_lead')->value('id');
        DB::table('permission_role')->where('role_id',$role)->where('permission_id',$permission)->delete();$this->asActor($this->lead);
        $this->get($this->url($doc,'preview'))->assertOk()->assertDontSee('Unduh Word (.docx)');$this->getJson($this->url($doc))->assertForbidden();
        $this->assertDatabaseCount('kkr_exports',0);
    }

    public function test_historical_versions_are_explicit_and_cannot_be_swapped_in_download_urls(): void
    {
        $doc=$this->document();$old=$this->version($doc);$a=ReviewAssignment::findOrFail($this->scope['assignment_id']);
        $this->postJson('/kkr/'.$doc->id.'/versi',['record_version'=>KkrWork::token($doc->fresh()),'title'=>'Judul revisi simulasi','conclusion'=>'Kesimpulan revisi',
            'recommendation'=>'Rekomendasi revisi','purpose'=>'Tujuan revisi','work_steps'=>'Langkah revisi','change_reason'=>'Perbaikan simulasi','reviews'=>[$this->scope['review']->id]])->assertOk();
        $new=$this->version($doc);$this->assertNotSame($old->id,$new->id);
        $oldBytes=$this->get($this->url($doc,'word',$old))->assertOk()->getContent();$newBytes=$this->get($this->url($doc,'word',$new))->assertOk()->getContent();
        $this->assertStringContainsString('Kesimpulan simulasi',$this->xml($oldBytes)->textContent);$this->assertStringNotContainsString('Kesimpulan revisi',$this->xml($oldBytes)->textContent);
        $this->assertStringContainsString('Kesimpulan revisi',$this->xml($newBytes)->textContent);
        $this->get('/kkr/'.$doc->id.'?version='.$old->id)->assertOk()->assertSee($this->url($doc,'word',$old),false);
    }

    public function test_unsupported_template_and_tampered_source_do_not_produce_word(): void
    {
        $doc=$this->document();$v=$this->version($doc);DB::table('kkr_versions')->where('id',$v->id)->update(['template_version'=>'unrecognized']);
        $this->getJson($this->url($doc))->assertConflict();DB::table('kkr_versions')->where('id',$v->id)->update(['template_version'=>KkrPrint::TEMPLATE]);
        DB::table('kkr_entries')->where('kkr_version_id',$v->id)->update(['snapshot'=>'{}']);$this->getJson($this->url($doc))->assertConflict();$this->getJson($this->url($doc,'preview'))->assertConflict();
        $this->assertDatabaseCount('kkr_exports',0);
    }
}
