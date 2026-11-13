<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // per_aspect: tiap aspek dinilai sendiri. single: satu "Hasil pemeriksaan" berlaku untuk semua aspek.
        Schema::table('item_reviews', fn (Blueprint $table) => $table->string('result_mode', 12)->default('per_aspect')->after('status'));
    }

    public function down(): void
    {
        Schema::table('item_reviews', fn (Blueprint $table) => $table->dropColumn('result_mode'));
    }
};
