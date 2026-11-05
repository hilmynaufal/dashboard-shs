<?php

namespace App\Http\Controllers;

use App\Authorization\ResourceAccess;
use App\Models\ProposalItem;
use App\Support\{AccountValidation,CatalogAudit,ItemReport,ReportXlsx};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ItemReportController extends Controller
{
    public function index(Request $request): View
    {
        [$report,$filters]=$this->context($request);
        return view('reports.index',['rows'=>$report->query($filters)->paginate(20)->withQueryString(),'filters'=>$filters,
            'organizations'=>$report->organizations(),'years'=>$report->years()]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        [$report,$filters]=$this->context($request);unset($filters['page']);
        // Read one consistent data snapshot while streaming selected values to private temporary files.
        $path=null;
        try {
            $path=DB::transaction(function()use($report,$filters,&$path): string {
                $rows=(function()use($report,$filters){$n=0;foreach($report->query($filters)->lazyById(500,'item.id','item_id') as $row){yield ItemReport::values($row,++$n);}})();
                return $path=(new ReportXlsx)->create($rows);
            });
            CatalogAudit::record($request->user()->id,'report.excel_exported','user',$request->user()->id,null,['filters'=>$filters]);
            return response()->download($path,'Rekap-SHS-SBU.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'])->deleteFileAfterSend();
        } catch(\Throwable $error) {if($path!==null && is_file($path)){unlink($path);}throw $error;}
    }

    private function context(Request $request): array
    {
        abort_unless(array_intersect($request->user()->permissionCodes(),['reports.own','reports.all','reports.assigned']),403);
        $filters=$request->validate(['q'=>['nullable','string','max:100'],'year'=>['nullable','integer','between:1000,9999'],
            'type'=>['nullable',Rule::in(['SHS','SBU'])],'status'=>['nullable',Rule::in(array_keys(ProposalItem::LABELS))],
            'organization'=>['nullable','integer','min:1'],'page'=>['nullable','integer','min:1','max:100000']],AccountValidation::MESSAGES);
        $filters=array_filter($filters,fn($v)=>$v!==null && $v!=='');
        $report=new ItemReport($request->user(),app(ResourceAccess::class));
        if(isset($filters['organization'])){
            abort_unless($report->organizations()->contains(fn($org)=>(int)$org->id===(int)$filters['organization']),403,'Pilihan SKPD berada di luar lingkup rekap Anda.');
        }
        return [$report,$filters];
    }
}
