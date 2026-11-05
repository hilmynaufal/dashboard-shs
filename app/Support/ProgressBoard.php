<?php

namespace App\Support;

use App\Authorization\ResourceAccess;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ProgressBoard
{
    public const VIEWS=['packages'=>'Paket usulan','items'=>'Item usulan','notes'=>'Catatan & tindak lanjut','kkr'=>'KKR & supervisi'];
    public const METRICS=['completed'=>'Reviu selesai','compliant'=>'Hasil sesuai'];
    private Builder $items;
    private Builder $reviews;
    private Builder $notes;
    private Builder $packages;
    private Builder $kkr;
    public readonly bool $canReview;
    public readonly bool $canKkr;

    public function __construct(private User $user,ResourceAccess $access)
    {
        $ids=$access->monitoredItemIds($user);
        $this->items=DB::table('proposal_items as item')->join('proposals as proposal','proposal.id','=','item.proposal_id')->whereIn('item.id',$ids);
        // Latest review visible to this actor per item, never another team's newer review.
        $latest=DB::table('item_reviews')->selectRaw('MAX(id)')->whereIn('id',$access->visibleReviewIds($user))->groupBy('proposal_item_id');
        $this->reviews=DB::table('item_reviews as review')->whereIn('review.id',$latest)->whereIn('review.proposal_item_id',clone $ids);
        $this->notes=DB::table('review_notes as note')->join('proposal_item_versions as version','version.id','=','note.item_version_id')
            ->join('proposal_items as item','item.id','=','version.proposal_item_id')->join('proposals as proposal','proposal.id','=','item.proposal_id')
            ->whereIn('note.id',$access->visibleNoteIds($user))->whereIn('item.id',clone $ids);
        $this->packages=DB::table('proposals as proposal')->where(function(Builder $q)use($user,$ids): void {
            $q->whereIn('proposal.id',DB::table('proposal_items')->select('proposal_id')->whereIn('id',clone $ids));
            // Empty own drafts are still packages. Cross-SKPD and assigned access require a visible item.
            if ($user->hasPermission('monitoring.own') && $user->hasPermission('proposals.view_own') && $user->organization_id!==null) {
                $q->orWhere('proposal.organization_id',$user->organization_id);
            }
            if ($user->hasPermission('monitoring.all') && $user->hasPermission('proposals.view_all')) { $q->orWhere('proposal.status','!=','draft'); }
        });
        $this->kkr=DB::table('kkr_documents as document')->whereIn('document.id',$access->visibleKkrIds($user))
            ->whereExists(fn($q)=>$q->selectRaw('1')->from('kkr_versions as v')->join('kkr_entries as e','e.kkr_version_id','=','v.id')
                ->join('item_reviews as r','r.id','=','e.item_review_id')->whereColumn('v.kkr_document_id','document.id')->whereIn('r.proposal_item_id',clone $ids));
        $this->canReview=$user->hasPermission('monitoring.assigned') && $user->hasPermission('proposals.view_assigned')
            && ($user->hasRole('examiner') || $user->hasRole('team_lead') || $user->hasRole('technical_controller'));
        $this->canKkr=$this->canReview && $user->hasPermission('kkr.view');
        if (!$this->canReview) { $this->reviews->whereRaw('1 = 0'); }
        if (!$this->canKkr) { $this->kkr->whereRaw('1 = 0'); }
    }

    public function page(array $filters): array
    {
        $view=$filters['view']??'items';$status=$filters['status']??'all';$metric=$filters['metric']??null;
        abort_if($view==='kkr' && !$this->canKkr,403);
        abort_if($metric!==null && (!$this->canReview || $view!=='items'),403);
        $groups=[
            'packages'=>$this->counts($this->packages,'proposal.status'),
            'items'=>$this->counts($this->items,'item.status'),
            'notes'=>$this->counts($this->notes,'note.status'),
        ];
        if ($this->canKkr) { $groups['kkr']=$this->counts($this->kkr,'document.status'); }
        $completed=$this->completed();$compliant=$this->compliant();
        $metrics=$this->canReview?['completed'=>(clone $completed)->count(),'compliant'=>(clone $compliant)->count()]:[];
        $openNotes=(clone $this->notes)->where('note.status','!=','resolved')->count();
        $query=match($view){'packages'=>clone $this->packages,'notes'=>clone $this->notes,'kkr'=>clone $this->kkr,default=>clone $this->items};
        $column=match($view){'packages'=>'proposal.status','notes'=>'note.status','kkr'=>'document.status',default=>'item.status'};
        if ($status==='open' && $view==='notes') { $query->where('note.status','!=','resolved'); }
        elseif ($status!=='all') { $query->where($column,$status); }
        if ($metric!==null) { $query->whereIn('item.id',($metric==='completed'?$completed:$compliant)->select('review.proposal_item_id')); }
        if ($view==='packages') {
            // Count only visible items, even when an assigned actor can open the parent package.
            $query->select('proposal.id','proposal.number','proposal.title','proposal.status','proposal.type')->selectSub(DB::table('proposal_items as counted')->selectRaw('COUNT(*)')->whereColumn('counted.proposal_id','proposal.id')
                ->whereIn('counted.id',app(ResourceAccess::class)->monitoredItemIds($this->user)),'item_count');
            $query->orderByDesc('proposal.id');
        } elseif ($view==='notes') {
            $query->select('note.id','note.category','note.status','version.description as title','proposal.number')->orderByDesc('note.id');
        } elseif ($view==='kkr') {
            $query->select('document.id','document.number','document.title','document.status')->orderByDesc('document.id');
        } else {
            $latestVersion=DB::table('proposal_item_versions as v')->select('v.description')->whereColumn('v.proposal_item_id','item.id');
            // A user with own+assigned roles may read drafts only for their own organization.
            $latestVersion->where(fn($q)=>$q->whereNotNull('v.submitted_at')->orWhere(function($q): void {
                if($this->user->hasPermission('proposals.view_own') && $this->user->organization_id!==null){$q->where('proposal.organization_id',$this->user->organization_id);}else{$q->whereRaw('1 = 0');}
            }));
            $latestVersion->orderByDesc('v.version_number')->limit(1);
            $query->select('item.id','item.proposal_id','item.status','proposal.number')->selectSub(clone $latestVersion,'title')
                ->selectSub((clone $latestVersion)->select('v.id'),'version_id')->orderByDesc('item.id');
            if ($this->canReview) {
                $query->selectSub((clone $this->reviews)->whereColumn('review.proposal_item_id','item.id')->select('review.id')->limit(1),'review_id');
            }
        }
        $rows=$query->paginate(20)->withQueryString();
        return compact('view','status','metric','groups','metrics','openNotes','rows')+['canReview'=>$this->canReview,'canKkr'=>$this->canKkr];
    }

    private function counts(Builder $query,string $column): array {return (clone $query)->selectRaw($column.' AS status, COUNT(*) AS total')->groupBy($column)->pluck('total','status')->map(fn($n)=>(int)$n)->all();}
    private function completed(): Builder { return (clone $this->reviews)->where('review.status','completed'); }
    private function compliant(): Builder
    {
        return $this->completed()->whereExists(fn($q)=>$q->selectRaw('1')->from('review_results as result')->whereColumn('result.item_review_id','review.id')->where('result.result','compliant'))
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('review_results as result')->whereColumn('result.item_review_id','review.id')
                ->where(fn($bad)=>$bad->whereNotIn('result.result',['compliant','not_applicable'])->orWhere(fn($na)=>$na->where('result.result','not_applicable')->whereRaw("TRIM(COALESCE(result.reason, '')) = ''"))));
    }
}
