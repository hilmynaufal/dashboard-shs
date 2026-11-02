<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_results', fn (Blueprint $table) => $table->text('recommendation')->nullable()->after('reason'));
        Schema::table('item_reviews', fn (Blueprint $table) => $table->unsignedInteger('lock_version')->default(0)->after('completed_at'));
    }

    public function down(): void
    {
        Schema::table('review_results', fn (Blueprint $table) => $table->dropColumn('recommendation'));
        Schema::table('item_reviews', fn (Blueprint $table) => $table->dropColumn('lock_version'));
    }
};
