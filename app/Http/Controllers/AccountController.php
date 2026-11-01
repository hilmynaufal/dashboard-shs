<?php

namespace App\Http\Controllers;

use App\Authorization\RolePermissions;
use App\Models\User;
use App\Support\{AccountActivity, AccountValidation};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('accounts.manage');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $users = User::query()->select(['id', 'name', 'email', 'position', 'organization_id', 'is_active'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
        $rolesByUser = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('user_id', $users->pluck('id'))->get(['user_id', 'roles.name'])->groupBy('user_id');

        return view('accounts.index', [
            'users' => $users, 'rolesByUser' => $rolesByUser,
            'organizations' => DB::table('organizations')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('accounts.manage');

        return view('accounts.create', [
            'roles' => RolePermissions::ROLES,
            'organizations' => DB::table('organizations')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('accounts.manage');
        $request->merge(['email' => is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : $request->input('email')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'position' => ['nullable', 'string', 'max:255'], 'role' => ['required', Rule::in(array_keys(RolePermissions::ROLES)), Rule::exists('roles', 'code')],
            'organization_id' => [Rule::requiredIf($request->input('role') === 'skpd_operator'), 'nullable', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)],
            'password' => AccountValidation::passwordRules(),
            'is_active' => ['prohibited'], 'roles' => ['prohibited'],
        ], AccountValidation::MESSAGES, AccountValidation::ATTRIBUTES);
        $user = DB::transaction(function () use ($request, $data): User {
            $user = new User;
            foreach (['name', 'email', 'password', 'position', 'organization_id'] as $field) {
                $user->$field = $data[$field] ?? null;
            }
            $user->save();
            DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $data['role'])->value('id'), 'created_at' => now(), 'updated_at' => now()]);
            AccountActivity::record($request->user()->id, 'account.created', $user->id, null, ['role' => $data['role'], 'organization_id' => $user->organization_id, 'is_active' => true]);

            return $user;
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Akun berhasil dibuat.', 'user' => $user->only(['id', 'name', 'email', 'is_active', 'organization_id'])], 201)
            : redirect()->route('accounts.index')->with('status', 'Akun '.$user->name.' berhasil dibuat.');
    }

    public function status(Request $request, User $account): JsonResponse|RedirectResponse
    {
        Gate::authorize('accounts.manage');
        $data = $request->validate(['is_active' => ['required', 'boolean']], AccountValidation::MESSAGES, AccountValidation::ATTRIBUTES);
        DB::transaction(function () use ($request, $account, $data): void {
            // Stable lock order serializes administrators changing one another's account status.
            $locked = User::query()->whereIn('id', [$request->user()->id, $account->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($locked[$request->user()->id]->hasPermission('accounts.manage'), 403);
            $target = $locked[$account->id];
            if ($target->id === $request->user()->id && ! (bool) $data['is_active']) {
                throw ValidationException::withMessages(['is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
            }
            $before = ['is_active' => $target->is_active];
            $target->is_active = (bool) $data['is_active'];
            $target->save();
            AccountActivity::record($request->user()->id, 'account.status_updated', $target->id, $before, ['is_active' => $target->is_active]);
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Status akun berhasil diperbarui.'])
            : back()->with('status', 'Status akun berhasil diperbarui.');
    }
}
