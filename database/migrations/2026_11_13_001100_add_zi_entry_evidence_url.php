<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bukti dukung butir ZI: berkas privat (evidence) ATAU tautan Google Drive (evidence_url).
        Schema::table('zi_entries', fn (Blueprint $table) => $table->string('evidence_url', 2000)->nullable()->after('evidence'));
    }

    public function down(): void
    {
        Schema::table('zi_entries', fn (Blueprint $table) => $table->dropColumn('evidence_url'));
    }
};
