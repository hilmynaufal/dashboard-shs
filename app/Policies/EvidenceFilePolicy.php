<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{EvidenceFile, User};

class EvidenceFilePolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, EvidenceFile $file): bool
    {
        return $this->access->evidence($user, $file);
    }

    public function download(User $user, EvidenceFile $file): bool
    {
        return $this->access->evidence($user, $file);
    }
}
