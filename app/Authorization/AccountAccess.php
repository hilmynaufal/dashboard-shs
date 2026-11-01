<?php

namespace App\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AccountAccess
{
    public static function snapshot(User $user): array
    {
        return [
            'roles' => DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)->orderBy('roles.code')->pluck('roles.code')->all(),
            'organization_id' => $user->organization_id,
            'is_active' => $user->is_active,
        ];
    }

    public static function version(User $user, array $snapshot): string
    {
        // Optimistic concurrency marker, not a permission grant or authentication secret.
        return hash('sha256', json_encode([$user->id, $snapshot], JSON_THROW_ON_ERROR));
    }
}
