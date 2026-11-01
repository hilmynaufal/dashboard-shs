<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    protected $attributes = ['is_active' => true];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isEnabled(): bool
    {
        return $this->is_active && ($this->organization_id === null || DB::table('organizations')
            ->where('id', $this->organization_id)->where('is_active', true)->exists());
    }

    public function hasRole(string $code): bool
    {
        return $this->isEnabled() && DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->id)->where('roles.code', $code)->exists();
    }

    public function permissionCodes(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        return DB::table('role_user')->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('role_user.user_id', $this->id)->distinct()->orderBy('permissions.code')->pluck('permissions.code')->all();
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissionCodes(), true);
    }
}
