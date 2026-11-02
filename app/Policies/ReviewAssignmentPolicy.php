<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{ReviewAssignment, User};

class ReviewAssignmentPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function create(User $user): bool
    {
        return $user->hasPermission('assignments.manage') && $user->hasRole('team_lead')
            && $user->hasPermission('proposals.view_assigned');
    }

    public function view(User $user, ReviewAssignment $assignment): bool
    {
        if ($assignment->status === 'draft' && $assignment->created_by === $user->id && $this->create($user)) {
            return true;
        }

        return $user->hasPermission('proposals.view_assigned') && (
            $this->access->membership($user, $assignment->id, 'examiner') ||
            $this->access->membership($user, $assignment->id, 'team_lead') ||
            $this->access->membership($user, $assignment->id, 'technical_controller'));
    }

    public function manage(User $user, ReviewAssignment $assignment): bool
    {
        return $this->create($user) && $assignment->status !== 'completed' && (
            $this->access->membership($user, $assignment->id, 'team_lead') ||
            ($assignment->status === 'draft' && $assignment->created_by === $user->id));
    }
}
