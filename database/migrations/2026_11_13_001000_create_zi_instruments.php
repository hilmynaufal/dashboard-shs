<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Versi bawaan yang sebelumnya dibaca dari berkas di resources/instruments. */
    private const SEEDED = ['zi-manajemen-v1', 'zi-v2'];
    private const GRANTS = ['admin' => ['zi.template']];

    public function up(): void
    {
        Schema::create('zi_instruments', function (Blueprint $t) {
            $t->id();
            $t->string('version')->unique();
            $t->string('status', 12)->default('draft'); // draft | published
            $t->string('source')->nullable();
            $t->json('items');
            $t->json('rules');
            $t->unsignedInteger('revision')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamp('published_at')->nullable();
            $t->foreignId('published_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('zi_instrument_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instrument_id')->constrained('zi_instruments')->cascadeOnDelete();
            $t->string('action');
            $t->text('detail')->nullable();
            $t->foreignId('actor_id')->constrained('users');
            $t->timestamp('created_at');
        });
        foreach (self::SEEDED as $version) {
            DB::table('zi_instruments')->insert([
                'version'=>$version, 'status'=>'published',
                'items'=>file_get_contents(resource_path('instruments/'.$version.'.json')),
                'rules'=>file_get_contents(resource_path('instruments/'.$version.'.rules.json')),
                'published_at'=>now(), 'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }
        if (!Schema::hasTable('roles')) {
            return;
        }
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
        Schema::dropIfExists('zi_instrument_events');
        Schema::dropIfExists('zi_instruments');
        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('code', array_merge(...array_values(self::GRANTS)))->pluck('id');
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
