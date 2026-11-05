<?php

namespace Tests\Feature;

use App\Support\{ItemReport,ProposalXlsx};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class ItemReportTest extends MySqlAccessTestCase
{
    private $owner;private $other;private $bkad;private $examiner;private $lead;private $controller;private array $f;private array $scope;private array $paths=[];
    protected function setUp(): void
    {
        parent::setUp();$this->owner=$this->actor('skpd_operator',$this->organization());$this->other=$this->actor('skpd_operator',$this->organization());$this->bkad=$this->actor('bkad');
        $this->examiner=$this->actor('examiner');$this->lead=$this->actor('team_lead');$this->controller=$this->actor('technical_controller');
        $this->f=$this->submission($this->owner);$this->scope=$this->scope($this->f,$this->examiner,$this->lead,$this->controller);
        DB::table('organizations')->where('id',$this->owner->organization_id)->update(['name'=>'SKPD pemilik simulasi']);DB::table('organizations')->where('id',$this->other->organization_id)->update(['name'=>'SKPD lain simulasi']);
        DB::table('proposals')->where('id',$this->f['proposal']->id)->update(['number'=>'SIM-REKAP']);
        DB::table('proposal_item_versions')->where('id',$this->f['version']->id)->update(['description'=>'Komputer simulasi 100%_A','specification'=>'Spesifikasi <simulasi> & aman','proposed_price'=>'123456789012345678.90',
            'reference_snapshot'=>json_encode(['classification'=>['code'=>'001.020%_A'],'unit'=>['name'=>'Paket'],'accounts'=>[['code'=>'00001.02'],['code'=>'00002.03']]])]);
        $this->f['version']->refresh();$this->asActor($this->owner);
    }
    protected function tearDown(): void {foreach($this->paths as $path){if(is_file($path)){unlink($path);}}parent::tearDown();}
    private function asActor($actor): void {$this->flushSession();$this->actingAs($actor);}
    private function page($actor,string $query='') {$this->asActor($actor);return $this->get('/rekap'.$query)->assertOk();}
    private function export(string $query=''): array
    {
        $response=$this->get('/rekap/excel'.$query)->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->assertHeader('X-Content-Type-Options','nosniff');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));$response->assertDownload('Rekap-SHS-SBU.xlsx');
        $path=$response->baseResponse->getFile()->getPathname();$this->paths[]=$path;
        return (new ProposalXlsx)->read($path)['rows'];
    }
    private function values(array $rows): array {return array_map(fn($row)=>array_column($row,'value'),array_values($rows));}

    public function test_combined_filters_search_saved_code_or_description_and_export_the_same_selected_item(): void
    {
        $foreign=$this->submission($this->other);
        $query='?q='.urlencode('001.020%_A').'&year=2026&type=SHS&status=assigned&organization='.$this->owner->organization_id;
        $page=$this->page($this->bkad,$query);$this->assertSame(1,$page->viewData('rows')->total());$page->assertSee('Komputer simulasi 100%_A')->assertSee('Rp 123.456.789.012.345.678,90');
        $data=$this->values($this->export($query));$this->assertCount(2,$data);$this->assertSame(ItemReport::HEADERS,$data[0]);
        $this->assertSame('001.020%_A',$data[1][5]);$this->assertSame('00001.02; 00002.03',$data[1][9]);$this->assertSame('123.456.789.012.345.678,90',$data[1][10]);
        $this->assertSame(1,$this->page($this->bkad,'?q=Komputer')->viewData('rows')->total());
        foreach(['?q=tidak-ditemukan','?year=2027','?type=SBU','?status=ready'] as $filter){$this->assertSame(0,$this->page($this->bkad,$filter)->viewData('rows')->total());}
    }

    public function test_percent_underscore_backslash_and_quotes_are_literal_search_text_not_wildcards(): void
    {
        $other=$this->submission($this->owner);DB::table('proposal_item_versions')->where('id',$other['version']->id)->update(['description'=>'Item 100XXA pembanding']);
        $this->assertSame(1,$this->page($this->owner,'?q='.urlencode('100%_A'))->viewData('rows')->total());
        $this->assertSame(0,$this->page($this->owner,'?q='.urlencode("' OR 1=1 --"))->viewData('rows')->total());
        DB::table('proposal_item_versions')->where('id',$other['version']->id)->update(['description'=>'Jalur \\simulasi']);
        $this->assertSame(1,$this->page($this->owner,'?q='.urlencode('\\simulasi'))->viewData('rows')->total());
    }

    public function test_own_and_global_scope_filter_options_and_exports_never_reveal_foreign_drafts(): void
    {
        $foreign=$this->submission($this->other);DB::table('proposals')->where('id',$foreign['proposal']->id)->update(['status'=>'draft']);
        DB::table('proposal_item_versions')->where('id',$foreign['version']->id)->update(['description'=>'Draf SKPD lain rahasia','submitted_at'=>null]);
        $page=$this->page($this->owner);$this->assertCount(1,$page->viewData('organizations'));$page->assertDontSee('SKPD lain simulasi')->assertDontSee('Draf SKPD lain rahasia');$this->assertCount(2,$this->export());
        $this->asActor($this->owner);$this->getJson('/rekap?organization='.$this->other->organization_id)->assertForbidden();$this->getJson('/rekap/excel?organization='.$this->other->organization_id)->assertForbidden();
        $global=$this->page($this->bkad);$this->assertSame(1,$global->viewData('rows')->total());$this->assertCount(1,$global->viewData('organizations'));$this->assertCount(2,$this->export());
    }

    public function test_assigned_team_sees_only_assigned_siblings_and_submitted_versions_in_search_and_export(): void
    {
        $sibling=DB::table('proposal_items')->insertGetId(['proposal_id'=>$this->f['proposal']->id,'public_id'=>Str::uuid(),'status'=>'submitted']);
        $version=$this->f['version']->getAttributes();unset($version['id']);
        DB::table('proposal_item_versions')->insert(array_replace($version,['proposal_item_id'=>$sibling,'description'=>'Saudara tidak ditugaskan']));
        DB::table('proposal_item_versions')->insert(array_replace($version,['version_number'=>2,'description'=>'Revisi privat belum terkirim','submitted_at'=>null]));
        foreach([$this->examiner,$this->lead,$this->controller] as $actor){
            $page=$this->page($actor);$this->assertSame(1,$page->viewData('rows')->total());$page->assertSee('Komputer simulasi')->assertDontSee('Revisi privat')->assertDontSee('Saudara tidak ditugaskan');
            $rows=$this->values($this->export());$this->assertCount(2,$rows);$this->assertSame('Komputer simulasi 100%_A',$rows[1][6]);
            $this->assertSame(0,$this->page($actor,'?q=privat')->viewData('rows')->total());
        }
        $this->page($this->owner)->assertSee('Revisi privat belum terkirim');
    }

    public function test_export_includes_all_filtered_pages_and_all_database_chunks_once(): void
    {
        $items=[];for($i=0;$i<512;$i++){$items[]=['proposal_id'=>$this->f['proposal']->id,'public_id'=>Str::uuid()->toString(),'status'=>'assigned'];}DB::table('proposal_items')->insert($items);
        $version=$this->f['version']->getAttributes();unset($version['id']);$versions=[];
        foreach(DB::table('proposal_items')->where('proposal_id',$this->f['proposal']->id)->where('id','!=',$this->f['item']->id)->pluck('id') as $id){$versions[]=array_replace($version,['proposal_item_id'=>$id,'description'=>'Baris simulasi '.$id]);}
        foreach(array_chunk($versions,200) as $chunk){DB::table('proposal_item_versions')->insert($chunk);}
        $page=$this->page($this->owner,'?status=assigned&page=2');$this->assertCount(20,$page->viewData('rows')->items());$this->assertSame(513,$page->viewData('rows')->total());
        $rows=$this->values($this->export('?status=assigned&page=2'));$this->assertCount(514,$rows);
        $this->assertSame(range(1,513),array_map('intval',array_column(array_slice($rows,1),0)));$this->assertCount(513,array_unique(array_column(array_slice($rows,1),6)));
        $this->assertDatabaseHas('activity_logs',['actor_id'=>$this->owner->id,'action'=>'report.excel_exported']);
    }

    public function test_saved_reference_codes_are_used_even_after_master_codes_change_and_repeated_accounts_do_not_duplicate_rows(): void
    {
        DB::table('item_classifications')->where('id',$this->f['version']->item_classification_id)->update(['code'=>'NEW-MASTER']);DB::table('units')->where('id',$this->f['version']->unit_id)->update(['name'=>'Satuan master baru']);
        $page=$this->page($this->owner,'?q='.urlencode('001.020%_A'));$page->assertSee('001.020%_A')->assertDontSee('NEW-MASTER')->assertDontSee('Satuan master baru');
        $rows=$this->values($this->export());$this->assertCount(2,$rows);$this->assertSame('Paket',$rows[1][8]);$this->assertSame('00001.02; 00002.03',$rows[1][9]);
    }

    public function test_revoked_membership_disabled_session_and_missing_report_grants_deny_or_empty_both_routes(): void
    {
        $this->page($this->examiner);$this->assertCount(2,$this->export());DB::table('assignment_members')->where('id',$this->scope['members']['examiner'])->update(['is_active'=>false]);
        $this->assertSame(0,$this->page($this->examiner)->viewData('rows')->total());$this->assertCount(1,$this->export());
        $role=DB::table('roles')->where('code','team_lead')->value('id');$permission=DB::table('permissions')->where('code','reports.assigned')->value('id');DB::table('permission_role')->where('role_id',$role)->where('permission_id',$permission)->delete();
        foreach([$this->lead,$this->actor('admin')] as $actor){$this->asActor($actor);$this->getJson('/rekap')->assertForbidden();$this->getJson('/rekap/excel')->assertForbidden();}
        $this->owner->is_active=false;$this->owner->save();$this->asActor($this->owner);$this->getJson('/rekap')->assertUnauthorized();$this->getJson('/rekap/excel')->assertUnauthorized();
    }

    public function test_report_action_cannot_inherit_an_unrelated_broader_read_permission(): void
    {
        $this->submission($this->other);$this->grantRole($this->owner,'bkad');$role=DB::table('roles')->where('code','bkad')->value('id');$permission=DB::table('permissions')->where('code','reports.all')->value('id');
        DB::table('permission_role')->where('role_id',$role)->where('permission_id',$permission)->delete();
        $this->assertSame(1,$this->page($this->owner)->viewData('rows')->total());$this->assertCount(2,$this->export());
    }

    public function test_empty_results_export_a_valid_header_only_workbook_and_bad_filters_recover_safely(): void
    {
        $page=$this->page($this->owner,'?q=kosong');$page->assertSee('Tidak ada item yang sesuai');$this->assertCount(1,$this->export('?q=kosong'));
        $this->asActor($this->owner);foreach(['q[]=bad','year=abc','year=999','type=OTHER','status=other','organization=-1','page=-1'] as $filter){$this->getJson('/rekap?'.$filter)->assertUnprocessable();$this->getJson('/rekap/excel?'.$filter)->assertUnprocessable();}
        $this->from('/rekap')->get('/rekap?q[]=bad')->assertRedirect('/rekap')->assertSessionHasErrors('q');$this->withCookie(config('session.cookie'),session()->getId())->get('/rekap')->assertOk()->assertSee('Periksa kembali isian Anda.');
    }

    public function test_assigned_report_grant_does_not_export_own_private_drafts_when_own_report_grant_is_revoked(): void
    {
        $this->grantRole($this->owner,'team_lead');DB::table('assignment_members')->where('id',$this->scope['members']['team_lead'])->update(['user_id'=>$this->owner->id]);
        $role=DB::table('roles')->where('code','skpd_operator')->value('id');$permission=DB::table('permissions')->where('code','reports.own')->value('id');
        DB::table('permission_role')->where('role_id',$role)->where('permission_id',$permission)->delete();
        $version=$this->f['version']->getAttributes();unset($version['id']);DB::table('proposal_item_versions')->insert(array_replace($version,['version_number'=>2,'description'=>'Draf privat milik sendiri','submitted_at'=>null]));
        $this->page($this->owner)->assertSee('Komputer simulasi 100%_A')->assertDontSee('Draf privat milik sendiri');
        $data=$this->values($this->export());$this->assertCount(2,$data);$this->assertSame('Komputer simulasi 100%_A',$data[1][6]);
        $this->assertSame(0,$this->page($this->owner,'?q=privat')->viewData('rows')->total());
    }
}
