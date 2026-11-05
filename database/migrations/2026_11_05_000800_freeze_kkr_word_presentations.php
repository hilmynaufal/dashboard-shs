<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('kkr_exports',function(Blueprint $t): void {
            $t->json('render_snapshot')->nullable();
            $t->char('render_sha256',64)->nullable();
            $t->unique('kkr_version_id');
        });
    }
    public function down(): void
    {
        Schema::table('kkr_exports',function(Blueprint $t): void {
            $t->dropUnique(['kkr_version_id']);$t->dropColumn(['render_snapshot','render_sha256']);
        });
    }
};
