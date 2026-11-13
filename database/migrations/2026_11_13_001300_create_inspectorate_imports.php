<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GRANTS = ['admin' => ['inspectorate.import'], 'team_lead' => ['inspectorate.import']];

    public function up(): void
    {
        // Impor Inspektorat: workbook usulan multi-SKPD yang langsung berstatus Siap direviu.
        Schema::create('inspectorate_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('file_name', 240);
            $t->char('file_sha256', 64);
            $t->string('status', 12)->default('preview');
            $t->json('mapping')->nullable();
            $t->json('summary')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['file_sha256', 'submission_period_id']);
        });
        Schema::create('inspectorate_import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('inspectorate_import_id')->constrained('inspectorate_imports')->restrictOnDelete();
            $t->string('type', 3);
            $t->unsignedInteger('row_number');
            $t->string('owner', 120);
            $t->json('data');
            $t->text('error')->nullable();
            $t->index(['inspectorate_import_id', 'type', 'owner'], 'inspectorate_rows_group');
        });
        foreach (self::GRANTS as $role => $codes) {
            $roleId = DB::table('roles')->where('code', $role)->value('id');
            foreach ($codes as $code) {
                DB::table('permissions')->insertOrIgnore(['code' => $code, 'name' => $code]);
                if ($roleId) {
                    DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('code', $code)->value('id')]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inspectorate_import_rows');
        Schema::dropIfExists('inspectorate_imports');
        $ids = DB::table('permissions')->whereIn('code', ['inspectorate.import'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
