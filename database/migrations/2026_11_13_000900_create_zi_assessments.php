<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const GRANTS = [
        'admin' => ['zi.manage'],
        'skpd_operator' => ['zi.write', 'zi.submit'],
        'examiner' => ['zi.review', 'zi.return', 'zi.finalize'],
    ];

    public function up(): void
    {
        Schema::create('zi_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->unsignedSmallInteger('year');
            $t->string('target', 4);
            $t->foreignId('reviewer_id')->constrained('users');
            $t->foreignId('created_by')->constrained('users');
            $t->string('instrument');
            $t->string('state')->default('draft');
            $t->unsignedInteger('revision')->default(0);
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users');
            $t->timestamp('finalized_at')->nullable();
            $t->foreignId('finalized_by')->nullable()->constrained('users');
            $t->json('final_result')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'year', 'target']);
        });
        Schema::create('zi_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_id')->constrained('zi_assessments');
            $t->unsignedSmallInteger('item');
            $t->string('action');
            $t->json('answer')->nullable();
            $t->text('note')->nullable();
            $t->string('evidence')->nullable();
            $t->foreignId('actor_id')->constrained('users');
            $t->timestamp('created_at');
            $t->index(['assessment_id','item','id']);
        });
        foreach (self::GRANTS as $role => $codes) {
            $roleId = DB::table('roles')->where('code', $role)->value('id');
            foreach ($codes as $code) {
                DB::table('permissions')->insertOrIgnore(['code'=>$code,'name'=>$code]);
                if ($roleId) {
                    DB::table('permission_role')->insertOrIgnore(['role_id'=>$roleId,'permission_id'=>DB::table('permissions')->where('code',$code)->value('id')]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zi_entries');
        Schema::dropIfExists('zi_assessments');
        $ids = DB::table('permissions')->whereIn('code', array_merge(...array_values(self::GRANTS)))->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
