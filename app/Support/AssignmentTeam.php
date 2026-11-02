<?php

namespace App\Support;

use App\Models\{ReviewAssignment, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssignmentTeam
{
    public static function candidates(string $role): Builder
    {
        return User::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhereIn('organization_id', DB::table('organizations')->select('id')->where('is_active', true)))
            ->whereIn('id', DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->select('role_user.user_id')->where('roles.code', $role))
            ->orderBy('name')->orderBy('id');
    }

    public static function add(ReviewAssignment $assignment, User $user, string $role): int
    {
        return DB::table('assignment_members')->insertGetId([
            'review_assignment_id' => $assignment->id, 'user_id' => $user->id, 'role' => $role,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Call under assignment lock; user row locks serialize against account/role edits. */
    public static function examiner(ReviewAssignment $assignment, int $memberId): object
    {
        $member = DB::table('assignment_members')->where('review_assignment_id', $assignment->id)
            ->where('id', $memberId)->where('role', 'examiner')->where('is_active', true)->first();
        $user = $member ? User::query()->lockForUpdate()->find($member->user_id) : null;
        if (!$user || !$user->isEnabled() || !$user->hasRole('examiner') || !$user->hasPermission('proposals.view_assigned')) {
            throw ValidationException::withMessages(['examiner_member_id' => 'Pilih pemeriksa aktif dari tim penugasan ini.']);
        }
        if (DB::table('assignment_members')->where('review_assignment_id', $assignment->id)->where('user_id', $user->id)
            ->whereIn('role', ['team_lead', 'technical_controller'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['examiner_member_id' => 'Pemeriksa tidak boleh merangkap supervisor pada penugasan yang sama.']);
        }

        return $member;
    }
}
