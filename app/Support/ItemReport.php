<?php

namespace App\Support;

use App\Authorization\ResourceAccess;
use App\Models\{ProposalItem,User};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ItemReport
{
    public const HEADERS=['No.','Nomor paket','Tahun anggaran','Jenis','SKPD','Kode barang','Uraian','Spesifikasi','Satuan','Rekening','Harga usulan (Rp)','Status item','Versi usulan'];
    private Builder $base;

    public function __construct(User $user,ResourceAccess $access)
    {
        $own=$user->hasPermission('reports.own') && $user->hasPermission('proposals.view_own') && $user->organization_id!==null;
        $this->base=DB::table('proposal_items as item')->join('proposals as proposal','proposal.id','=','item.proposal_id')
            ->join('organizations as org','org.id','=','proposal.organization_id')->join('submission_periods as period','period.id','=','proposal.submission_period_id')
            ->join('proposal_item_versions as version',function($join)use($user,$own): void {
                $join->on('version.proposal_item_id','=','item.id')->where('version.id','=',function($q)use($user,$own): void {
                    $q->from('proposal_item_versions as latest')->selectRaw('MAX(latest.id)')->whereColumn('latest.proposal_item_id','item.id')
                        ->where(function($q)use($user,$own): void {
                            $q->whereNotNull('latest.submitted_at');
                            if($own){$q->orWhere('proposal.organization_id',$user->organization_id);}
                        });
                });
            })->whereIn('item.id',$access->reportedItemIds($user));
    }

    public function organizations()
    {
        return (clone $this->base)->select('org.id','org.code','org.name')->distinct()->orderBy('org.name')->orderBy('org.id')->get();
    }

    public function years(): array {return (clone $this->base)->select('period.budget_year')->distinct()->orderByDesc('period.budget_year')->pluck('period.budget_year')->all();}

    public function query(array $filters): Builder
    {
        $query=clone $this->base;
        if(($filters['q']??'')!==''){
            $term=ReferenceCatalog::searchPattern($filters['q']);
            $query->where(fn($q)=>$q->where('version.description','like',$term)->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(version.reference_snapshot, '$.classification.code')) LIKE ?",[$term]));
        }
        if(isset($filters['year'])){$query->where('period.budget_year',$filters['year']);}
        if(isset($filters['type'])){$query->where('proposal.type',$filters['type']);}
        if(isset($filters['status'])){$query->where('item.status',$filters['status']);}
        if(isset($filters['organization'])){$query->where('org.id',$filters['organization']);}
        return $query->select('item.id as item_id','item.proposal_id','item.status','proposal.number','proposal.type','org.name as organization_name','period.budget_year',
            'version.id as version_id','version.version_number','version.description','version.specification','version.proposed_price','version.reference_snapshot')->orderBy('item.id');
    }

    /** One item stays one row, including all its account codes, taken from the saved reference snapshot. */
    public static function values(object $row,int $number): array
    {
        $s=is_array($row->reference_snapshot)?$row->reference_snapshot:json_decode($row->reference_snapshot,true,512,JSON_THROW_ON_ERROR);
        [$whole,$fraction]=explode('.',$row->proposed_price);
        return [(string)$number,$row->number??'',(string)$row->budget_year,$row->type,$row->organization_name,$s['classification']['code']??'',
            $row->description,$row->specification??'',$s['unit']['name']??'',implode('; ',array_column($s['accounts']??[],'code')),
            preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction,ProposalItem::LABELS[$row->status]??$row->status,(string)$row->version_number];
    }
}
