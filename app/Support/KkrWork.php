<?php

namespace App\Support;

use App\Models\{ItemReview, KkrDocument, KkrVersion, Proposal, ProposalItem, ReviewAssignment, User};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

final class KkrWork
{
    public static function hash(array $value): string
    {
        return hash('sha256', json_encode(self::canonical($value), JSON_THROW_ON_ERROR));
    }

    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) { return $value; }
        if (!array_is_list($value)) { ksort($value); }
        return array_map(self::canonical(...), $value);
    }

    public static function candidates(User $author, ReviewAssignment $assignment, ?KkrDocument $document=null)
    {
        $final=$document?self::latestFinal($document):null;
        $query=DB::table('item_reviews as review')->join('assignment_items as scope','scope.id','=','review.assignment_item_id')
            ->join('assignment_members as member','member.id','=','scope.examiner_member_id')->join('proposal_items as item','item.id','=','review.proposal_item_id')
            ->join('proposal_item_versions as version','version.id','=','review.item_version_id')->join('proposals as proposal','proposal.id','=','item.proposal_id')
            ->where('scope.review_assignment_id',$assignment->id)->where('member.review_assignment_id',$assignment->id)->where('member.role','examiner')
            ->where('member.user_id',$author->id)->where('member.is_active',true)->where('review.status','completed')->whereNotNull('version.submitted_at')
            ->where('proposal.organization_id',$assignment->object_organization_id)->where('proposal.submission_period_id',$assignment->submission_period_id)
            ->whereIn('proposal.status',['review','submitted','verification'])->where('review.id',function($q):void{
                $q->from('item_reviews as latest')->selectRaw('MAX(latest.id)')->whereColumn('latest.assignment_item_id','scope.id');
            })->whereNotExists(function($q)use($document):void{
                $q->from('kkr_entries as final_entry')->join('kkr_versions as final_version','final_version.id','=','final_entry.kkr_version_id')
                    ->join('item_reviews as final_review','final_review.id','=','final_entry.item_review_id')->whereNotNull('final_version.finalized_at')->whereColumn('final_review.proposal_item_id','item.id');
                if($document){$q->where('final_version.kkr_document_id','!=',$document->id);}
            })->select('review.id','review.lock_version','item.lock_version as item_lock','scope.id as scope_id','version.description','version.version_number','proposal.number')->orderBy('review.id');
        if($final){$query->whereIn('review.id',array_column($final->snapshot['source']['entries']??[],'review_id'));}
        return $query;
    }

    public static function createToken(User $author, ReviewAssignment $assignment): string
    {
        return RecordVersion::of([AssignmentWork::token($assignment),self::candidates($author,$assignment)->get()->all()]);
    }

    public static function token(KkrDocument $document): string
    {
        $latest = self::latest($document);
        return RecordVersion::of([$document->getAttributes(),$latest?->getAttributes(),
            DB::table('kkr_approvals')->where('kkr_version_id',$latest?->id)->orderBy('id')->get()->all(),
            AssignmentWork::token(ReviewAssignment::findOrFail($document->review_assignment_id))]);
    }

    public static function latest(KkrDocument $document): ?KkrVersion { return KkrVersion::where('kkr_document_id',$document->id)->orderByDesc('version_number')->first(); }
    public static function latestFinal(KkrDocument $document): ?KkrVersion { return KkrVersion::where('kkr_document_id',$document->id)->whereNotNull('finalized_at')->orderByDesc('version_number')->first(); }

    /** Serialize with review writes/transfers/notes, then owner revisions, then document. */
    public static function lockAssignment(User $actor, int $id): ReviewAssignment
    {
        $assignment=ReviewAssignment::query()->lockForUpdate()->findOrFail($id);
        Gate::forUser($actor->refresh())->authorize('view',$assignment);
        $ids=DB::table('assignment_items')->where('review_assignment_id',$id)->pluck('proposal_item_id');
        Proposal::whereIn('id',ProposalItem::whereIn('id',$ids)->select('proposal_id'))->orderBy('id')->lockForUpdate()->get();
        ProposalItem::whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
        return $assignment;
    }

    public static function lockDocument(User $actor, KkrDocument $document, string $token): array
    {
        $assignment=self::lockAssignment($actor,$document->review_assignment_id);
        $locked=KkrDocument::query()->lockForUpdate()->findOrFail($document->id);
        Gate::forUser($actor)->authorize('view',$locked);
        if (!hash_equals(self::token($locked),$token)) { self::stale('Dokumen atau keputusan supervisi berubah. Muat ulang KKR.'); }
        if ($assignment->status!=='active') { throw ValidationException::withMessages(['kkr'=>'Penugasan tidak lagi aktif. KKR hanya dapat dibaca.']); }
        return [$locked,$assignment];
    }

    public static function source(User $author, ReviewAssignment $assignment, array $reviewIds, ?KkrDocument $document=null): array
    {
        Gate::forUser($author)->authorize('create',[KkrDocument::class,$assignment]);
        $ids=array_map('intval',$reviewIds);sort($ids);
        if($document){
            abort_unless((int)$document->prepared_by===(int)$author->id && (int)$document->review_assignment_id===(int)$assignment->id,403);
            if($final=self::latestFinal($document)){
                Gate::forUser($author)->authorize('archives.assigned');
                if(!self::integrity($final)){throw ValidationException::withMessages(['kkr'=>'Sumber arsip koreksi tidak dapat digunakan.'])->status(409);}
                $fixed=array_map('intval',array_column($final->snapshot['source']['entries'],'review_id'));sort($fixed);
                if($ids!==$fixed){throw ValidationException::withMessages(['reviews'=>'Koreksi narasi mempertahankan seluruh item dari arsip final.']);}
            }
        }
        $available=self::candidates($author,$assignment,$document)->whereIn('review.id',$ids)->pluck('review.id')->map(fn($id)=>(int)$id)->all();sort($available);
        if (!$ids || $ids!==$available || count($ids)!==count(array_unique($ids))) { throw ValidationException::withMessages(['reviews'=>'Pilih hasil reviu selesai milik pemeriksa dalam penugasan ini yang belum masuk KKR final.']); }
        $organization=DB::table('organizations')->where('id',$assignment->object_organization_id)->first(['id','code','name']);
        $period=DB::table('submission_periods')->where('id',$assignment->submission_period_id)->first(['budget_year','name']);
        $team=DB::table('assignment_members as member')->join('users as user','user.id','=','member.user_id')->where('member.review_assignment_id',$assignment->id)
            ->where('member.is_active',true)->orderBy('member.id')->get(['member.role','user.id as user_id','user.name'])->map(fn($r)=>(array)$r)->all();
        $entries=[];
        foreach($ids as $id){
            $review=ItemReview::findOrFail($id);
            if (ReviewWork::problems($id)) { throw ValidationException::withMessages(['reviews'=>'Ada hasil reviu selesai yang aspek wajibnya belum lengkap. Periksa sumber sebelum menyusun KKR.']); }
            $item=ProposalItem::findOrFail($review->proposal_item_id);
            $notes=DB::table('review_notes as note')->join('proposal_item_versions as origin','origin.id','=','note.item_version_id')
                ->leftJoin('item_reviews as note_review','note_review.id','=','note.item_review_id')->where('origin.proposal_item_id',$item->id)->whereNotNull('origin.submitted_at')
                ->where(fn($q)=>$q->where('note.type','administrative')->orWhere(fn($q)=>$q->where('note.type','substantive')->whereColumn('note_review.item_version_id','note.item_version_id')->where('note_review.assignment_item_id',$review->assignment_item_id)))
                ->orderBy('note.id')->select('note.*')->get()->map(function($note):array{
                    $values=(array)$note;$values['created_by_name']=User::find($note->created_by)?->name;
                    $values['responses']=DB::table('note_responses as response')->leftJoin('note_response_decisions as decision','decision.note_response_id','=','response.id')
                        ->join('users as actor','actor.id','=','response.responded_by')->where('response.review_note_id',$note->id)->orderBy('response.id')
                        ->get(['response.id','response.response','response.revised_version_id','response.responded_at','actor.name as responded_by_name','decision.decision','decision.reason','decision.decided_at'])->map(fn($r)=>(array)$r)->all();
                    return $values;
                })->all();
            $results=ReviewWork::results($id)->map(fn($r)=>['aspect_id'=>$r->review_aspect_id,'criterion'=>$r->criterion,'result'=>$r->result,'reason'=>$r->reason,'recommendation'=>$r->recommendation])->all();
            $entries[]=['review_id'=>$id,'scope_id'=>$review->assignment_item_id,'item_id'=>$item->id,'item_state'=>['status'=>$item->status,'lock_version'=>$item->lock_version],
                'review'=>['status'=>$review->status,'reviewed_by'=>$review->reviewed_by,'reviewed_by_name'=>User::find($review->reviewed_by)?->name,'completed_at'=>$review->completed_at,'lock_version'=>$review->lock_version,
                    'comparison_price'=>$review->comparison_price,'recommended_price'=>$review->recommended_price,'recommendation'=>$review->recommendation,'results'=>$results],
                'reviewed_version'=>self::version($review->item_version_id),'latest_submitted_version'=>self::version(ItemVerification::latest($item->id,true)->id),
                'notes'=>$notes,'has_open_notes'=>count(array_filter($notes,fn($n)=>$n['status']!=='resolved'))>0];
        }
        return ['assignment'=>['id'=>$assignment->id,'number'=>$assignment->number,'title'=>$assignment->title,'task_basis'=>$assignment->task_basis,'scope_description'=>$assignment->scope_description,
            'organization'=>(array)$organization,'period'=>(array)$period,'team'=>$team],'entries'=>$entries];
    }

    private static function version(int $id): array
    {
        $version=DB::table('proposal_item_versions')->where('id',$id)->first();abort_unless($version && $version->submitted_at!==null,409);
        $value=(array)$version;$value['reference_snapshot']=json_decode($value['reference_snapshot'],true,512,JSON_THROW_ON_ERROR);
        $value['evidence']=DB::table('item_version_evidence')->join('evidence_files','evidence_files.id','=','evidence_file_id')->where('item_version_id',$id)->orderBy('evidence_files.id')
            ->get(['evidence_files.id','original_name','mime_type','byte_size','sha256'])->map(fn($r)=>(array)$r)->all();
        return $value;
    }

    public static function append(User $actor, KkrDocument $document, ReviewAssignment $assignment, array $data): KkrVersion
    {
        $source=self::source($actor,$assignment,$data['reviews'],$document);$previous=self::latest($document);
        $snapshot=['format'=>'supervision-v1','template'=>KkrPrint::TEMPLATE,'purpose'=>$data['purpose']??'Belum dicatat','work_steps'=>$data['work_steps']??'Belum dicatat','number'=>$document->number,'title'=>$data['title'],'conclusion'=>$data['conclusion'],'recommendation'=>$data['recommendation'],'source'=>$source,'source_hash'=>self::hash($source)];
        $json=json_encode($snapshot,JSON_THROW_ON_ERROR);
        if(strlen($json)>4*1024*1024){throw ValidationException::withMessages(['reviews'=>'Isi KKR terlalu besar. Bagi hasil reviu ke beberapa KKR.']);}
        $version=new KkrVersion;$version->kkr_document_id=$document->id;$version->version_number=($previous?->version_number??0)+1;$version->template_version=KkrPrint::TEMPLATE;
        $version->snapshot=$snapshot;$version->snapshot_sha256=self::hash($snapshot);$version->created_by=$actor->id;$version->supersedes_version_id=$previous?->id;$version->change_reason=$data['change_reason']??null;$version->save();
        foreach($source['entries'] as $position=>$entry){DB::table('kkr_entries')->insert(['kkr_version_id'=>$version->id,'item_review_id'=>$entry['review_id'],'display_order'=>$position+1,'has_open_notes'=>$entry['has_open_notes'],'snapshot'=>json_encode($entry,JSON_THROW_ON_ERROR)]);}
        $document->title=$data['title'];$document->status='draft';$document->lock_version++;$document->save();
        CatalogAudit::record($actor->id,'kkr.version_prepared','kkr_version',$version->id,null,['document_id'=>$document->id,'version_number'=>$version->version_number,'previous_version_id'=>$previous?->id]);
        return $version;
    }

    public static function integrity(KkrVersion $version): bool
    {
        $snapshot=$version->snapshot;
        if (($snapshot['format']??null)!=='supervision-v1' || !hash_equals($version->snapshot_sha256,self::hash($snapshot))) { return false; }
        $entries=DB::table('kkr_entries')->where('kkr_version_id',$version->id)->orderBy('display_order')->get();
        if($entries->isEmpty() || $entries->count()!==count($snapshot['source']['entries']??[])){return false;}
        foreach($entries as $i=>$row){$part=$snapshot['source']['entries'][$i];if((int)$row->item_review_id!==(int)$part['review_id'] || (bool)$row->has_open_notes!==(bool)$part['has_open_notes'] || !hash_equals(self::hash(json_decode($row->snapshot,true,512,JSON_THROW_ON_ERROR)),self::hash($part))){return false;}}
        return true;
    }

    public static function current(KkrDocument $document, KkrVersion $version): bool
    {
        try {
            $source=self::source(User::findOrFail($document->prepared_by),ReviewAssignment::findOrFail($document->review_assignment_id),array_column($version->snapshot['source']['entries']??[],'review_id'),$document);
            return self::integrity($version) && hash_equals($version->snapshot['source_hash']??'',self::hash($source));
        } catch(\Illuminate\Auth\Access\AuthorizationException|ValidationException $error){return false;}
    }

    public static function assertCurrent(KkrDocument $document, KkrVersion $version): void
    {
        if(!self::current($document,$version)){self::stale('Sumber hasil, catatan, item atau identitas tim berubah. Pemeriksa perlu menyiapkan versi baru sebelum disetujui.');}
    }

    public static function openCount(KkrVersion $version): int
    {
        return array_sum(array_map(fn($entry)=>count(array_filter($entry['notes'],fn($note)=>$note['status']!=='resolved')),$version->snapshot['source']['entries']??[]));
    }

    private static function stale(string $message): never { throw ValidationException::withMessages(['record_version'=>$message])->status(409); }
}
