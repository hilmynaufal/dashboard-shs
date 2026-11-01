<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $this->denied($request, 'Silakan masuk terlebih dahulu.');
        }
        $user = $request->user()->refresh();
        $fingerprint = hash('sha256', $user->getAuthPassword());
        if (! $user->isEnabled() || ($request->session()->has('auth_password_fingerprint') && ! hash_equals($request->session()->get('auth_password_fingerprint'), $fingerprint))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->denied($request, 'Sesi tidak dapat digunakan. Silakan masuk kembali atau hubungi pengelola akun.');
        }
        $request->session()->put('auth_password_fingerprint', $fingerprint);

        return $next($request)->header('Cache-Control', 'no-store, private');
    }

    private function denied(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->is('auth/*')) {
            return response()->json(['message' => $message], 401)->header('Cache-Control', 'no-store');
        }

        return redirect()->route('login')->withErrors(['session' => $message])->header('Cache-Control', 'no-store');
    }
}
