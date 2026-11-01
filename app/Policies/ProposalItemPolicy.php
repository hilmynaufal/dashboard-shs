<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{ProposalItem, User};

class ProposalItemPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, ProposalItem $item): bool
    {
        return $this->access->item($user, $item);
    }
}
