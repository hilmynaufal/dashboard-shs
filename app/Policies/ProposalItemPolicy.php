<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{Proposal, ProposalItem, User};

class ProposalItemPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, ProposalItem $item): bool
    {
        return $this->access->item($user, $item);
    }

    public function update(User $user, ProposalItem $item): bool
    {
        return $user->hasPermission('proposals.update_own') && $this->editableByOwner($user, $item);
    }

    public function resubmit(User $user, ProposalItem $item): bool
    {
        return $user->hasPermission('proposals.submit_own') && $item->status === 'needs_completion'
            && $this->editableByOwner($user, $item);
    }

    public function viewVerification(User $user, ProposalItem $item): bool
    {
        return $user->hasPermission('verification.perform') && $user->hasPermission('proposals.view_all')
            && $this->access->item($user, $item);
    }

    public function verify(User $user, ProposalItem $item): bool
    {
        $proposal = Proposal::findOrFail($item->proposal_id);

        return $this->viewVerification($user, $item) && $item->status === 'submitted'
            && in_array($proposal->status, ['submitted', 'verification', 'review'], true);
    }

    private function editableByOwner(User $user, ProposalItem $item): bool
    {
        $proposal = Proposal::findOrFail($item->proposal_id);

        return $this->access->owns($user, $proposal->organization_id) && (
            ($proposal->status === 'draft' && $item->status === 'draft') ||
            (in_array($proposal->status, ['submitted', 'verification', 'review'], true) && $item->status === 'needs_completion')
        );
    }
}
