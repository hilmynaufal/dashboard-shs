<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ZiInstrument;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ZiWorkflowTest extends TestCase
{
    private function actor(string $role, ?int $org = null): User
    {
        $actor = User::factory()->create(['organization_id'=>$org]);
        DB::table('role_user')->insert(['user_id'=>$actor->id,'role_id'=>DB::table('roles')->where('code',$role)->value('id')]);
        return $actor;
    }

    private function useActor(User $actor): void
    {
        $this->flushSession();
        $this->actingAs($actor);
    }

    public function test_area_can_be_submitted_corrected_reviewed_and_frozen(): void
    {
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        foreach (['0001_01_01_000000_create_users_table','2026_11_01_000100_create_reference_and_access_tables','2026_11_13_000900_create_zi_assessments','2026_11_13_001100_add_zi_entry_evidence_url'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
        $org = DB::table('organizations')->insertGetId(['code'=>'ZI','name'=>'Unit latihan']);
        $admin = $this->actor('admin');
        $unit = $this->actor('skpd_operator',$org);
        $reviewer = $this->actor('examiner');
        $this->useActor($admin);
        $this->get('/penilaian-zi')->assertOk();
        $this->post('/penilaian-zi',['organization_id'=>$org,'reviewer_id'=>$reviewer->id,'year'=>2027,'target'=>'WBBM'])->assertRedirect();
        $id = DB::table('zi_assessments')->sole()->id;
        $url = '/penilaian-zi/'.$id;
        $entries = fn($item)=>DB::table('zi_entries')->where('assessment_id',$id)->where('item',$item)->orderByDesc('id')->first();
        $revision = fn()=>DB::table('zi_assessments')->where('id',$id)->value('revision');
        $this->useActor($unit);
        $items = ZiInstrument::items(ZiInstrument::VERSION);
        $answers = [];
        foreach ($items as $item=>$q) {
            $answers[$item] = isset($q['fields']) ? ['previous'=>4,'current'=>1] : ($q['type'] === '%' ? ['numerator'=>6,'denominator'=>4] : ['choice'=>explode('/',$q['type'])[0]]);
            $this->post($url.'/butir/'.$item,$answers[$item]+['action'=>'draft','revision'=>0,
                'file'=>UploadedFile::fake()->createWithContent('latihan.pdf',"%PDF-1.4\n%%EOF")])->assertSessionHasNoErrors()->assertRedirect();
        }
        $draft = $entries(6);
        $this->get($url.'/butir/6')->assertOk();
        $this->useActor($reviewer);
        $this->get($url.'/bukti/'.$draft->id)->assertNotFound();
        $this->useActor($unit);
        $this->post($url.'/ajukan',['revision'=>$revision()])->assertSessionHasNoErrors();
        $this->useActor($this->actor('examiner'));
        $this->get($url)->assertForbidden();
        $this->useActor($reviewer);
        $this->get($url)->assertOk();
        $this->get($url.'/butir/10')->assertOk();
        $this->post($url.'/butir/10',['action'=>'returned','revision'=>$entries(10)->id,'note'=>'Lengkapi lampiran.'])->assertSessionHasNoErrors();
        $this->post($url.'/final',['revision'=>$revision()])->assertSessionHasErrors('item');
        $this->useActor($unit);
        $this->post($url.'/butir/10',$answers[10]+['action'=>'revised','revision'=>$entries(10)->id,'note'=>'Lampiran tambahan.',
            'file'=>UploadedFile::fake()->createWithContent('revisi.pdf',"%PDF-1.4\n%%EOF")])->assertSessionHasNoErrors();
        $this->useActor($reviewer);
        foreach ($items as $item=>$q) {
            $response = $item===10 ? ['choice'=>'B','note'=>'Sebagian target relevan.'] : $answers[$item];
            $this->post($url.'/butir/'.$item,$response+['action'=>'rated','revision'=>$entries($item)->id])->assertSessionHasNoErrors();
        }
        $this->post($url.'/final',['revision'=>$revision()])->assertSessionHasNoErrors();
        $final = DB::table('zi_assessments')->where('id',$id)->first();
        $this->assertSame('final',$final->state);
        $result = json_decode($final->final_result,true);
        $this->assertEqualsWithDelta(7.80555556,$result['areas'][0]['total'],.000001);
        $this->assertEqualsWithDelta(7.0,$result['areas'][1]['total'],.000001);
        $this->assertEqualsWithDelta(0.75,$result['scores'][151],.000001);
        $this->assertEqualsWithDelta(9.5,$result['areas'][2]['total'],.000001);
        $this->assertEqualsWithDelta(24.30555556,$result['total'],.000001);
        $this->assertTrue($result['met']);
        $this->assertSame(1,$result['scores'][125]);
        $this->get($url)->assertOk()->assertSee('Rekap final area');
        $this->post($url.'/butir/10',['action'=>'returned','revision'=>$entries(10)->id,'note'=>'Ulang'])->assertForbidden();
    }

    public function test_evidence_can_be_a_private_upload_or_a_google_drive_link(): void
    {
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        foreach (['0001_01_01_000000_create_users_table','2026_11_01_000100_create_reference_and_access_tables','2026_11_13_000900_create_zi_assessments','2026_11_13_001100_add_zi_entry_evidence_url'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
        $org = DB::table('organizations')->insertGetId(['code'=>'ZI','name'=>'Unit latihan']);
        $unit = $this->actor('skpd_operator',$org);
        $reviewer = $this->actor('examiner');
        $this->useActor($this->actor('admin'));
        $this->post('/penilaian-zi',['organization_id'=>$org,'reviewer_id'=>$reviewer->id,'year'=>2027,'target'=>'WBBM'])->assertRedirect();
        $id = DB::table('zi_assessments')->sole()->id;
        $url = '/penilaian-zi/'.$id;
        $latest = fn($item)=>DB::table('zi_entries')->where('assessment_id',$id)->where('item',$item)->orderByDesc('id')->first();
        $drive = 'https://drive.google.com/file/d/1AbC/view?usp=sharing';
        $this->useActor($unit);
        $items = ZiInstrument::items(ZiInstrument::VERSION);
        $answer = fn($q)=>isset($q['fields']) ? ['previous'=>4,'current'=>1] : ($q['type'] === '%' ? ['numerator'=>6,'denominator'=>4] : ['choice'=>explode('/',$q['type'])[0]]);
        $first = array_key_first($items);
        $payload = fn($item,array $extra)=>$answer($items[$item])+['action'=>'draft','revision'=>$latest($item)?->id ?? 0]+$extra;

        // Tautan di luar Google Drive, tanpa HTTPS, memuat kredensial, atau kosong ditolak.
        foreach (['http://drive.google.com/file/d/1','https://example.com/file/d/1','https://drive.google.com.evil.test/file/d/1','https://user@drive.google.com/file/d/1','https://drive.google.com/','javascript:alert(1)','https://drive.google.com/file/d/1 2',''] as $bad) {
            $this->post($url.'/butir/'.$first,$payload($first,['evidence_mode'=>'link','evidence_url'=>$bad]))->assertSessionHasErrors('evidence_url');
        }
        $this->assertDatabaseCount('zi_entries',0);

        // Mode tautan menyimpan tautan tanpa berkas; berkas yang ikut terkirim tidak disimpan.
        $this->post($url.'/butir/'.$first,$payload($first,['evidence_mode'=>'link','evidence_url'=>$drive]))->assertSessionHasNoErrors();
        $this->assertSame($drive,$latest($first)->evidence_url);
        $this->assertNull($latest($first)->evidence);
        $this->get($url.'/butir/'.$first)->assertOk()->assertSee('Tautan Google Drive')->assertSee($drive,false)->assertSee('rel="noopener noreferrer"',false);
        $this->get($url.'/bukti/'.$latest($first)->id)->assertNotFound();

        // Simpan ulang tanpa memilih bukti baru mempertahankan tautan sebelumnya.
        $this->post($url.'/butir/'.$first,$payload($first,[]))->assertSessionHasNoErrors();
        $this->assertSame($drive,$latest($first)->evidence_url);

        // Beralih ke unggah berkas mengganti tautan dengan berkas privat.
        $this->post($url.'/butir/'.$first,$payload($first,['evidence_mode'=>'file','file'=>UploadedFile::fake()->createWithContent('bukti.pdf',"%PDF-1.4\n%%EOF")]))->assertSessionHasNoErrors();
        $this->assertNull($latest($first)->evidence_url);
        $this->assertNotNull($latest($first)->evidence);
        $this->get($url.'/bukti/'.$latest($first)->id)->assertOk();

        // Butir lain memakai tautan; pengajuan menerima campuran berkas dan tautan.
        foreach ($items as $item=>$q) {
            if ($item !== $first) {
                $this->post($url.'/butir/'.$item,$payload($item,['evidence_mode'=>'link','evidence_url'=>$drive]))->assertSessionHasNoErrors();
            }
        }
        $this->post($url.'/ajukan',['revision'=>DB::table('zi_assessments')->where('id',$id)->value('revision')])->assertSessionHasNoErrors();
        $this->assertSame('review',DB::table('zi_assessments')->where('id',$id)->value('state'));
        $last = array_key_last($items);
        $this->assertSame('submitted',$latest($last)->action);
        $this->assertSame($drive,$latest($last)->evidence_url);

        // TPI melihat tautan dan tidak dapat menambahkan bukti sendiri.
        $this->useActor($reviewer);
        $this->get($url.'/butir/'.$last)->assertOk()->assertSee('Buka tautan bukti (Google Drive)')->assertSee($drive,false);
        $this->post($url.'/butir/'.$last,$answer($items[$last])+['action'=>'rated','revision'=>$latest($last)->id,'evidence_mode'=>'link','evidence_url'=>$drive])->assertSessionHasNoErrors();
        $this->assertNull($latest($last)->evidence_url);
    }

    public function test_legacy_version_and_area_thresholds_are_computed_from_rules(): void
    {
        $rated = fn(array $items, bool $best)=>collect($items)->map(fn($q,$id)=>(object)['item'=>$id,'action'=>'rated',
            'answer'=>json_encode(isset($q['fields'])?['previous'=>1,'current'=>0]:($q['type']==='%'?['numerator'=>1,'denominator'=>1]:['choice'=>$best?explode('/',$q['type'])[0]:last(explode('/',$q['type']))]))]);
        $v1 = ZiInstrument::items('zi-manajemen-v1');
        $result = ZiInstrument::result($v1,$rated($v1,true),'WBBM','zi-manajemen-v1');
        $this->assertEqualsWithDelta(8.0,$result['total'],1e-9);
        $this->assertEqualsWithDelta(6.0,$result['minimum'],1e-9);
        $v2 = ZiInstrument::items('zi-v2');
        $this->assertCount(52,$v2);
        $partial = $rated($v2,true)->except(144);
        $result = ZiInstrument::result($v2,$partial,'WBK');
        $this->assertNull($result['total']);
        $this->assertTrue($result['areas'][0]['complete']);
        $this->assertFalse($result['areas'][1]['complete']);
        $result = ZiInstrument::result($v2,$rated($v2,false),'WBK');
        $this->assertEqualsWithDelta(4.8+4.2+6.0,$result['minimum'],1e-9);
        $this->assertFalse($result['met']);
        $this->assertEqualsWithDelta(4.2,$result['areas'][1]['minimum'],1e-9);
        $this->assertSame(1.0,ZiInstrument::percent(['previous'=>0,'current'=>0]));
        $this->assertSame(0.0,ZiInstrument::percent(['previous'=>0,'current'=>2]));
        $this->assertSame(0.0,ZiInstrument::percent(['previous'=>2,'current'=>5]));
        $this->assertEqualsWithDelta(0.5,ZiInstrument::percent(['previous'=>4,'current'=>2]),1e-9);
    }

    public function test_published_instrument_is_read_from_database_and_drafts_are_ignored(): void
    {
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_11_13_001000_create_zi_instruments.php'))->up();
        $this->assertSame(['zi-manajemen-v1','zi-v2'],DB::table('zi_instruments')->orderBy('id')->pluck('version')->all());
        $this->assertCount(52,ZiInstrument::items('zi-v2'));
        $rules = ZiInstrument::rules('zi-v2');
        $rules['thresholds']['WBK'] = 0.5;
        DB::table('zi_instruments')->insert(['version'=>'zi-uji','status'=>'draft','items'=>json_encode(ZiInstrument::items('zi-v2')),
            'rules'=>json_encode($rules),'created_at'=>now(),'updated_at'=>now()]);
        try {
            ZiInstrument::rules('zi-uji');
            $this->fail('Draf tidak boleh terbaca.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409,$e->getStatusCode());
        }
        DB::table('zi_instruments')->where('version','zi-uji')->update(['status'=>'published']);
        $items = ZiInstrument::items('zi-uji');
        $result = ZiInstrument::result($items,collect(),'WBK','zi-uji');
        $this->assertEqualsWithDelta(.5*25,$result['minimum'],1e-9);
    }

    public function test_admin_drafts_validates_publishes_and_locks_a_template(): void
    {
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        foreach (['0001_01_01_000000_create_users_table','2026_11_01_000100_create_reference_and_access_tables','2026_11_13_000900_create_zi_assessments','2026_11_13_001000_create_zi_instruments'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        $this->seed(AccessControlSeeder::class);
        $org = DB::table('organizations')->insertGetId(['code'=>'ZI','name'=>'Unit latihan']);
        $admin = $this->actor('admin');
        $reviewer = $this->actor('examiner');
        $this->useActor($reviewer);
        $this->get('/penilaian-zi/template')->assertForbidden();
        $this->post('/penilaian-zi/template',['source'=>'zi-v2','version'=>'zi-uji'])->assertForbidden();
        $this->useActor($admin);
        $this->get('/penilaian-zi/template')->assertOk()->assertSee('zi-v2');
        $this->post('/penilaian-zi/template',['source'=>'zi-v2','version'=>'ZI Uji'])->assertSessionHasErrors('version');
        $this->post('/penilaian-zi/template',['source'=>'zi-v2','version'=>'zi-uji'])->assertSessionHasNoErrors();
        $id = DB::table('zi_instruments')->where('version','zi-uji')->value('id');
        $url = '/penilaian-zi/template/'.$id;
        $rev = fn()=>DB::table('zi_instruments')->where('id',$id)->value('revision');
        $change = fn(array $data)=>$this->post($url.'/ubah',$data+['revision'=>$rev()]);
        $this->get($url)->assertOk()->assertSee('Siap terbit');

        // Bobot kelompok yang tidak lagi sama dengan bobot area menahan penerbitan.
        $change(['op'=>'group.save','area'=>'penataan-tatalaksana','group'=>'sop','name'=>'Pemenuhan · SOP','weight'=>'1,5'])->assertSessionHasNoErrors();
        $this->get($url)->assertOk()->assertSee('Belum siap terbit')->assertSee('tidak sama dengan bobot area');
        $this->post($url.'/terbit',['revision'=>$rev()])->assertSessionHasErrors('publish');
        $change(['op'=>'area.save','area'=>'penataan-tatalaksana','name'=>'Penataan Tatalaksana','max'=>'7,5'])->assertSessionHasNoErrors();
        $this->post($url.'/ubah',['op'=>'thresholds','wbk'=>'65','wbbm'=>'80','revision'=>$rev()-1])->assertSessionHasErrors('revision');
        $change(['op'=>'thresholds','wbk'=>'65','wbbm'=>'80'])->assertSessionHasNoErrors();

        // Area, kelompok, dan butir baru.
        $change(['op'=>'area.save','name'=>'Penguatan Akuntabilitas','max'=>'2'])->assertSessionHasNoErrors();
        $change(['op'=>'group.save','area'=>'penguatan-akuntabilitas','name'=>'Pemenuhan · Keterlibatan Pimpinan','weight'=>'2'])->assertSessionHasNoErrors();
        $this->post($url.'/terbit',['revision'=>$rev()])->assertSessionHasErrors('publish');
        $this->get($url.'/butir/0?kelompok=penguatan-akuntabilitas/pemenuhan-keterlibatan-pimpinan')->assertOk();
        $item = ['op'=>'item.save','id'=>61,'placement'=>'penguatan-akuntabilitas/pemenuhan-keterlibatan-pimpinan','title'=>'Pimpinan terlibat menyusun perencanaan','type'=>'A/B/C'];
        $change($item+['rubric'=>"a. Seluruhnya\nb. Sebagian"])->assertSessionHasNoErrors();
        $this->get($url)->assertSee('Butir 61: kriteria harus berisi 3 baris');
        $change($item+['original'=>61,'rubric'=>"a. Seluruhnya\nb. Sebagian\nc. Tidak"])->assertSessionHasNoErrors();
        $change($item+['rubric'=>'x'])->assertSessionHasErrors('id');
        $this->get($url.'/butir/61')->assertOk()->assertSee('Pimpinan terlibat menyusun perencanaan');
        $change(['op'=>'item.save','original'=>151,'id'=>151,'placement'=>'penataan-sdm/pelanggaran-disiplin','title'=>'Penurunan pelanggaran','type'=>'%','rubric'=>'Penurunan dari tahun lalu.','formula'=>'reduction','label_a'=>'Tahun lalu','label_b'=>'Tahun ini'])->assertSessionHasNoErrors();
        $stored = json_decode(DB::table('zi_instruments')->where('id',$id)->value('items'),true);
        $this->assertSame(['Tahun lalu','Tahun ini','Jumlah pelanggaran yang telah diberikan sanksi/hukuman'],array_column($stored[151]['fields'],'label'));
        $this->assertSame('Pemenuhan · Keterlibatan Pimpinan',$stored[61]['group']);

        // Draf belum dapat dipakai penilaian; setelah terbit terkunci dan dapat dipilih.
        $open = ['organization_id'=>$org,'reviewer_id'=>$reviewer->id,'year'=>2027,'target'=>'WBK','instrument'=>'zi-uji'];
        $this->post('/penilaian-zi',$open)->assertSessionHasErrors('instrument');
        $this->post($url.'/terbit',['revision'=>$rev()])->assertSessionHasNoErrors();
        $this->assertSame('published',DB::table('zi_instruments')->where('id',$id)->value('status'));
        $this->post($url.'/ubah',['op'=>'thresholds','wbk'=>'10','wbbm'=>'10','revision'=>$rev()])->assertStatus(409);
        $this->post($url.'/hapus',['revision'=>$rev()])->assertStatus(409);
        $this->get($url.'/butir/61')->assertStatus(409);
        $this->get($url)->assertOk()->assertSee('Versi terkunci');
        $this->post('/penilaian-zi',$open)->assertSessionHasNoErrors();
        $this->assertSame('zi-uji',DB::table('zi_assessments')->sole()->instrument);
        $result = ZiInstrument::result(ZiInstrument::items('zi-uji'),collect(),'WBK','zi-uji');
        $this->assertCount(4,$result['areas']);
        $this->assertEqualsWithDelta(.65*7.5,$result['areas'][1]['minimum'],1e-9);
        $this->assertSame(53,count(ZiInstrument::items('zi-uji')));
        $this->assertGreaterThanOrEqual(9,DB::table('zi_instrument_events')->where('instrument_id',$id)->count());
        $this->assertSame(52,count(ZiInstrument::items('zi-v2')));
    }
}
