<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EvidenceLink;
use App\Support\ZiInstrument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;

class ZiController extends Controller
{
    private function roles(Request $r, object $a): array
    {
        $codes = $r->user()->permissionCodes();
        $assigned = (int)$a->reviewer_id === (int)$r->user()->id;
        $own = (int)$a->organization_id === (int)$r->user()->organization_id;
        return [
            'manage'=>in_array('zi.manage',$codes,true),
            'write'=>$own && in_array('zi.write',$codes,true),
            'submit'=>$own && in_array('zi.submit',$codes,true),
            'review'=>$assigned && in_array('zi.review',$codes,true),
            'return'=>$assigned && in_array('zi.return',$codes,true),
            'finalize'=>$assigned && in_array('zi.finalize',$codes,true),
        ];
    }

    private function assessment(int $id, bool $lock = false): object
    {
        $q = DB::table('zi_assessments')->where('id',$id);
        return ($lock ? $q->lockForUpdate() : $q)->first() ?? abort(404);
    }

    private function entries(int $id)
    {
        return DB::table('zi_entries')->where('assessment_id',$id);
    }

    private function visibleEntries(object $a, array $access)
    {
        abort_unless($access['write'] || $access['submit'] || $access['review'] || $access['return'] || $access['finalize'],403);
        $q = $this->entries($a->id);
        if (!$access['write'] && !$access['submit']) {
            $q->where('action','!=','draft');
        }
        return $q;
    }

    public function index(Request $r)
    {
        $codes = $r->user()->permissionCodes();
        $manage = in_array('zi.manage',$codes,true);
        $template = in_array('zi.template',$codes,true);
        $versions = $manage ? ZiInstrument::published() : [];
        $unit = array_intersect($codes,['zi.write','zi.submit']);
        $tpi = array_intersect($codes,['zi.review','zi.return','zi.finalize']);
        abort_unless($manage || $template || $unit || $tpi,403);
        $q = DB::table('zi_assessments as a')->join('organizations as o','o.id','=','a.organization_id')
            ->join('users as u','u.id','=','a.reviewer_id')->select('a.*','o.name as unit','u.name as reviewer');
        if (!$manage) {
            $q->where(function ($scope) use ($unit,$tpi,$r) {
                $scope->whereRaw('1 = 0');
                if ($unit) $scope->orWhere('a.organization_id',$r->user()->organization_id);
                if ($tpi) $scope->orWhere('a.reviewer_id',$r->user()->id);
            });
        }
        $list = $q->orderByDesc('a.id')->paginate(20);
        $units = $manage ? DB::table('organizations')->where('is_active',true)->orderBy('name')->get(['id','name']) : [];
        $reviewers = $manage ? User::where('is_active',true)->whereIn('id', DB::table('role_user as ru')
            ->join('permission_role as pr','pr.role_id','=','ru.role_id')->join('permissions as p','p.id','=','pr.permission_id')
            ->where('p.code','zi.review')->select('ru.user_id'))->orderBy('name')->get()->filter(fn($u)=>$u->isEnabled()) : [];
        return view('zi.index',compact('list','manage','units','reviewers','unit','tpi','template','versions'));
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->hasPermission('zi.manage'),403);
        $data = $r->validate(['organization_id'=>['required','integer',Rule::exists('organizations','id')->where('is_active',true)],
            'reviewer_id'=>'required|integer','year'=>'required|integer|between:2000,2199','target'=>['required',Rule::in(['WBK','WBBM'])],
            'instrument'=>['nullable',Rule::in($versions = ZiInstrument::published())]]);
        $data['instrument'] ??= end($versions) ?: ZiInstrument::VERSION;
        $reviewer = User::find($data['reviewer_id']);
        if (!$reviewer?->hasPermission('zi.review')) {
            throw ValidationException::withMessages(['reviewer_id'=>'Pilih TPI aktif dengan izin penilaian.']);
        }
        try {
            DB::table('zi_assessments')->insert($data + ['created_by'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['year'=>'Penilaian untuk unit, tahun, dan target ini sudah ada.']);
        }
        return to_route('zi.index')->with('status','Penilaian dibuka.');
    }

    public function show(Request $r, int $assessment)
    {
        $a = $this->assessment($assessment);
        $access = $this->roles($r,$a);
        $latest = $this->visibleEntries($a,$access)->orderBy('id')->get()->keyBy('item');
        $items = ZiInstrument::items($a->instrument);
        $result = $a->final_result ? json_decode($a->final_result,true) : ZiInstrument::result($items,$latest,$a->target,$a->instrument);
        $unit = DB::table('organizations')->where('id',$a->organization_id)->value('name');
        return view('zi.show',compact('a','access','items','latest','result','unit'));
    }

    public function item(Request $r, int $assessment, int $item)
    {
        $a = $this->assessment($assessment);
        $access = $this->roles($r,$a);
        $history = $this->visibleEntries($a,$access)->where('item',$item)->join('users','users.id','=','zi_entries.actor_id')
            ->orderByDesc('zi_entries.id')->get(['zi_entries.*','users.name as actor']);
        $question = ZiInstrument::items($a->instrument)[$item] ?? abort(404);
        $last = $history->first();
        $unitEntry = $history->first(fn($e)=>in_array($e->action,['draft','submitted','revised'],true));
        return view('zi.item',compact('a','access','question','item','history','last','unitEntry'));
    }

    private function current(Request $r, int $version): void
    {
        $r->validate(['revision'=>'required|integer|min:0']);
        if ((int)$r->revision !== $version) {
            throw ValidationException::withMessages(['revision'=>'Data telah berubah. Muat ulang dan periksa versi terbaru.']);
        }
    }

    private function answer(Request $r, array $question): array
    {
        if (isset($question['fields'])) {
            $rules = [];
            foreach ($question['fields'] as $field) {
                $rules[$field['name']] = [$field['required'] ? 'required' : 'nullable','integer','between:0,1000000000'];
            }
            return array_map('intval', array_filter($r->validate($rules), fn($v)=>$v !== null));
        }
        if ($question['type'] === '%') {
            $data = $r->validate(['numerator'=>'required|integer|between:0,1000000000','denominator'=>'required|integer|between:1,1000000000']);
            return array_map('intval',$data);
        }
        return $r->validate(['choice'=>['required','string',Rule::in(explode('/',$question['type']))]]);
    }

    public function save(Request $r, int $assessment, int $item)
    {
        $uploaded = null;
        try {
            DB::transaction(function () use ($r,$assessment,$item,&$uploaded) {
                $a = $this->assessment($assessment,true);
                $access = $this->roles($r,$a);
                $question = ZiInstrument::items($a->instrument)[$item] ?? abort(404);
                $last = $this->entries($assessment)->where('item',$item)->orderByDesc('id')->first();
                $action = $r->validate(['action'=>['required',Rule::in(['draft','revised','returned','rated'])]])['action'];
                $unit = in_array($action,['draft','revised'],true);
                $allowed = match ($action) {
                    'draft'=>$access['write'] && $a->state === 'draft',
                    'revised'=>$access['write'] && $access['submit'] && $a->state === 'review' && $last?->action === 'returned',
                    'returned','rated'=>$access[$action === 'rated' ? 'review' : 'return'] && $a->state === 'review' && in_array($last?->action,['submitted','revised'],true),
                };
                abort_unless($allowed,403);
                $this->current($r,$last?->id ?? 0);
                $link = $unit && $r->input('evidence_mode') === 'link';
                $r->validate(['note'=>[$action === 'returned' ? 'required' : 'nullable','string','max:10000'],
                    'file'=>[$unit && !$link ? 'nullable' : 'prohibited','file','mimes:pdf,jpg,jpeg,png','max:5120'],
                    'evidence_mode'=>['nullable',Rule::in(['file','link'])],
                    'evidence_url'=>$link ? ['required','string','max:2000',fn($attr,$value,$fail)=>EvidenceLink::normalize($value) ?: $fail(EvidenceLink::MESSAGE)] : ['nullable']]);
                $answer = $action === 'returned' ? null : $this->answer($r,$question);
                if ($action === 'rated' && $answer != json_decode($last->answer,true) && !$r->filled('note')) {
                    throw ValidationException::withMessages(['note'=>'Jelaskan alasan perbedaan penilaian TPI.']);
                }
                $evidence = null;
                $evidenceUrl = null;
                if ($unit) {
                    // Bukti dukung berupa berkas privat ATAU tautan Google Drive; tanpa pilihan baru, bukti sebelumnya dipakai.
                    $previous = $this->entries($assessment)->where('item',$item)->whereIn('action',['draft','submitted','revised'])
                        ->orderByDesc('id')->first(['evidence','evidence_url']);
                    $evidence = $previous?->evidence;
                    $evidenceUrl = $previous?->evidence_url;
                    if ($link) {
                        $evidence = null;
                        $evidenceUrl = EvidenceLink::normalize($r->input('evidence_url'));
                    } elseif ($r->hasFile('file')) {
                        $uploaded = $r->file('file')->store('zi/'.$assessment,'local');
                        if (!$uploaded) throw new \RuntimeException('Bukti gagal disimpan.');
                        $evidence = $uploaded;
                        $evidenceUrl = null;
                    }
                }
                $this->entries($assessment)->insert(['assessment_id'=>$assessment,'item'=>$item,'action'=>$action,
                    'answer'=>$answer === null ? null : json_encode($answer),'note'=>$r->input('note'),'evidence'=>$evidence,'evidence_url'=>$evidenceUrl,
                    'actor_id'=>$r->user()->id,'created_at'=>now()]);
                DB::table('zi_assessments')->where('id',$assessment)->increment('revision',1,['updated_at'=>now()]);
            });
        } catch (\Throwable $e) {
            if ($uploaded) Storage::disk('local')->delete($uploaded);
            throw $e;
        }
        return to_route('zi.item',[$assessment,$item])->with('status','Perubahan butir tersimpan.');
    }

    public function submit(Request $r, int $assessment)
    {
        DB::transaction(function () use ($r,$assessment) {
            $a = $this->assessment($assessment,true);
            abort_unless($this->roles($r,$a)['submit'] && $a->state === 'draft',403);
            $this->current($r,$a->revision);
            $items = ZiInstrument::items($a->instrument);
            $latest = $this->entries($assessment)->orderBy('id')->get()->keyBy('item');
            foreach ($items as $id=>$question) {
                $row = $latest->get($id);
                $hasFile = $row && $row->evidence && Storage::disk('local')->exists($row->evidence);
                if (!$row || !($hasFile || $row->evidence_url)) {
                    throw ValidationException::withMessages(['item'=>'Lengkapi jawaban dan bukti pada butir '.$id.'.']);
                }
                $this->entries($assessment)->insert(['assessment_id'=>$assessment,'item'=>$id,'action'=>'submitted',
                    'answer'=>$row->answer,'note'=>$row->note,'evidence'=>$row->evidence,'evidence_url'=>$row->evidence_url,'actor_id'=>$r->user()->id,'created_at'=>now()]);
            }
            DB::table('zi_assessments')->where('id',$assessment)->update(['state'=>'review','submitted_at'=>now(),'submitted_by'=>$r->user()->id,'updated_at'=>now()]);
        });
        return to_route('zi.show',$assessment)->with('status','LKE diajukan ke TPI.');
    }

    public function finalize(Request $r, int $assessment)
    {
        DB::transaction(function () use ($r,$assessment) {
            $a = $this->assessment($assessment,true);
            abort_unless($this->roles($r,$a)['finalize'] && $a->state === 'review',403);
            $this->current($r,$a->revision);
            $latest = $this->entries($assessment)->orderBy('id')->get()->keyBy('item');
            $result = ZiInstrument::result(ZiInstrument::items($a->instrument),$latest,$a->target,$a->instrument);
            if (!$result['complete']) throw ValidationException::withMessages(['item'=>'Selesaikan seluruh reviu dan perbaikan sebelum finalisasi.']);
            DB::table('zi_assessments')->where('id',$assessment)->update(['state'=>'final','final_result'=>json_encode($result),
                'finalized_at'=>now(),'finalized_by'=>$r->user()->id,'updated_at'=>now()]);
        });
        return to_route('zi.show',$assessment)->with('status','Rekap penilaian sudah final.');
    }

    public function download(Request $r, int $assessment, int $entry)
    {
        $a = $this->assessment($assessment);
        $row = $this->visibleEntries($a,$this->roles($r,$a))->where('id',$entry)->first();
        abort_unless($row?->evidence && Storage::disk('local')->exists($row->evidence),404);
        return Storage::disk('local')->download($row->evidence,'Bukti-'.$row->item.'-'.$row->id.'.'.pathinfo($row->evidence,PATHINFO_EXTENSION),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
