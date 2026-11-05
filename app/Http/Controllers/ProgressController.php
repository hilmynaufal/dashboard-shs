<?php

namespace App\Http\Controllers;

use App\Authorization\ResourceAccess;
use App\Models\{KkrDocument,Proposal,ProposalItem,ReviewNote};
use App\Support\{AccountValidation,ProgressBoard};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(array_intersect($request->user()->permissionCodes(),['monitoring.own','monitoring.all','monitoring.assigned']),403);
        $filters=$request->validate(['view'=>['nullable',Rule::in(array_keys(ProgressBoard::VIEWS))],'status'=>['nullable','string','max:50'],
            'metric'=>['nullable',Rule::in(array_keys(ProgressBoard::METRICS))],'page'=>['nullable','integer','min:1','max:100000']],AccountValidation::MESSAGES);
        $view=$filters['view']??'items';
        $labels=match($view){'packages'=>Proposal::LABELS,'notes'=>['open'=>'Semua catatan terbuka']+ReviewNote::LABELS,'kkr'=>KkrDocument::LABELS,default=>ProposalItem::LABELS};
        validator($filters,['status'=>['nullable',Rule::in(['all',...array_keys($labels)])]],AccountValidation::MESSAGES)->validate();
        if (($filters['metric']??null)!==null && ($filters['status']??'all')!=='all') {
            throw \Illuminate\Validation\ValidationException::withMessages(['status'=>'Pilih indikator reviu atau status item secara terpisah.']);
        }
        $data=DB::transaction(fn()=>(new ProgressBoard($request->user(),app(ResourceAccess::class)))->page($filters));
        return view('progress.index',$data+['labels'=>$labels,'refreshedAt'=>now()->timezone('Asia/Jakarta')]);
    }
}
