<?php

namespace App\Http\Controllers;

use App\Authorization\{AccountAccess, RolePermissions};
use App\Models\User;
use App\Support\{AccountActivity, AccountValidation};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class AccountAccessController extends Controller
{
    public function edit(User $account): View
    {
        Gate::authorize('access.manage');
        $access = AccountAccess::snapshot($account);

        return view('accounts.access', [
            'account' => $account, 'access' => $access, 'accessVersion' => AccountAccess::version($account, $access),
            'roles' => RolePermissions::ROLES,
            // Keep the existing inactive organization visible so it cannot be silently cleared.
            'organizations' => DB::table('organizations')->where(function ($query) use ($account): void {
                $query->where('is_active', true)->orWhere('id', $account->organization_id);
            })->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function update(Request $request, User $account): JsonResponse|RedirectResponse
    {
        Gate::authorize('access.manage');
        $roles = $request->input('roles');
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1', 'max:6'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(array_keys(RolePermissions::ROLES)), Rule::exists('roles', 'code')],
            'organization_id' => [Rule::requiredIf(is_array($roles) && in_array('skpd_operator', $roles, true)), 'nullable', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)],
            'access_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'name' => ['prohibited'], 'email' => ['prohibited'], 'password' => ['prohibited'],
            'is_active' => ['prohibited'], 'permissions' => ['prohibited'],
        ], AccountValidation::MESSAGES + [
            'roles.required' => 'Pilih sedikitnya satu peran.', 'roles.min' => 'Pilih sedikitnya satu peran.',
            'roles.array' => 'Peran harus berupa daftar pilihan.', 'roles.max' => 'Pilih hanya peran yang tersedia pada daftar resmi.',
            'roles.*.distinct' => 'Peran yang sama tidak boleh dipilih lebih dari sekali.',
            'roles.*.in' => 'Peran harus dipilih dari daftar resmi.',
            'access_version.required' => 'Muat ulang halaman sebelum menyimpan pengaturan akses.',
            'access_version.regex' => 'Penanda versi tidak sesuai. Muat ulang halaman.',
        ], AccountValidation::ATTRIBUTES + ['roles.*' => 'Peran', 'access_version' => 'Versi akses', 'permissions' => 'Izin tindakan']);
        if (in_array('skpd_operator', $data['roles'], true) && count($data['roles']) > 1) {
            throw ValidationException::withMessages(['roles' => 'Operator SKPD digunakan sebagai peran tersendiri agar akses organisasi tidak bercampur dengan akses lintas SKPD.']);
        }

        $access = DB::transaction(function () use ($request, $account, $data): array {
            // All account status/access mutations lock the same user rows in a stable order.
            $locked = User::query()->whereIn('id', [$request->user()->id, $account->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($locked[$request->user()->id]->hasPermission('access.manage'), 403);
            $target = $locked[$account->id];
            $before = AccountAccess::snapshot($target);
            if (! hash_equals(AccountAccess::version($target, $before), $data['access_version'])) {
                throw ValidationException::withMessages(['access_version' => 'Akses akun telah berubah sejak halaman dibuka. Muat ulang data sebelum menyimpan.'])->status(409);
            }
            if ($target->id === $request->user()->id && in_array('admin', $before['roles'], true) && ! in_array('admin', $data['roles'], true)) {
                throw ValidationException::withMessages(['roles' => 'Peran Admin pada akun sendiri tidak dapat dicabut. Gunakan Admin lain untuk mengubahnya.']);
            }
            $nextRoles = $data['roles']; sort($nextRoles);
            $after = ['roles' => $nextRoles, 'organization_id' => isset($data['organization_id']) ? (int) $data['organization_id'] : null, 'is_active' => $target->is_active];
            if ($before !== $after) {
                $roleIds = DB::table('roles')->whereIn('code', $nextRoles)->pluck('id')->all();
                if (count($roleIds) !== count($nextRoles)) {
                    throw ValidationException::withMessages(['roles' => 'Daftar peran berubah. Muat ulang halaman sebelum menyimpan.']);
                }
                $target->organization_id = $after['organization_id'];
                $target->save();
                DB::table('role_user')->where('user_id', $target->id)->whereNotIn('role_id', $roleIds)->delete();
                foreach ($roleIds as $roleId) {
                    DB::table('role_user')->insertOrIgnore(['user_id' => $target->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
                }
                AccountActivity::record($request->user()->id, 'account.access_updated', $target->id, $before, $after);
            }

            return ['access' => $after, 'access_version' => AccountAccess::version($target, $after)];
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Hak akses berhasil diperbarui. Perubahan berlaku pada akses berikutnya.'] + $access)
            : redirect()->route('accounts.index')->with('status', 'Hak akses '.$account->name.' diperbarui. Perubahan berlaku pada akses berikutnya.');
    }
}
