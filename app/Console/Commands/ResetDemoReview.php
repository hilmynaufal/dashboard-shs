<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\{KkrDocument, ReviewAssignment, User};
use App\Support\{KkrExport, KkrWork};
use RuntimeException;

class ResetDemoReview extends Command
{
    protected $signature = 'demo:reset-review {--stages-only : Bagi antrean demo yang sudah diimpor}';
    protected $description = 'Ganti usulan lokal dengan item workbook yang telah ditugaskan untuk demo.';

    private array $references = [];
    private array $teams = [];
    private array $packages = [];
    private array $actors = [];
    private int $period;
    private int $system;
    private string $stamp;
    private string $digest;
    private const REASON = 'Simulasi demo lokal, dibuat otomatis oleh sistem.';

    public function handle(): int
    {
        $db = DB::connection();
        if (!app()->environment('local') || $db->getDriverName() !== 'mysql'
            || $db->getConfig('host') !== '127.0.0.1' || $db->getDatabaseName() !== 'shs_new') {
            throw new RuntimeException('Target harus MySQL shs_new di localhost pada lingkungan local.');
        }
        if ($this->option('stages-only')) {
            DB::transaction(fn () => $this->stageExistingQueue());
            return self::SUCCESS;
        }
        ini_set('memory_limit', '768M');
        $source = base_path('analysis/workbook.json');
        $this->digest = hash_file('sha256', $source);
        $sheets = array_column(json_decode(file_get_contents($source), true, flags: JSON_THROW_ON_ERROR), 'rows', 'name');
        if (empty($sheets['Data_SSH']) || empty($sheets['Data_SBU'])) {
            throw new RuntimeException('Lembar sumber belum lengkap.');
        }
        if (!(int) DB::selectOne("select get_lock('shs_new_demo_reset', 0) as acquired")->acquired) {
            throw new RuntimeException('Reset demo lain sedang berjalan.');
        }
        try {
            DB::transaction(function () use ($sheets) {
                $this->stamp = now()->toDateTimeString();
                foreach (['examiner','team_lead','technical_controller'] as $role) {
                    $id = DB::table('users as u')->join('role_user as ru','ru.user_id','=','u.id')
                        ->join('roles as r','r.id','=','ru.role_id')->where('r.code',$role)
                        ->where('u.is_active',true)->whereNotIn('u.id',$this->actors)
                        ->orderByRaw("case when u.email like '%@shs.example.test' then 0 else 1 end")->orderBy('u.id')->value('u.id');
                    if (!$id) {
                        throw new RuntimeException("Akun aktif untuk peran {$role} tidak ditemukan.");
                    }
                    $this->actors[$role] = $id;
                }
                $this->backupAndClear();
                $this->system = DB::table('users')->where('email','system.review-demo@shs.example.test')->value('id')
                    ?? $this->insert('users',['name'=>'Sistem Demo','email'=>'system.review-demo@shs.example.test',
                        'password'=>bcrypt(Str::random(64)),'is_active'=>false]);
                $period = ['name'=>'Simulasi reviu SHS/SBU','budget_year'=>2026];
                $this->period = DB::table('submission_periods')->where($period)->value('id')
                    ?? $this->insert('submission_periods',$period + ['opens_at'=>'2026-01-01','closes_at'=>'2026-12-31 23:59:59',
                        'status'=>'open','created_by'=>$this->system]);
                $count = 0;
                foreach (['Data_SSH'=>'SHS','Data_SBU'=>'SBU'] as $sheet=>$type) {
                    foreach ($sheets[$sheet] as $row) {
                        $cells = [];
                        foreach ($row['cells'] as $cell) {
                            $cells[rtrim($cell['ref'],'0123456789')] = trim((string) ($cell['v'] ?? ''));
                        }
                        if (!is_numeric($cells['E'] ?? '')) {
                            continue;
                        }
                        $owners = array_unique(array_filter(array_map('trim',explode(';',mb_strtoupper($cells['R'] ?? '')))));
                        if (count($owners)>1) {
                            $owners = array_diff($owners,['ASB']);
                        } elseif ($owners === ['ASB'] && ($cells['Q'] ?? '') === 'PUTR') {
                            $owners = ['PUTR'];
                        }
                        foreach ($owners ?: ['PENGUSUL KOSONG'] as $owner) {
                            $this->addItem($owner,$type,$sheet,(int)$row['r'],$cells);
                            $count++;
                        }
                    }
                }
                $verified = DB::table('assignment_items as ai')
                    ->join('proposal_items as i','i.id','=','ai.proposal_item_id')
                    ->join('verification_results as v','v.item_version_id','=','ai.initial_version_id')
                    ->join('assignment_members as m','m.id','=','ai.examiner_member_id')
                    ->join('import_rows as ir','ir.item_version_id','=','ai.initial_version_id')
                    ->where('i.status','assigned')->where('v.result','ready')->where('m.role','examiner')
                    ->where('m.user_id',$this->actors['examiner'])->count();
                if (!$count || $verified !== $count || DB::table('proposal_items')->count() !== $count) {
                    throw new RuntimeException('Rekonsiliasi gagal.');
                }
                $this->stageExistingQueue();
            });
            $this->info('Pemeriksa: '.DB::table('users')->where('id',$this->actors['examiner'])->value('email'));
        } finally {
            DB::selectOne("select release_lock('shs_new_demo_reset')");
        }
        return self::SUCCESS;
    }

    private function stageExistingQueue(): void
    {
        $period = DB::table('submission_periods')->where('name','Simulasi reviu SHS/SBU')->where('budget_year',2026)->sole();
        $system = DB::table('users')->where('email','system.review-demo@shs.example.test')->sole();
        $assignments = ReviewAssignment::where('submission_period_id',$period->id)->where('created_by',$system->id)
            ->where('number','like','DEMO-R-%')->orderBy('id')->lockForUpdate()->get();
        $this->stamp = now()->toDateTimeString();
        foreach (['PUTR','PERPRES 33'] as $owner) {
            $proposal = DB::table('proposals')->where('submission_period_id',$period->id)->where('created_by',$system->id)
                ->where('title','Usulan '.($owner==='PUTR'?'SHS':'SBU').' · '.$owner)->sole();
            $assignment = $assignments->firstWhere('object_organization_id',$proposal->organization_id);
            $number = 'KKR-DEMO-'.$proposal->type.'-'.$proposal->organization_id;
            if (KkrDocument::where('number',$number)->exists()) {
                continue;
            }
            $query = DB::table('assignment_items as a')->join('proposal_items as i','i.id','=','a.proposal_item_id')
                ->join('proposal_item_versions as v','v.id','=','a.initial_version_id')->where('a.review_assignment_id',$assignment->id)
                ->where('i.proposal_id',$proposal->id)->where('i.status','assigned')
                ->whereNotExists(fn ($q)=>$q->from('item_reviews as r')->whereColumn('r.assignment_item_id','a.id'))
                ->orderBy('a.id')->select('a.*','v.description','v.specification','v.proposed_price');
            $samples = (clone $query)->get()->unique('description')->take(3);
            if ($samples->count()!==3) {
                throw new RuntimeException("Sampel {$owner} belum cukup.");
            }
            $team = DB::table('assignment_members')->where('review_assignment_id',$assignment->id)->where('is_active',true)->pluck('user_id','role');
            $author = User::findOrFail($team['examiner']);
            $aspects = DB::table('review_aspects')->where('is_active',true)->get();
            $reviews = [];
            foreach ($samples as $sample) {
                $recommendation = "Lengkapi dasar harga untuk {$sample->description} sesuai spesifikasi yang diusulkan.";
                $review = $this->insert('item_reviews',['assignment_item_id'=>$sample->id,'proposal_item_id'=>$sample->proposal_item_id,
                    'item_version_id'=>$sample->initial_version_id,'reviewed_by'=>$author->id,'status'=>'completed',
                    'recommendation'=>$recommendation,'completed_at'=>$this->stamp]);
                foreach ($aspects as $aspect) {
                    $specified = $aspect->code==='PROTO_SPEC_UNIT';
                    $this->insert('review_results',['item_review_id'=>$review,'review_aspect_id'=>$aspect->id,
                        'result'=>$specified?'compliant':'insufficient_evidence',
                        'criterion_snapshot'=>json_encode(['name'=>$aspect->name,'code'=>$aspect->code,'is_required'=>(bool)$aspect->is_required],JSON_THROW_ON_ERROR),
                        'reason'=>$specified?'Spesifikasi dan satuan tercantum pada baris usulan.':"Simulasi: bukti pendukung aspek {$aspect->name} belum tersedia.",
                        'recommendation'=>$specified?null:$recommendation]);
                }
                DB::table('proposal_items')->where('id',$sample->proposal_item_id)->update(['status'=>'reviewed','lock_version'=>DB::raw('lock_version + 1'),'updated_at'=>$this->stamp]);
                $reviews[]=$review;
            }
            $title="KKR simulasi {$proposal->type} · {$owner}";
            $doc = new KkrDocument;
            $doc->review_assignment_id=$assignment->id;
            $doc->prepared_by=$author->id;
            $doc->number=$number;
            $doc->title=$title;
            $doc->status='draft';
            $doc->save();
            $version=KkrWork::append($author,$doc,$assignment,[
                'reviews'=>$reviews,'title'=>$title,'purpose'=>"Menilai kelengkapan tiga usulan {$proposal->type} dari {$owner} dalam simulasi reviu.",
                'work_steps'=>"1. Cocokkan kode, spesifikasi, satuan, dan harga dengan baris sumber.\n2. Periksa ketersediaan dasar harga dan data pembanding.\n3. Rangkum hasil per butir.",
                'conclusion'=>"Ketiga butir sampel {$owner} mencantumkan spesifikasi dan satuan. Bukti pembanding belum tersedia dalam simulasi sehingga kewajaran harga ketiganya belum dapat disimpulkan.",
                'recommendation'=>'Lampirkan dasar harga dan pembanding dengan spesifikasi serta satuan yang sepadan untuk setiap butir.']);
            foreach (['team_lead','technical_controller'] as $level) {
                $this->insert('kkr_approvals',['kkr_version_id'=>$version->id,'decided_by'=>$team[$level],'level'=>$level,
                    'decision'=>'approved','notes'=>self::REASON,'decided_at'=>$this->stamp]);
            }
            $version->submitted_at=$this->stamp;
            $version->finalized_at=$this->stamp;
            $version->save();
            $doc->status='final';
            $doc->save();
            KkrExport::presentation($version,$author);
            $result=KkrExport::presentation($version,$author);
            if (count($result['model']['rows'])!==3 || !str_starts_with($result['bytes'],'PK')) {
                throw new RuntimeException('Verifikasi arsip Word gagal.');
            }
            $this->line("{$number} | /kkr/{$doc->id}/versi/{$version->id}/preview");
        }
        foreach ($assignments as $assignment) {
            $items=DB::table('proposal_items as i')->join('proposals as p','p.id','=','i.proposal_id')
                ->where('p.submission_period_id',$period->id)->where('p.organization_id',$assignment->object_organization_id)
                ->where('p.created_by',$system->id);
            $target=intdiv((clone $items)->count(),4)-(clone $items)->where('i.status','ready')->count();
            if ($target<=0) {
                continue;
            }
            $ids=(clone $items)->where('i.status','assigned')->join('assignment_items as a','a.proposal_item_id','=','i.id')
                ->where('a.review_assignment_id',$assignment->id)
                ->whereNotExists(fn ($q)=>$q->from('item_reviews as r')->whereColumn('r.assignment_item_id','a.id'))
                ->whereNotExists(fn ($q)=>$q->from('assignment_transfers as t')->whereColumn('t.assignment_item_id','a.id'))
                ->orderByDesc('a.id')->limit($target)->pluck('i.id');
            DB::table('assignment_items')->where('review_assignment_id',$assignment->id)->whereIntegerInRaw('proposal_item_id',$ids)->delete();
            DB::table('proposal_items')->whereIntegerInRaw('id',$ids)->update(['status'=>'ready','lock_version'=>DB::raw('lock_version + 1'),'updated_at'=>$this->stamp]);
        }
        $counts=DB::table('proposal_items as i')->join('proposals as p','p.id','=','i.proposal_id')
            ->where('p.submission_period_id',$period->id)->where('p.created_by',$system->id)->groupBy('i.status')
            ->selectRaw('i.status,count(*) as total')->pluck('total','status');
        $this->info($counts->toJson());
    }

    private function backupAndClear(): void
    {
        $tables = ['kkr_exports','kkr_approvals','kkr_entries','kkr_versions','kkr_documents','note_response_decisions',
            'note_responses','review_notes','review_results','item_reviews','assignment_transfers','assignment_items',
            'assignment_members','review_assignments','verification_results','import_rows','import_batches',
            'item_version_accounts','item_version_evidence','price_sources','proposal_item_versions','proposal_items','proposals'];
        $folder = storage_path('app/private/demo-backups');
        if (!is_dir($folder) && !mkdir($folder,0700,true)) {
            throw new RuntimeException('Direktori cadangan tidak dapat dibuat.');
        }
        $file = $folder.'/review-'.Str::uuid().'.jsonl';
        $stream = fopen($file,'xb');
        try {
            foreach ($tables as $table) {
                foreach (DB::table($table)->lockForUpdate()->cursor() as $row) {
                    $line = json_encode([$table,$row],JSON_THROW_ON_ERROR)."\n";
                    if (fwrite($stream,$line)!==strlen($line)) {
                        throw new RuntimeException('Cadangan gagal ditulis.');
                    }
                }
            }
            if (!fflush($stream)) {
                throw new RuntimeException('Cadangan gagal disimpan.');
            }
        } finally {
            fclose($stream);
        }
        $this->line("Cadangan: {$file}");
        foreach ($tables as $table) {
            if ($table==='kkr_versions') {
                foreach (DB::table($table)->orderByDesc('id')->pluck('id') as $id) {
                    DB::table($table)->where('id',$id)->delete();
                }
            } else {
                DB::table($table)->delete();
            }
        }
    }

    private function insert(string $table,array $values): int
    {
        return DB::table($table)->insertGetId($values+['created_at'=>$this->stamp,'updated_at'=>$this->stamp]);
    }

    private function reference(string $table,string $code,string $name): array
    {
        if (!isset($this->references[$table][$code])) {
            $record = DB::table($table)->where('code',$code)->first(['id','code','name']);
            if (!$record && $table==='units') {
                $record = DB::table($table)->where('name',$name)->where('is_active',true)->first(['id','code','name']);
            }
            $this->references[$table][$code] = $record ? (array)$record : [
                'id'=>$this->insert($table,['code'=>$code,'name'=>$name]),'code'=>$code,'name'=>$name];
        }
        return $this->references[$table][$code];
    }

    private function addItem(string $owner,string $type,string $sheet,int $row,array $c): void
    {
        if (!isset($this->teams[$owner])) {
            $name = $owner==='PENGUSUL KOSONG' ? 'Pengusul belum tercantum (demo)' : $owner;
            $operator = DB::table('users')->where('email','operator.'.Str::slug($owner).'.demo@shs.example.test')->value('organization_id');
            $org = $operator ?: $this->reference('organizations',$owner,$name)['id'];
            $assignment = $this->insert('review_assignments',['number'=>'DEMO-R-'.$org,'title'=>'Reviu demo '.$name,
                'submission_period_id'=>$this->period,'object_organization_id'=>$org,'created_by'=>$this->system,
                'task_basis'=>self::REASON,'status'=>'active']);
            foreach ($this->actors as $role=>$actor) {
                $member = $this->insert('assignment_members',['review_assignment_id'=>$assignment,'user_id'=>$actor,'role'=>$role]);
                if ($role==='examiner') {
                    $examiner = $member;
                }
            }
            $this->teams[$owner] = [$org,$assignment,$examiner];
        }
        [$org,$assignment,$member] = $this->teams[$owner];
        $key = $type.'-'.$owner;
        if (!isset($this->packages[$key])) {
            $proposal = $this->insert('proposals',['number'=>"DEMO-{$type}-{$org}",'organization_id'=>$org,
                'submission_period_id'=>$this->period,'type'=>$type,'title'=>"Usulan {$type} · {$owner}",
                'status'=>'review','created_by'=>$this->system,'submitted_at'=>$this->stamp]);
            $batch = $this->insert('import_batches',['proposal_id'=>$proposal,'created_by'=>$this->system,
                'file_sha256'=>$this->digest,'sheet_name'=>$sheet,'status'=>'completed']);
            $this->packages[$key] = [$proposal,$batch];
        }
        [$proposal,$batch] = $this->packages[$key];
        $classification = $this->reference('item_classifications',$c['A'],$c['B']);
        $unit = $this->reference('units','U-'.substr(sha1(mb_strtolower($c['D'])),0,16),$c['D']);
        $accounts = [];
        foreach (range('F','O') as $column) {
            if (!empty($c[$column])) {
                $account = $this->reference('account_codes',$c[$column],$c[$column]);
                $accounts[$account['id']] = $account;
            }
        }
        $accounts = array_values($accounts);
        $item = $this->insert('proposal_items',['proposal_id'=>$proposal,'public_id'=>(string)Str::uuid(),
            'status'=>'assigned','lock_version'=>2]);
        $tkdn = str_replace(',','.',rtrim($c['Q'] ?? '','%'));
        $version = $this->insert('proposal_item_versions',['proposal_item_id'=>$item,'version_number'=>1,
            'item_classification_id'=>$classification['id'],'unit_id'=>$unit['id'],'description'=>$c['B'],
            'specification'=>$c['C'],'proposed_price'=>$c['E'],'tkdn_percent'=>is_numeric($tkdn) && $tkdn>=0 && $tkdn<=100 ? $tkdn : null,
            'origin'=>match($c['S'] ?? '') {'Data lama'=>'existing','Data baru'=>'new',default=>null},
            'reference_snapshot'=>json_encode(compact('classification','unit','accounts'),JSON_THROW_ON_ERROR),
            'created_by'=>$this->system,'change_reason'=>self::REASON,'submitted_at'=>$this->stamp]);
        foreach ($accounts as $account) {
            DB::table('item_version_accounts')->insert(['item_version_id'=>$version,'account_code_id'=>$account['id'],
                'code_snapshot'=>$account['code'],'name_snapshot'=>$account['name']]);
        }
        $this->insert('import_rows',['import_batch_id'=>$batch,'row_number'=>$row,'raw_values'=>json_encode($c,JSON_THROW_ON_ERROR),
            'item_version_id'=>$version]);
        $this->insert('verification_results',['item_version_id'=>$version,'verified_by'=>$this->system,
            'result'=>'ready','reason'=>self::REASON,'verified_at'=>$this->stamp]);
        $this->insert('assignment_items',['review_assignment_id'=>$assignment,'proposal_item_id'=>$item,
            'initial_version_id'=>$version,'examiner_member_id'=>$member]);
    }
}
