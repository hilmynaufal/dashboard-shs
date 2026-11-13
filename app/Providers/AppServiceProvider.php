<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Authorization\RolePermissions;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use App\Models\SubmissionPeriod;

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
                Limit::perMinute(5)->by('identity:'.$request->ip().'|'.hash('sha256', $identity))->response(fn () => $this->loginLimitResponse($request)),
                Limit::perMinute(30)->by('address:'.$request->ip())->response(fn () => $this->loginLimitResponse($request)),
            ];
        });
        foreach (array_unique(array_merge(...array_values(RolePermissions::GRANTS))) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
        View::composer('layouts.workspace', function (\Illuminate\View\View $view): void {
            $user = auth()->user();
            $now = now();
            $period = SubmissionPeriod::query()->where('status', 'open')->where('opens_at', '<=', $now)
                ->where('closes_at', '>=', $now)->orderBy('closes_at')->first();
            $roles = DB::table('roles')->join('role_user', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)->orderBy('roles.id')->pluck('roles.code')->all();
            $view->with([
                'shellOrganization' => $user->organization_id
                    ? DB::table('organizations')->where('id', $user->organization_id)->value('name')
                    : 'Inspektorat Daerah',
                'shellRoles' => implode(' · ', array_intersect_key(RolePermissions::ROLES, array_flip($roles))),
                'shellPeriod' => $period,
                'shellPeriodDays' => $period ? (int) $now->diffInDays($period->closes_at) : 0,
            ]);
        });
    }

    private function loginLimitResponse(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $message = 'Terlalu banyak percobaan masuk. Tunggu satu menit, lalu coba kembali.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 429)->header('Retry-After', '60')
            : response()->view('auth.login', ['loginLimitMessage' => $message], 429)->header('Retry-After', '60')->header('Cache-Control', 'no-store');
    }

}
