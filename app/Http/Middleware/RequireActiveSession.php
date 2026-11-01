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
            return response()->json(['message' => 'Silakan masuk terlebih dahulu.'], 401);
        }
        if (! $request->user()->refresh()->isEnabled()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Sesi tidak dapat digunakan. Silakan hubungi pengelola akun.'], 401);
        }

        return $next($request);
    }
}
