<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function csrfToken(): JsonResponse
    {
        return response()->json(['csrf_token' => csrf_token()])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->validate(['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:4096']]);
        if ($request->user()) {
            return response()->json(['message' => 'Keluar dari sesi saat ini sebelum masuk kembali.'], 409);
        }
        if (! Auth::attempt(['email' => mb_strtolower(trim($input['email'])), 'password' => $input['password'], 'is_active' => true])) {
            return response()->json(['message' => 'Informasi masuk tidak sesuai atau akun tidak aktif.'], 401);
        }
        if (! $request->user()->isEnabled()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Informasi masuk tidak sesuai atau akun tidak aktif.'], 401);
        }
        $request->session()->regenerate();

        return $this->show($request);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'organization_id' => $user->organization_id], 'permissions' => $user->permissionCodes()])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Berhasil keluar.'])->header('Cache-Control', 'no-store');
    }
}
