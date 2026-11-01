<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\{AccountActivity, AccountValidation};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('accounts.profile');
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'position' => ['nullable', 'string', 'max:255'],
            'email' => ['prohibited'], 'organization_id' => ['prohibited'], 'is_active' => ['prohibited'],
            'role' => ['prohibited'], 'roles' => ['prohibited'], 'password' => ['prohibited'],
        ], AccountValidation::MESSAGES, AccountValidation::ATTRIBUTES);
        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $before = $user->only(['name', 'position']);
            $user->name = $data['name'];
            $user->position = $data['position'] ?? null;
            $user->save();
            AccountActivity::record($user->id, 'account.profile_updated', $user->id, $before, $user->only(['name', 'position']));
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Profil berhasil diperbarui.'])
            : back()->with('status', 'Profil berhasil diperbarui.');
    }

    public function password(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => AccountValidation::passwordRules(),
        ], AccountValidation::MESSAGES, AccountValidation::ATTRIBUTES);
        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            // Recheck inside the lock so a concurrent password change cannot use stale credentials.
            abort_unless(\Illuminate\Support\Facades\Hash::check($data['current_password'], $user->password), 422, 'Kata sandi saat ini tidak sesuai.');
            $user->password = $data['password'];
            $user->remember_token = null;
            $user->save();
            $request->session()->put('auth_password_fingerprint', hash('sha256', $user->getAuthPassword()));
            AccountActivity::record($user->id, 'account.password_changed', $user->id);
        });
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['message' => 'Kata sandi diperbarui. Sesi lain akan diminta masuk kembali.'])
            : back()->with('status', 'Kata sandi diperbarui. Sesi lain akan diminta masuk kembali.');
    }
}
