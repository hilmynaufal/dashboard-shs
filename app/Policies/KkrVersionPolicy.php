<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{KkrDocument, KkrVersion, User};

class KkrVersionPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, KkrVersion $version): bool
    {
        return $this->access->kkr($user, KkrDocument::findOrFail($version->kkr_document_id));
    }

    public function approveLead(User $user, KkrVersion $version): bool
    {
        return $this->access->approve($user, $version, 'team_lead');
    }

    public function approveTechnical(User $user, KkrVersion $version): bool
    {
        return $this->access->approve($user, $version, 'technical_controller');
    }
}
