<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Authorization\RolePermissions;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $identity = is_string($email) ? mb_strtolower(trim($email)) : '';

            return [
                Limit::perMinute(5)->by('identity:'.$request->ip().'|'.hash('sha256', $identity)),
                Limit::perMinute(30)->by('address:'.$request->ip()),
            ];
        });
        foreach (array_unique(array_merge(...array_values(RolePermissions::GRANTS))) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
    }

}
