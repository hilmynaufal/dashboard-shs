<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{Proposal, User};

class ProposalPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, Proposal $proposal): bool
    {
        return $this->access->proposal($user, $proposal);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('proposals.create') && $user->organization_id !== null;
    }

    public function update(User $user, Proposal $proposal): bool
    {
        return $user->hasPermission('proposals.update_own') && $this->access->owns($user, $proposal->organization_id) &&
            $proposal->status === 'draft';
    }

    public function submit(User $user, Proposal $proposal): bool
    {
        return $user->hasPermission('proposals.submit_own') && $this->access->owns($user, $proposal->organization_id) && $proposal->status === 'draft';
    }
}
