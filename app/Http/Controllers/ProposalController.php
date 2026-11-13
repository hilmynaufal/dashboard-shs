<?php

namespace App\Http\Controllers;

use App\Authorization\ResourceAccess;
use App\Models\{Proposal, ProposalItem, ProposalItemVersion, SubmissionPeriod};
use App\Support\{AccountValidation, CatalogAudit, ItemVerification, ProposalDraft, ProposalItemInput, RecordVersion, ReferenceCatalog, SubmissionWindow};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class ProposalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(array_intersect($user->permissionCodes(), ['proposals.view_own','proposals.view_all','proposals.view_assigned']),403);
        $filters = $request->validate(['q'=>['nullable','string','max:100'],'type'=>['nullable',Rule::in(['SHS','SBU'])],
            'status'=>['nullable',Rule::in(['draft','submitted','verification','review','completed'])]],AccountValidation::MESSAGES);
        $visible = app(ResourceAccess::class)->visibleItemIds($user);
        $query = Proposal::query()->where(function ($q) use ($user,$visible): void {
            $q->whereIn('id', DB::table('proposal_items')->select('proposal_id')->whereIn('id',clone $visible));
            if ($user->hasPermission('proposals.view_own') && $user->organization_id !== null) { $q->orWhere('organization_id',$user->organization_id); }
        });
        $term = $filters['q'] ?? null;
        $packages = $query->when($term !== null,fn($q)=>$q->where(fn($q)=>$q->where('title','like',ReferenceCatalog::searchPattern($term))->orWhere('number','like',ReferenceCatalog::searchPattern($term))))
            ->when($filters['type']??null,fn($q,$v)=>$q->where('type',$v))->when($filters['status']??null,fn($q,$v)=>$q->where('status',$v))
            ->select('proposals.*')->selectSub(DB::table('proposal_items')->selectRaw('COUNT(*)')->whereColumn('proposal_id','proposals.id')->whereIn('id',$visible),'item_count')
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('proposals.index',compact('packages'));
    }

    public function create(): View
    {
        Gate::authorize('create',Proposal::class);

        return view('proposals.create',['periods'=>SubmissionPeriod::where('status','open')->orderByDesc('budget_year')->get()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create',Proposal::class);
        $data = $request->validate(['title'=>['required','string','max:255'],'type'=>['required',Rule::in(['SHS','SBU'])],
            'submission_period_id'=>['required','integer','exists:submission_periods,id'], 'organization_id'=>['prohibited'],'created_by'=>['prohibited'],'status'=>['prohibited'],'number'=>['prohibited']],AccountValidation::MESSAGES,
            ['title'=>'Nama paket','type'=>'Jenis usulan','submission_period_id'=>'Periode pengusulan']);
        $proposal = DB::transaction(function () use ($request,$data): Proposal {
            Gate::forUser($request->user()->refresh())->authorize('create',Proposal::class);
            $period = SubmissionPeriod::query()->lockForUpdate()->findOrFail($data['submission_period_id']);
            if ($period->status !== 'open') { throw ValidationException::withMessages(['submission_period_id'=>'Pilih periode berstatus Dibuka untuk membuat draf.']); }
            $proposal = new Proposal;
            $proposal->title=$data['title']; $proposal->type=$data['type']; $proposal->submission_period_id=$period->id;
            $proposal->organization_id=$request->user()->organization_id; $proposal->created_by=$request->user()->id; $proposal->status='draft'; $proposal->save();
            $proposal->number='USL/'.$period->budget_year.'/'.str_pad((string)$proposal->id,6,'0',STR_PAD_LEFT); $proposal->save();
            CatalogAudit::record($request->user()->id,'proposal.created','proposal',$proposal->id,null,['number'=>$proposal->number]);

            return $proposal;
        });

        return $request->expectsJson()?response()->json(['id'=>$proposal->id,'message'=>'Draf usulan tersimpan.'],201):redirect()->route('proposals.show',$proposal)->with('status','Draf usulan tersimpan.');
    }

    public function show(Request $request, Proposal $proposal): View
    {
        Gate::authorize('view',$proposal);
        $request->validate(['page'=>['nullable','integer','min:1','max:100000']]);
        $rows=ProposalDraft::rows($proposal,$request->user()); $page=(int)$request->input('page',1);
        $items=new LengthAwarePaginator($rows->forPage($page,25)->values(),$rows->count(),25,$page,['path'=>$request->url(),'query'=>$request->query()]);

        return view('proposals.show',['proposal'=>$proposal,'items'=>$items,'period'=>SubmissionPeriod::findOrFail($proposal->submission_period_id),
            'itemModels'=>ProposalItem::whereIn('id',$items->pluck('item_id'))->get()->keyBy('id'),
            'decisions'=>DB::table('verification_results as result')->join('proposal_item_versions as version','version.id','=','result.item_version_id')
                ->whereIn('version.proposal_item_id',$items->pluck('item_id'))->orderByDesc('result.id')->select('result.*','version.proposal_item_id')->get()->groupBy('proposal_item_id')->map->first(),
            'batches'=>DB::table('import_batches')->where('proposal_id',$proposal->id)->orderByDesc('id')->get(['id','status','sheet_name']),
            'draftVersion'=>Gate::allows('update',$proposal)?ProposalDraft::version($proposal):null]);
    }

    public function itemForm(Proposal $proposal, ?int $item = null): View
    {
        $itemModel=$item===null?null:ItemVerification::item($proposal,$item);
        Gate::authorize('update',$itemModel??$proposal);
        $version=$item===null?null:$this->itemVersion($proposal,$item);

        return view('proposals.item',['proposal'=>$proposal,'itemId'=>$item,'version'=>$version,'fields'=>$version?ProposalItemInput::fields($version):[],
            'itemModel'=>$itemModel,'verificationHistory'=>$item?ItemVerification::history($item):collect(),
            'verificationVersion'=>$item?ItemVerification::token($itemModel):null,
            'recordVersion'=>$version?RecordVersion::of((array)$version):ProposalDraft::version($proposal),
            'files'=>$version?DB::table('item_version_evidence')->join('evidence_files','evidence_files.id','=','evidence_file_id')->where('item_version_id',$version->id)->where('organization_id',$proposal->organization_id)->select('evidence_files.*')->get():collect(),
            'versions'=>$item?DB::table('proposal_item_versions')->where('proposal_item_id',$item)->orderByDesc('version_number')->get(['id','version_number','created_at']):collect()]);
    }

    public function saveItem(Request $request, Proposal $proposal, ?int $item = null): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$item===null?$proposal:ItemVerification::item($proposal,$item));
        $request->validate(['record_version'=>['required','string','regex:/\A[a-f0-9]{64}\z/']],['required'=>'Muat ulang formulir sebelum menyimpan.']);
        $data=(new ProposalItemInput)->validate($request->only(['name','spec','code','unit','account','price']));
        $versionId=DB::transaction(function () use ($request,$proposal,$item,$data): int {
            $locked=$item===null?ProposalDraft::lock($request->user(),$proposal->id):ItemVerification::lock($request->user(),$item,'update',$proposal->id)[0];
            $state=$item===null?null:(array)$this->itemVersion($locked,$item);
            if ($item===null) { if (!hash_equals(ProposalDraft::version($locked),$request->input('record_version'))) { throw ValidationException::withMessages(['record_version'=>'Draf berubah. Muat ulang formulir.'])->status(409); } }
            else { RecordVersion::assertCurrent($request->input('record_version'),$state); }
            $id=ProposalDraft::append($locked,$request->user(),$data,$item);
            CatalogAudit::record($request->user()->id,'proposal.item_saved','proposal_item_version',$id,null,['proposal_id'=>$locked->id]);

            return $id;
        },3);

        return $request->expectsJson()?response()->json(['message'=>'Item tersimpan sebagai versi baru.','version_id'=>$versionId],$item===null?201:200):redirect()->route('proposals.show',$proposal)->with('status','Item tersimpan sebagai versi baru.');
    }

    public function check(Request $request,Proposal $proposal): View
    {
        Gate::authorize('view',$proposal);
        // Read-only team viewers must not receive other items' validation data.
        $editable=Gate::allows('update',$proposal)||Gate::allows('submit',$proposal);

        $request->validate(['page'=>['nullable','integer','min:1','max:100000']]);$all=ProposalDraft::rows($proposal,$request->user());$page=(int)$request->input('page',1);
        $rows=new LengthAwarePaginator($all->forPage($page,25)->values(),$all->count(),25,$page,['path'=>$request->url(),'query'=>$request->query()]);
        return view('proposals.check',['proposal'=>$proposal,'problems'=>$editable?ProposalDraft::problems($proposal):[],
            'rows'=>$rows,'draftVersion'=>$editable?ProposalDraft::version($proposal):null]);
    }

    public function version(Proposal $proposal,int $item,ProposalItemVersion $version): View
    {
        Gate::authorize('view',$proposal);abort_unless((int)$version->proposal_item_id===$item&&DB::table('proposal_items')->where('id',$item)->where('proposal_id',$proposal->id)->exists(),404);
        Gate::authorize('view',$version);

        return view('proposals.version',['proposal'=>$proposal,'itemId'=>$item,'version'=>$version,'fields'=>ProposalItemInput::fields($version),
            'verificationHistory'=>ItemVerification::history($item)->where('item_version_id',$version->id),
            'files'=>DB::table('item_version_evidence')->join('evidence_files','evidence_files.id','=','evidence_file_id')->where('item_version_id',$version->id)->where('organization_id',$proposal->organization_id)->select('evidence_files.*')->get()]);
    }

    public function submit(Request $request, Proposal $proposal): JsonResponse|RedirectResponse
    {
        Gate::authorize('submit',$proposal);
        $data=$request->validate(['record_version'=>['required','string','regex:/\A[a-f0-9]{64}\z/'],'confirm'=>['accepted']],['accepted'=>'Centang pernyataan pemeriksaan sebelum mengirim.','required'=>'Muat ulang halaman pemeriksaan sebelum mengirim.']);
        DB::transaction(function () use ($request,$proposal,$data): void {
            $locked=ProposalDraft::lock($request->user(),$proposal->id,'submit');
            if (!hash_equals(ProposalDraft::version($locked),$data['record_version'])) { throw ValidationException::withMessages(['record_version'=>'Isi usulan berubah. Periksa kembali sebelum mengirim.'])->status(409); }
            $period=SubmissionPeriod::query()->lockForUpdate()->findOrFail($locked->submission_period_id); SubmissionWindow::assertOpen($period);
            $problems=ProposalDraft::problems($locked,true);
            if ($problems) { $errors=[];foreach($problems as $key=>$messages){$errors['items.'.$key]=array_map(fn($m)=>($key==='package'||$key==='import'?'Paket':'Item #'.$key).': '.$m,$messages);}throw ValidationException::withMessages($errors); }
            $rows=ProposalDraft::rows($locked); $ids=$rows->pluck('version_id')->all();
            foreach (DB::table('item_version_evidence')->join('evidence_files','evidence_files.id','=','evidence_file_id')->whereIn('item_version_id',$ids)->get(['disk','path']) as $file) {
                if ($file->disk!=='local'||!Storage::disk('local')->exists($file->path)) { throw ValidationException::withMessages(['evidence'=>'Ada berkas bukti yang tidak tersedia. Unggah kembali sebelum mengirim.']); }
            }
            $time=now(); DB::table('proposal_item_versions')->whereIn('id',$ids)->whereNull('submitted_at')->update(['submitted_at'=>$time]);
            DB::table('proposal_items')->where('proposal_id',$locked->id)->update(['status'=>'submitted','updated_at'=>$time]);
            $locked->status='submitted';$locked->submitted_at=$time;$locked->save();
            CatalogAudit::record($request->user()->id,'proposal.submitted','proposal',$locked->id,null,['version_ids'=>$ids,'submitted_at'=>$time->toIso8601String(),'evidence_optional_demo'=>\App\Support\DemoMode::allowsMissingEvidence()]);
        },3);

        return $request->expectsJson()?response()->json(['message'=>'Usulan berhasil diajukan.']):redirect()->route('proposals.show',$proposal)->with('status','Usulan berhasil diajukan. Isi yang terkirim kini hanya-baca.');
    }

    public function references(Request $request,string $kind): JsonResponse
    {
        Gate::authorize('create',Proposal::class); $type=ReferenceCatalog::type($kind);
        $data=$request->validate(['q'=>['required','string','max:100']],AccountValidation::MESSAGES);

        return response()->json(['options'=>DB::table($type['table'])->where('is_active',true)->where(fn($q)=>$q->where('code','like',ReferenceCatalog::searchPattern($data['q']))->orWhere('name','like',ReferenceCatalog::searchPattern($data['q'])))->orderBy('code')->limit(25)->get(['code','name'])])->header('Cache-Control','no-store');
    }

    private function itemVersion(Proposal $proposal,int $item): object
    {
        abort_unless(DB::table('proposal_items')->where('id',$item)->where('proposal_id',$proposal->id)->exists(),404);
        $version=DB::table('proposal_item_versions')->where('proposal_item_id',$item)->orderByDesc('version_number')->first();abort_unless($version,404);

        return $version;
    }
}
