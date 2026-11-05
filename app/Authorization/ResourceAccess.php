<?php

namespace App\Authorization;

use App\Models\{EvidenceFile, ItemReview, KkrDocument, KkrVersion, Proposal, ProposalItem, ProposalItemVersion, ReviewAssignment, ReviewNote, User};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ResourceAccess
{
    /** Use this subquery for lists, counts and exports; a package grant is not a grant to every item. */
    public function visibleItemIds(User $user): Builder
    {
        return DB::table('proposal_items as item')->join('proposals as proposal', 'proposal.id', '=', 'item.proposal_id')
            ->select('item.id')->where(function (Builder $query) use ($user): void {
                $query->whereRaw('1 = 0');
                if ($user->hasPermission('proposals.view_own') && $user->organization_id !== null) {
                    $query->orWhere('proposal.organization_id', $user->organization_id);
                }
                if ($user->hasPermission('proposals.view_all')) {
                    $query->orWhere('proposal.status', '!=', 'draft');
                }
                if ($user->hasPermission('proposals.view_assigned')) {
                    $query->orWhere(function (Builder $assigned) use ($user): void {
                        $assigned->where('proposal.status', '!=', 'draft')->whereIn('item.id',
                            $this->scopeQuery($user)->select('scope.proposal_item_id'));
                    });
                }
            });
    }

    public function owns(User $user, int $organizationId): bool
    {
        return $user->isEnabled() && $user->organization_id !== null && (int) $user->organization_id === $organizationId;
    }

    /** Monitoring action and resource scope must both be granted, in the same scope. */
    public function monitoredItemIds(User $user): Builder
    {
        return DB::table('proposal_items as item')->join('proposals as proposal','proposal.id','=','item.proposal_id')
            ->whereIn('item.id',$this->visibleItemIds($user))->select('item.id')->where(function(Builder $q)use($user): void {
                $q->whereRaw('1 = 0');
                if ($user->hasPermission('monitoring.own') && $user->hasPermission('proposals.view_own') && $user->organization_id!==null) {
                    $q->orWhere('proposal.organization_id',$user->organization_id);
                }
                if ($user->hasPermission('monitoring.all') && $user->hasPermission('proposals.view_all')) { $q->orWhere('proposal.status','!=','draft'); }
                if ($user->hasPermission('monitoring.assigned') && $user->hasPermission('proposals.view_assigned')) {
                    $q->orWhere(fn($part)=>$part->where('proposal.status','!=','draft')->whereIn('item.id',$this->scopeQuery($user)->select('scope.proposal_item_id')));
                }
            });
    }

    public function visibleReviewIds(User $user): Builder
    {
        $query=DB::table('item_reviews as review')->join('proposal_item_versions as reviewed','reviewed.id','=','review.item_version_id')
            ->select('review.id')->whereNotNull('reviewed.submitted_at');
        if (!$user->hasPermission('proposals.view_assigned')) { return $query->whereRaw('1 = 0'); }
        return $query->whereIn('review.assignment_item_id',$this->scopeQuery($user)->select('scope.id'));
    }

    private function scopeQuery(User $user): Builder
    {
        $roles = array_values(array_filter(['examiner', 'team_lead', 'technical_controller'], fn ($role) => $user->hasRole($role)));

        return DB::table('assignment_items as scope')
            ->join('review_assignments as assignment', 'assignment.id', '=', 'scope.review_assignment_id')
            ->join('assignment_members as actor', 'actor.review_assignment_id', '=', 'assignment.id')
            ->where('actor.user_id', $user->id)->where('actor.is_active', true)
            ->whereIn('actor.role', $roles)->whereIn('assignment.status', ['active', 'completed'])
            ->where(function (Builder $q): void {
                $q->whereIn('actor.role', ['team_lead', 'technical_controller'])
                    ->orWhereColumn('scope.examiner_member_id', 'actor.id');
            });
    }

    public function membership(User $user, int $assignmentId, string $role): bool
    {
        return $user->hasRole($role) && DB::table('assignment_members')
            ->where('review_assignment_id', $assignmentId)->where('user_id', $user->id)
            ->where('role', $role)->where('is_active', true)->exists();
    }

    public function proposal(User $user, Proposal $proposal): bool
    {
        if ($user->hasPermission('proposals.view_own') && $this->owns($user, $proposal->organization_id)) {
            return true;
        }
        if ($proposal->status === 'draft') {
            return false;
        }
        if ($user->hasPermission('proposals.view_all')) {
            return true;
        }

        return $user->hasPermission('proposals.view_assigned') && $this->scopeQuery($user)
            ->join('proposal_items as item', 'item.id', '=', 'scope.proposal_item_id')
            ->where('item.proposal_id', $proposal->id)->exists();
    }

    public function item(User $user, ProposalItem $item): bool
    {
        $proposal = Proposal::findOrFail($item->proposal_id);
        if (($user->hasPermission('proposals.view_own') && $this->owns($user, $proposal->organization_id)) ||
            ($user->hasPermission('proposals.view_all') && $proposal->status !== 'draft')) {
            return true;
        }

        return $proposal->status !== 'draft' && $user->hasPermission('proposals.view_assigned') &&
            $this->scopeQuery($user)->where('scope.proposal_item_id', $item->id)->exists();
    }

    public function version(User $user, ProposalItemVersion $version): bool
    {
        $item = ProposalItem::findOrFail($version->proposal_item_id);
        $proposal = Proposal::findOrFail($item->proposal_id);
        if ($user->hasPermission('proposals.view_own') && $this->owns($user, $proposal->organization_id)) {
            return true;
        }

        return $version->submitted_at !== null && $this->item($user, $item);
    }

    public function evidence(User $user, EvidenceFile $file): bool
    {
        if ($user->hasPermission('proposals.view_own') && $this->owns($user, $file->organization_id)) {
            return true;
        }
        $versions = DB::table('item_version_evidence as link')
            ->join('proposal_item_versions as version', 'version.id', '=', 'link.item_version_id')
            ->join('proposal_items as item', 'item.id', '=', 'version.proposal_item_id')
            ->join('proposals as proposal', 'proposal.id', '=', 'item.proposal_id')
            ->where('link.evidence_file_id', $file->id)->whereNotNull('version.submitted_at')
            ->where('proposal.status', '!=', 'draft')->where('proposal.organization_id', $file->organization_id);
        if ($user->hasPermission('proposals.view_all')) {
            return $versions->exists();
        }
        if (! $user->hasPermission('proposals.view_assigned')) {
            return false;
        }
        $scope = $this->scopeQuery($user)->select('scope.proposal_item_id');

        return $versions->whereIn('item.id', $scope)->exists();
    }

    public function review(User $user, ItemReview $review): bool
    {
        $version = ProposalItemVersion::find($review->item_version_id);

        return $version !== null && $version->submitted_at !== null &&
            $user->hasPermission('proposals.view_assigned') && $this->scopeQuery($user)
            ->where('scope.id', $review->assignment_item_id)->exists();
    }

    public function writeReview(User $user, ItemReview $review): bool
    {
        return $this->review($user, $review) && $user->hasPermission('reviews.write') && $review->status !== 'completed' &&
            $this->scopeQuery($user)->where('scope.id', $review->assignment_item_id)
                ->where('assignment.status', 'active')->where('actor.role', 'examiner')->exists();
    }

    public function visibleNoteIds(User $user): Builder
    {
        $query = DB::table('review_notes as n')->join('proposal_item_versions as v', 'v.id', '=', 'n.item_version_id')
            ->join('proposal_items as i', 'i.id', '=', 'v.proposal_item_id')->join('proposals as p', 'p.id', '=', 'i.proposal_id')
            ->leftJoin('item_reviews as r', 'r.id', '=', 'n.item_review_id')->select('n.id')
            ->where(function (Builder $q): void {
                $q->where('n.type', 'administrative')->orWhere(fn ($q) => $q->where('n.type', 'substantive')->whereColumn('r.item_version_id', 'n.item_version_id'));
            });
        if ($user->hasPermission('notes.respond_own')) {
            return $query->where('p.organization_id', $user->organization_id ?? 0)->whereIn('n.status', ['sent', 'responded', 'resolved', 'open_at_final']);
        }
        return $query->whereNotNull('v.submitted_at')->where('p.status', '!=', 'draft')->where(function (Builder $q) use ($user): void {
            $q->whereRaw('1 = 0');
            if ($user->hasPermission('notes.administrative') && $user->hasPermission('proposals.view_all')) {
                $q->orWhere('n.type', 'administrative');
            }
            if ($user->hasPermission('proposals.view_assigned')) {
                $q->orWhere(function (Builder $assigned) use ($user): void {
                    $assigned->whereIn('i.id', $this->scopeQuery($user)->select('scope.proposal_item_id'))
                        ->where(function (Builder $kind) use ($user): void {
                            $kind->where('n.type', 'administrative')->orWhereIn('r.assignment_item_id', $this->scopeQuery($user)->select('scope.id'));
                        });
                });
            }
        });
    }

    public function note(User $user, ReviewNote $note): bool
    {
        return $this->visibleNoteIds($user)->where('n.id', $note->id)->exists();
    }

    public function assignment(User $user, ReviewAssignment $assignment, string $role): bool
    {
        return $this->membership($user, $assignment->id, $role);
    }

    public function visibleKkrIds(User $user): Builder
    {
        $query = DB::table('kkr_documents as d')->select('d.id');
        if (!$user->hasPermission('kkr.view')) { return $query->whereRaw('1 = 0'); }
        return $query->where(function (Builder $q) use ($user): void {
            $q->whereRaw('1 = 0');
            foreach (['team_lead','technical_controller','examiner'] as $role) {
                if (!$user->hasRole($role)) { continue; }
                $q->orWhere(function (Builder $part) use ($user, $role): void {
                    $part->whereExists(fn ($member) => $member->selectRaw('1')->from('assignment_members as actor')
                        ->whereColumn('actor.review_assignment_id','d.review_assignment_id')->where('actor.user_id',$user->id)->where('actor.role',$role)->where('actor.is_active',true));
                    if ($role === 'examiner') {
                        $part->where('d.prepared_by',$user->id)->whereNotExists(function ($entries) use ($user): void {
                            $entries->selectRaw('1')->from('kkr_entries as entry')->join('kkr_versions as version','version.id','=','entry.kkr_version_id')
                                ->join('item_reviews as review','review.id','=','entry.item_review_id')->join('assignment_items as scope','scope.id','=','review.assignment_item_id')
                                ->join('assignment_members as current','current.id','=','scope.examiner_member_id')
                                ->whereColumn('version.kkr_document_id','d.id')->where(fn($bad)=>$bad->where('current.user_id','!=',$user->id)->orWhere('current.is_active',false)
                                    ->orWhere('current.role','!=','examiner')->orWhereColumn('scope.review_assignment_id','!=','d.review_assignment_id'));
                        });
                    }
                });
            }
        });
    }

    public function kkr(User $user, KkrDocument $document): bool
    {
        return $this->visibleKkrIds($user)->where('d.id',$document->id)->exists();
    }

    public function approve(User $user, KkrVersion $version, string $role): bool
    {
        $document = KkrDocument::findOrFail($version->kkr_document_id);
        $permission = $role === 'team_lead' ? 'kkr.approve_lead' : 'kkr.approve_technical';
        $status = $role === 'team_lead' ? 'awaiting_lead' : 'awaiting_controller';
        if (! $user->hasPermission($permission) || $document->prepared_by === $user->id ||
            $document->status !== $status || $version->submitted_at === null || $version->finalized_at !== null ||
            ! $this->membership($user, $document->review_assignment_id, $role) ||
            ! ReviewAssignment::whereKey($document->review_assignment_id)->where('status','active')->exists() ||
            DB::table('kkr_approvals')->where('kkr_version_id',$version->id)->where('level',$role)->exists() ||
            (int) $version->version_number !== (int) KkrVersion::where('kkr_document_id', $document->id)->max('version_number')) {
            return false;
        }
        if ($role === 'technical_controller') {
            return DB::table('kkr_approvals')->where('kkr_version_id', $version->id)
                ->where('level', 'team_lead')->where('decision', 'approved')->where('decided_by','!=',$user->id)->exists();
        }

        return true;
    }
}
