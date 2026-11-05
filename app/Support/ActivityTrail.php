<?php

namespace App\Support;

use App\Authorization\ResourceAccess;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Project only recognized event metadata. Raw before/after audit payloads never leave this service. */
final class ActivityTrail
{
    public const TECHNICAL=[
        'account.initial_admin_created'=>'Admin awal dibuat','account.created'=>'Akun dibuat','account.access_updated'=>'Akses akun diubah',
        'account.status_updated'=>'Status akun diubah','account.profile_updated'=>'Profil diperbarui','account.password_changed'=>'Kata sandi diubah',
        'period.created'=>'Periode dibuat','period.updated'=>'Periode diubah','reference.created'=>'Referensi dibuat','reference.updated'=>'Referensi diubah','reference.deleted'=>'Referensi dihapus',
    ];
    public const ASSIGNED=[
        'proposal.submitted'=>'Usulan dikirim','proposal.item_saved'=>'Versi item disimpan','proposal.item_revision_submitted'=>'Revisi item dikirim',
        'verification.decided'=>'Keputusan kelengkapan','verification.resubmitted'=>'Kelengkapan diajukan ulang',
        'assignment.created'=>'Penugasan dibuat','assignment.items_allocated'=>'Item ditugaskan','assignment.transferred'=>'Pemeriksa dialihkan',
        'review.started'=>'Reviu dimulai','review.saved'=>'Hasil reviu disimpan','review.completed'=>'Reviu diselesaikan','review.note_added'=>'Catatan internal ditambahkan',
        'note.administrative_sent'=>'Catatan administrasi dikirim','note.sent'=>'Catatan resmi dikirim','note.responded'=>'Tanggapan dikirim','note.response_decided'=>'Tanggapan diperiksa',
        'kkr.version_prepared'=>'Versi KKR disiapkan','kkr.final_correction_prepared'=>'Koreksi KKR final disiapkan','kkr.submitted'=>'KKR diajukan',
        'kkr.supervision_decided'=>'Keputusan supervisi','kkr.finalized'=>'KKR difinalisasi','kkr.word_archived'=>'Word final diarsipkan','kkr.word_downloaded'=>'Word diunduh',
    ];
    public const TYPES=['user'=>'Akun','submission_period'=>'Periode','item_classifications'=>'Kode barang','account_codes'=>'Rekening','units'=>'Satuan',
        'proposal'=>'Paket usulan','proposal_item_version'=>'Versi item','verification_result'=>'Pemeriksaan kelengkapan','review_assignment'=>'Penugasan',
        'assignment_transfer'=>'Pengalihan pemeriksa','item_review'=>'Reviu item','review_note'=>'Catatan','note_response'=>'Tanggapan','kkr_version'=>'Versi KKR'];

    public static function actions(User $user): array
    {
        return ($user->hasPermission('audit.technical')?self::TECHNICAL:[])+($user->hasPermission('audit.assigned') && $user->hasPermission('proposals.view_assigned')?self::ASSIGNED:[]);
    }

    public static function query(User $user,ResourceAccess $access): Builder
    {
        $query=DB::table('activity_logs as log')->leftJoin('users as actor','actor.id','=','log.actor_id');
        $query->where(function(Builder $q)use($user,$access): void {
            $q->whereRaw('1 = 0');
            if($user->hasPermission('audit.technical')){
                self::branch($q,'user',array_filter(array_keys(self::TECHNICAL),fn($a)=>str_starts_with($a,'account.')));
                self::branch($q,'submission_period',['period.created','period.updated']);
                foreach(['item_classifications','account_codes','units'] as $type){self::branch($q,$type,['reference.created','reference.updated','reference.deleted']);}
            }
            if(!$user->hasPermission('audit.assigned') || !$user->hasPermission('proposals.view_assigned')){return;}
            // Assigned action must not inherit broader own/all read scope from an unrelated role.
            $items=$access->assignedItemIds($user);
            $versions=DB::table('proposal_item_versions')->select('id')->whereNotNull('submitted_at')->whereIn('proposal_item_id',clone $items);
            $reviews=$access->visibleReviewIds($user);
            self::branch($q,'proposal',['proposal.submitted'],DB::table('proposal_items')->select('proposal_id')->whereIn('id',clone $items));
            self::branch($q,'proposal_item_version',['proposal.item_saved','proposal.item_revision_submitted','verification.resubmitted'],clone $versions);
            self::branch($q,'verification_result',['verification.decided'],DB::table('verification_results')->select('id')->whereIn('item_version_id',clone $versions));
            $assignments=$access->assignedAssignmentIds($user);
            self::branch($q,'review_assignment',['assignment.created','assignment.items_allocated'],$assignments);
            self::branch($q,'assignment_transfer',['assignment.transferred'],DB::table('assignment_transfers')->select('id')->whereIn('assignment_item_id',$access->assignedScopeIds($user)));
            self::branch($q,'item_review',['review.started','review.saved','review.completed','review.note_added'],$reviews);
            $notes=$access->visibleNoteIds($user)->whereIn('i.id',clone $items);
            self::branch($q,'review_note',['note.administrative_sent','note.sent'],clone $notes);
            self::branch($q,'note_response',['note.responded','note.response_decided'],DB::table('note_responses')->select('id')->whereIn('review_note_id',$notes));
            self::branch($q,'kkr_version',array_filter(array_keys(self::ASSIGNED),fn($a)=>str_starts_with($a,'kkr.')),
                DB::table('kkr_versions')->select('id')->whereIn('kkr_document_id',$access->visibleKkrIds($user)));
        });
        return $query->select('log.id','log.actor_id','actor.name as actor_name','log.action','log.subject_type','log.subject_id','log.occurred_at')
            ->selectRaw("CASE WHEN log.action IN ('kkr.supervision_decided','note.response_decided') THEN JSON_UNQUOTE(JSON_EXTRACT(log.after_values, '$.decision')) ELSE NULL END AS decision")
            ->selectSub(DB::table('verification_results as result')->select('result.result')->whereColumn('result.id','log.subject_id')->where('log.subject_type','verification_result')->limit(1),'verification_result')
            ->orderByDesc('log.occurred_at')->orderByDesc('log.id');
    }

    private static function branch(Builder $query,string $type,array $actions,?Builder $ids=null): void
    {
        $query->orWhere(function(Builder $part)use($type,$actions,$ids): void {
            $part->where('log.subject_type',$type)->whereIn('log.action',array_values($actions));if($ids){$part->whereIn('log.subject_id',$ids);}
        });
    }

    public static function label(object $row): string
    {
        if($row->action==='kkr.supervision_decided'){return match($row->decision){'approved'=>'KKR disetujui dalam supervisi','returned'=>'KKR dikembalikan dalam supervisi',default=>'Keputusan supervisi'};}
        if($row->action==='verification.decided'){return $row->verification_result==='needs_completion'?'Item dikembalikan untuk dilengkapi':'Keputusan kelengkapan';}
        if($row->action==='note.response_decided'){return $row->decision==='needs_revision'?'Tanggapan dikembalikan untuk diperbaiki':'Tanggapan diperiksa';}
        return (self::TECHNICAL+self::ASSIGNED)[$row->action];
    }
}
