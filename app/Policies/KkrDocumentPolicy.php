<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{KkrDocument, ReviewAssignment, User};

class KkrDocumentPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, KkrDocument $document): bool
    {
        return $this->access->kkr($user, $document);
    }

    public function create(User $user, ReviewAssignment $assignment): bool
    {
        return $assignment->status === 'active' && $user->hasPermission('kkr.draft') && $user->hasPermission('kkr.view')
            && $user->hasPermission('proposals.view_assigned') && $this->access->membership($user, $assignment->id, 'examiner');
    }

    public function update(User $user, KkrDocument $document): bool
    {
        return (int)$document->prepared_by === (int)$user->id && in_array($document->status, ['draft','returned'], true)
            && (!\App\Support\KkrWork::latestFinal($document) || $user->hasPermission('archives.assigned'))
            && $this->create($user, ReviewAssignment::findOrFail($document->review_assignment_id)) && $this->view($user, $document);
    }

    public function correct(User $user,KkrDocument $document): bool
    {
        return $document->status==='final' && (int)$document->prepared_by===(int)$user->id && $user->hasPermission('archives.assigned')
            && $this->create($user,ReviewAssignment::findOrFail($document->review_assignment_id)) && $this->view($user,$document)
            && \App\Support\KkrWork::latestFinal($document)!==null;
    }

    public function download(User $user, KkrDocument $document): bool
    {
        return $user->hasPermission('kkr.download') && $this->access->kkr($user, $document);
    }
}
