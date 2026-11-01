<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kkr_documents', function (Blueprint $t) {
            $t->id(); $t->foreignId('review_assignment_id')->constrained()->restrictOnDelete();
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->string('number', 100)->unique(); $t->string('title');
            $t->enum('status', ['draft', 'awaiting_lead', 'returned', 'awaiting_controller', 'final', 'superseded'])->default('draft');
            $t->unsignedInteger('lock_version')->default(0); $t->timestamps();
        });
        Schema::create('kkr_versions', function (Blueprint $t) {
            $t->id(); $t->foreignId('kkr_document_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version_number'); $t->string('template_version', 80);
            $t->json('snapshot'); $t->char('snapshot_sha256', 64);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('supersedes_version_id')->nullable()->constrained('kkr_versions')->restrictOnDelete();
            $t->text('change_reason')->nullable(); $t->dateTime('submitted_at')->nullable();
            $t->dateTime('finalized_at')->nullable(); $t->timestamps();
            $t->unique(['kkr_document_id', 'version_number']);
        });
        Schema::create('kkr_entries', function (Blueprint $t) {
            $t->id(); $t->foreignId('kkr_version_id')->constrained()->restrictOnDelete();
            $t->foreignId('item_review_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('display_order'); $t->boolean('has_open_notes')->default(false);
            $t->json('snapshot'); $t->unique(['kkr_version_id', 'item_review_id']);
            $t->unique(['kkr_version_id', 'display_order']);
        });
        Schema::create('kkr_approvals', function (Blueprint $t) {
            $t->id(); $t->foreignId('kkr_version_id')->constrained()->restrictOnDelete();
            $t->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $t->enum('level', ['team_lead', 'technical_controller']);
            $t->enum('decision', ['approved', 'returned']); $t->text('notes')->nullable();
            $t->dateTime('decided_at'); $t->unique(['kkr_version_id', 'level']); $t->timestamps();
        });
        Schema::create('kkr_exports', function (Blueprint $t) {
            $t->id(); $t->foreignId('kkr_version_id')->constrained()->restrictOnDelete();
            $t->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $t->string('disk', 50)->default('local'); $t->string('path')->unique();
            $t->char('sha256', 64); $t->unsignedBigInteger('byte_size'); $t->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action', 100); $t->string('subject_type', 100); $t->unsignedBigInteger('subject_id');
            $t->json('before_values')->nullable(); $t->json('after_values')->nullable();
            $t->text('reason')->nullable(); $t->dateTime('occurred_at');
            $t->index(['subject_type', 'subject_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'kkr_exports', 'kkr_approvals', 'kkr_entries', 'kkr_versions', 'kkr_documents'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
