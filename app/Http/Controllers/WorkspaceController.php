<?php

namespace App\Http\Controllers;

use App\Authorization\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function show(Request $request): View
    {
        $roles = DB::table('roles')->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $request->user()->id)->orderBy('roles.id')->pluck('roles.code')->all();

        return view('workspace.home', [
            'roles' => array_intersect_key(RolePermissions::ROLES, array_flip($roles)),
        ]);
    }
}
