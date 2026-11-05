<?php

namespace App\Http\Controllers;

use App\Authorization\ResourceAccess;
use App\Models\{KkrDocument,KkrVersion};
use App\Support\{AccountValidation,KkrExport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\View\View;

class KkrArchiveController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('archives.assigned');
        $request->validate(['page'=>['nullable','integer','min:1','max:100000']],AccountValidation::MESSAGES);
        $versions=KkrVersion::whereNotNull('finalized_at')->whereIn('kkr_document_id',app(ResourceAccess::class)->visibleKkrIds($request->user()))
            ->select('kkr_versions.*')->selectSub(DB::table('kkr_versions as other')->selectRaw('MAX(other.version_number)')->whereColumn('other.kkr_document_id','kkr_versions.kkr_document_id')->whereNotNull('other.finalized_at'),'latest_final_number')
            ->orderByDesc('finalized_at')->orderByDesc('id')->paginate(20)->withQueryString();
        return view('archives.index',compact('versions'));
    }

    public function show(Request $request,KkrVersion $version): View
    {
        Gate::authorize('archives.assigned');$document=KkrDocument::findOrFail($version->kkr_document_id);Gate::authorize('view',$document);abort_unless($version->finalized_at,404);
        $presentation=KkrExport::presentation($version,$request->user());
        $latestFinal=KkrVersion::where('kkr_document_id',$document->id)->whereNotNull('finalized_at')->max('version_number');
        return view('archives.show',['document'=>$document,'version'=>$version,'print'=>$presentation['model'],'latestFinal'=>(int)$latestFinal]);
    }
}
