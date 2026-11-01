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
                Limit::perMinute(5)->by('identity:'.$request->ip().'|'.hash('sha256', $identity))->response(fn () => $this->loginLimitResponse($request)),
                Limit::perMinute(30)->by('address:'.$request->ip())->response(fn () => $this->loginLimitResponse($request)),
            ];
        });
        foreach (array_unique(array_merge(...array_values(RolePermissions::GRANTS))) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
    }

    private function loginLimitResponse(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $message = 'Terlalu banyak percobaan masuk. Tunggu satu menit, lalu coba kembali.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 429)->header('Retry-After', '60')
            : response()->view('auth.login', ['loginLimitMessage' => $message], 429)->header('Retry-After', '60')->header('Cache-Control', 'no-store');
    }

}
