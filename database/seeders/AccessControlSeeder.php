<?php

namespace Database\Seeders;

use App\Authorization\RolePermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (RolePermissions::ROLES as $code => $name) {
                DB::table('roles')->updateOrInsert(['code' => $code], ['name' => $name, 'updated_at' => now()]);
                $roleId = DB::table('roles')->where('code', $code)->value('id');
                $permissionIds = [];
                foreach (RolePermissions::GRANTS[$code] as $grant) {
                    DB::table('permissions')->updateOrInsert(['code' => $grant], ['name' => $grant, 'updated_at' => now()]);
                    $permissionIds[] = DB::table('permissions')->where('code', $grant)->value('id');
                }
                DB::table('permission_role')->where('role_id', $roleId)->delete();
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
        });
    }
}
