<?php

namespace App\Http\Controllers;

use App\Authorization\ResourceAccess;
use App\Models\{KkrDocument,KkrVersion,ReviewAssignment};
use App\Support\{AccountValidation,CatalogAudit,KkrWork};
use Illuminate\Database\QueryException;
use Illuminate\Http\{JsonResponse,RedirectResponse,Request};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Validation\{Rule,ValidationException};
use Illuminate\View\View;

class KkrController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('kkr.view');
        $data=$request->validate(['queue'=>['nullable',Rule::in(['mine','lead','technical','all'])],'page'=>['nullable','integer','min:1','max:100000']],AccountValidation::MESSAGES);
        $queue=$data['queue']??($request->user()->hasRole('examiner')?'mine':($request->user()->hasRole('team_lead')?'lead':'technical'));
        $query=KkrDocument::whereIn('id',app(ResourceAccess::class)->visibleKkrIds($request->user()));
        if($queue==='mine'){$query->where('prepared_by',$request->user()->id);}
        if(in_array($queue,['lead','technical'],true)){
            $role=$queue==='lead'?'team_lead':'technical_controller';
            $query->where('status',$queue==='lead'?'awaiting_lead':'awaiting_controller')->whereIn('review_assignment_id',DB::table('assignment_members')->select('review_assignment_id')->where('user_id',$request->user()->id)->where('role',$role)->where('is_active',true));
            if(!$request->user()->hasRole($role)){$query->whereRaw('1 = 0');}
        }
        return view('kkr.index',['documents'=>$query->orderByDesc('id')->paginate(20)->withQueryString(),'queue'=>$queue]);
    }

    public function create(Request $request,ReviewAssignment $assignment): View
    {
        Gate::authorize('create',[KkrDocument::class,$assignment]);
        return view('kkr.form',['assignment'=>$assignment,'document'=>null,'version'=>null,'candidates'=>KkrWork::candidates($request->user(),$assignment)->limit(200)->get(),'recordVersion'=>KkrWork::createToken($request->user(),$assignment)]);
    }

    public function store(Request $request,ReviewAssignment $assignment): JsonResponse|RedirectResponse
    {
        Gate::authorize('create',[KkrDocument::class,$assignment]);
        $data=$request->validate($this->formRules()+['number'=>['required','string','max:100','unique:kkr_documents,number']],AccountValidation::MESSAGES);
        try{
            $document=DB::transaction(function()use($request,$assignment,$data):KkrDocument{
                $locked=KkrWork::lockAssignment($request->user(),$assignment->id);Gate::forUser($request->user())->authorize('create',[KkrDocument::class,$locked]);
                if(!hash_equals(KkrWork::createToken($request->user(),$locked),$data['record_version'])){throw ValidationException::withMessages(['record_version'=>'Hasil atau penugasan berubah. Muat ulang daftar KKR.'])->status(409);}
                $document=new KkrDocument;$document->review_assignment_id=$locked->id;$document->prepared_by=$request->user()->id;$document->number=$data['number'];$document->title=$data['title'];$document->status='draft';$document->save();
                KkrWork::append($request->user(),$document,$locked,$data);return $document;
            },3);
        }catch(QueryException $error){if(($error->errorInfo[1]??null)===1062){throw ValidationException::withMessages(['number'=>'Nomor kerja KKR sudah digunakan.']);}throw $error;}
        return $request->expectsJson()?response()->json(['id'=>$document->id,'message'=>'Versi KKR disiapkan.'],201):redirect()->route('kkr.show',$document)->with('status','Versi KKR disiapkan; periksa isinya sebelum mengajukan supervisi.');
    }

    public function edit(Request $request,KkrDocument $document): View
    {
        Gate::authorize('update',$document);$assignment=ReviewAssignment::findOrFail($document->review_assignment_id);
        return view('kkr.form',['assignment'=>$assignment,'document'=>$document,'version'=>KkrWork::latest($document),'candidates'=>KkrWork::candidates($request->user(),$assignment)->limit(200)->get(),'recordVersion'=>KkrWork::token($document)]);
    }

    public function revise(Request $request,KkrDocument $document): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$document);
        $data=$request->validate($this->formRules()+['change_reason'=>['required','string','max:10000']],AccountValidation::MESSAGES);
        DB::transaction(function()use($request,$document,$data):void{
            [$locked,$assignment]=KkrWork::lockDocument($request->user(),$document,$data['record_version']);Gate::forUser($request->user())->authorize('update',$locked);
            KkrWork::append($request->user(),$locked,$assignment,$data);
        },3);
        return $this->done($request,$document,'Versi baru tersimpan. Persetujuan versi sebelumnya tidak dipindahkan.');
    }

    public function show(Request $request,KkrDocument $document): View
    {
        Gate::authorize('view',$document);
        $data=$request->validate(['version'=>['nullable','integer','min:1'],'version_page'=>['nullable','integer','min:1','max:100000'],'item_page'=>['nullable','integer','min:1','max:100000']],AccountValidation::MESSAGES);
        $latest=KkrWork::latest($document);abort_unless($latest,404);
        $version=isset($data['version'])?KkrVersion::where('kkr_document_id',$document->id)->findOrFail($data['version']):$latest;
        $entries=collect($version->snapshot['source']['entries']??[]);$page=(int)($data['item_page']??1);
        $rows=new LengthAwarePaginator($entries->forPage($page,10)->values(),$entries->count(),10,$page,['path'=>$request->url(),'pageName'=>'item_page','query'=>$request->query()]);
        $integrity=KkrWork::integrity($version);$current=$version->finalized_at!==null?null:($integrity && KkrWork::current($document,$version));
        return view('kkr.show',['document'=>$document,'version'=>$version,'latest'=>$latest,'entries'=>$rows,'integrity'=>$integrity,'current'=>$current,
            'activeAssignment'=>ReviewAssignment::whereKey($document->review_assignment_id)->where('status','active')->exists(),
            'openCount'=>KkrWork::openCount($version),'recordVersion'=>KkrWork::token($document),
            'versions'=>KkrVersion::where('kkr_document_id',$document->id)->orderByDesc('version_number')->paginate(20,['*'],'version_page')->withQueryString(),
            'approvals'=>DB::table('kkr_approvals')->where('kkr_version_id',$version->id)->orderBy('id')->get()]);
    }

    public function submit(Request $request,KkrDocument $document): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$document);
        $data=$request->validate($this->tokenRules()+['version_id'=>['required','integer','min:1'],'confirm'=>['accepted'],'acknowledge_open_notes'=>['nullable','boolean']],AccountValidation::MESSAGES);
        DB::transaction(function()use($request,$document,$data):void{
            [$locked,$assignment]=KkrWork::lockDocument($request->user(),$document,$data['record_version']);Gate::forUser($request->user())->authorize('update',$locked);
            $version=KkrWork::latest($locked);
            if($locked->status!=='draft' || !$version || $version->submitted_at!==null || (int)$version->id!==(int)$data['version_id']){throw ValidationException::withMessages(['kkr'=>'Siapkan versi draf terbaru sebelum mengajukan ulang.']);}
            KkrWork::assertCurrent($locked,$version);$this->acknowledge($version,$data);
            $version->submitted_at=now();$version->save();$locked->status='awaiting_lead';$locked->lock_version++;$locked->save();
            CatalogAudit::record($request->user()->id,'kkr.submitted','kkr_version',$version->id,null,['document_id'=>$locked->id,'snapshot_sha256'=>$version->snapshot_sha256]);
        },3);
        return $this->done($request,$document,'KKR diajukan ke antrean Ketua Tim.');
    }

    public function decide(Request $request,KkrDocument $document,KkrVersion $version): JsonResponse|RedirectResponse
    {
        abort_unless((int)$version->kkr_document_id===(int)$document->id,404);
        $data=$request->validate($this->tokenRules()+['level'=>['required',Rule::in(['team_lead','technical_controller'])],'decision'=>['required',Rule::in(['approved','returned'])],
            'notes'=>['required_if:decision,returned','nullable','string','max:10000'],'acknowledge_open_notes'=>['nullable','boolean']],AccountValidation::MESSAGES);
        $ability=$data['level']==='team_lead'?'approveLead':'approveTechnical';Gate::authorize($ability,$version);
        DB::transaction(function()use($request,$document,$version,$data,$ability):void{
            [$locked,$assignment]=KkrWork::lockDocument($request->user(),$document,$data['record_version']);$saved=KkrVersion::query()->lockForUpdate()->findOrFail($version->id);
            Gate::forUser($request->user())->authorize($ability,$saved);
            if($data['decision']==='approved'){KkrWork::assertCurrent($locked,$saved);$this->acknowledge($saved,$data);}
            DB::table('kkr_approvals')->insert(['kkr_version_id'=>$saved->id,'decided_by'=>$request->user()->id,'level'=>$data['level'],'decision'=>$data['decision'],'notes'=>$data['notes']??null,'decided_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            if($data['decision']==='returned'){$locked->status='returned';}
            elseif($data['level']==='team_lead'){$locked->status='awaiting_controller';}
            else{
                // Both decisions belong to this same immutable version; technical approval seals it.
                $saved->finalized_at=now();$saved->save();$locked->status='final';
                CatalogAudit::record($request->user()->id,'kkr.finalized','kkr_version',$saved->id,null,['snapshot_sha256'=>$saved->snapshot_sha256,'open_notes'=>KkrWork::openCount($saved)]);
            }
            $locked->lock_version++;$locked->save();
            CatalogAudit::record($request->user()->id,'kkr.supervision_decided','kkr_version',$saved->id,null,['level'=>$data['level'],'decision'=>$data['decision'],'notes'=>$data['notes']??null]);
        },3);
        $message=$data['decision']==='returned'?'KKR dikembalikan beserta alasan. Pemeriksa perlu menyiapkan versi baru.':($data['level']==='team_lead'?'KKR diteruskan ke antrean Pengendali Teknis.':'Persetujuan berjenjang lengkap; versi KKR dikunci final.');
        return $this->done($request,$document,$message);
    }

    private function acknowledge(KkrVersion $version,array $data): void
    {
        if(KkrWork::openCount($version)>0 && !($data['acknowledge_open_notes']??false)){throw ValidationException::withMessages(['acknowledge_open_notes'=>'Konfirmasikan bahwa catatan yang masih terbuka sudah terbaca dan tercantum dalam versi ini.']);}
    }

    private function tokenRules(): array {return ['record_version'=>['required','string','regex:/\A[a-f0-9]{64}\z/']];}
    private function formRules(): array
    {
        return $this->tokenRules()+['title'=>['required','string','max:255'],'conclusion'=>['required','string','max:10000'],'recommendation'=>['required','string','max:10000'],
            'reviews'=>['required','array','min:1','max:200'],'reviews.*'=>['required','integer','distinct'],'snapshot'=>['prohibited'],'status'=>['prohibited'],'prepared_by'=>['prohibited'],'finalized_at'=>['prohibited']];
    }
    private function done(Request $request,KkrDocument $document,string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()?response()->json(['message'=>$message]):redirect()->route('kkr.show',$document)->with('status',$message);
    }
}
