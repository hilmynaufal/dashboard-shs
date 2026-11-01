<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Support\AccountValidation;

class SessionController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isEnabled()) {
            return redirect()->route('workspace');
        }
        if ($request->user()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return view('auth.login');
    }

    public function csrfToken(): JsonResponse
    {
        return response()->json(['csrf_token' => csrf_token()])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $input = $request->validate(['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:4096']], AccountValidation::MESSAGES, AccountValidation::ATTRIBUTES);
        if ($request->user()) {
            return response()->json(['message' => 'Keluar dari sesi saat ini sebelum masuk kembali.'], 409);
        }
        if (! Auth::attempt(['email' => mb_strtolower(trim($input['email'])), 'password' => $input['password'], 'is_active' => true])) {
            return $this->rejected($request);
        }
        if (! $request->user()->isEnabled()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->rejected($request);
        }
        $request->session()->regenerate();
        $request->session()->put('auth_password_fingerprint', hash('sha256', $request->user()->getAuthPassword()));

        return $request->expectsJson() ? $this->show($request) : redirect()->route('workspace');
    }

    private function rejected(Request $request): JsonResponse
    {
        $message = 'Informasi masuk tidak sesuai atau akun tidak aktif.';
        if (! $request->expectsJson()) {
            throw ValidationException::withMessages(['email' => $message]);
        }

        return response()->json(['message' => $message], 401);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'organization_id' => $user->organization_id], 'permissions' => $user->permissionCodes()])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['message' => 'Berhasil keluar.'])->header('Cache-Control', 'no-store')
            : redirect()->route('login')->with('status', 'Anda berhasil keluar.');
    }
}
