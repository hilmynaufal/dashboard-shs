<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{KkrDocument, User};

class KkrDocumentPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, KkrDocument $document): bool
    {
        return $this->access->kkr($user, $document);
    }

    public function download(User $user, KkrDocument $document): bool
    {
        return $user->hasPermission('kkr.download') && $this->access->kkr($user, $document);
    }
}
