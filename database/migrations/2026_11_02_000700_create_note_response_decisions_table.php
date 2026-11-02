<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_response_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('note_response_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $t->enum('decision', ['resolved', 'needs_revision']);
            $t->text('reason');
            $t->dateTime('decided_at');
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('note_response_decisions'); }
};
