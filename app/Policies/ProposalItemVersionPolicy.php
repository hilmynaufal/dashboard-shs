<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{ProposalItemVersion, User};

class ProposalItemVersionPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, ProposalItemVersion $version): bool
    {
        return $this->access->version($user, $version);
    }
}
