<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_assignments', function (Blueprint $t) {
            $t->id(); $t->string('number', 100)->unique(); $t->string('title');
            $t->foreignId('submission_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('object_organization_id')->constrained('organizations')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->text('task_basis')->nullable(); $t->text('scope_description')->nullable();
            $t->enum('status', ['draft', 'active', 'completed'])->default('draft'); $t->timestamps();
        });
        Schema::create('assignment_members', function (Blueprint $t) {
            $t->id(); $t->foreignId('review_assignment_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->enum('role', ['examiner', 'team_lead', 'technical_controller']);
            $t->boolean('is_active')->default(true); $t->timestamps();
            $t->unique(['review_assignment_id', 'user_id', 'role'], 'assignment_member_role_unique');
            $t->unique(['review_assignment_id', 'id'], 'assignment_member_identity_unique');
        });
        Schema::create('assignment_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('review_assignment_id')->constrained()->restrictOnDelete();
            $t->foreignId('proposal_item_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('initial_version_id'); $t->unsignedBigInteger('examiner_member_id');
            $t->foreign(['proposal_item_id', 'initial_version_id'], 'assignment_item_version_fk')->references(['proposal_item_id', 'id'])->on('proposal_item_versions')->restrictOnDelete();
            $t->foreign(['review_assignment_id', 'examiner_member_id'], 'assignment_item_member_fk')->references(['review_assignment_id', 'id'])->on('assignment_members')->restrictOnDelete();
            $t->unique(['review_assignment_id', 'proposal_item_id'], 'assignment_scope_item_unique');
            $t->unique(['id', 'proposal_item_id'], 'assignment_scope_identity_unique'); $t->timestamps();
        });
        Schema::create('assignment_transfers', function (Blueprint $t) {
            $t->id(); $t->foreignId('assignment_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('from_member_id')->constrained('assignment_members')->restrictOnDelete();
            $t->foreignId('to_member_id')->constrained('assignment_members')->restrictOnDelete();
            $t->foreignId('transferred_by')->constrained('users')->restrictOnDelete();
            $t->text('reason'); $t->dateTime('transferred_at'); $t->timestamps();
        });
        Schema::create('review_aspects', function (Blueprint $t) {
            $t->id(); $t->string('code', 60)->unique(); $t->string('name');
            $t->boolean('is_required')->default(true); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('item_reviews', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('assignment_item_id'); $t->unsignedBigInteger('proposal_item_id');
            $t->unsignedBigInteger('item_version_id');
            $t->foreign(['assignment_item_id', 'proposal_item_id'], 'item_review_scope_fk')->references(['id', 'proposal_item_id'])->on('assignment_items')->restrictOnDelete();
            $t->foreign(['proposal_item_id', 'item_version_id'], 'item_review_version_fk')->references(['proposal_item_id', 'id'])->on('proposal_item_versions')->restrictOnDelete();
            $t->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['in_progress', 'awaiting_response', 'recheck', 'completed'])->default('in_progress');
            $t->decimal('comparison_price', 20, 2)->unsigned()->nullable();
            $t->decimal('recommended_price', 20, 2)->unsigned()->nullable();
            $t->text('recommendation')->nullable(); $t->dateTime('completed_at')->nullable(); $t->timestamps();
            $t->unique(['assignment_item_id', 'item_version_id'], 'review_scope_version_unique');
        });
        Schema::create('review_results', function (Blueprint $t) {
            $t->id(); $t->foreignId('item_review_id')->constrained()->restrictOnDelete();
            $t->foreignId('review_aspect_id')->constrained()->restrictOnDelete();
            $t->enum('result', ['not_assessed', 'compliant', 'non_compliant', 'insufficient_evidence', 'not_applicable'])->default('not_assessed');
            $t->json('criterion_snapshot'); $t->text('reason')->nullable(); $t->timestamps();
            $t->unique(['item_review_id', 'review_aspect_id']);
        });
        Schema::create('review_notes', function (Blueprint $t) {
            $t->id(); $t->foreignId('item_version_id')->constrained('proposal_item_versions')->restrictOnDelete();
            $t->foreignId('item_review_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->enum('type', ['administrative', 'substantive']); $t->string('category', 100);
            $t->text('description'); $t->text('recommendation')->nullable();
            $t->enum('status', ['internal', 'awaiting_lead', 'sent', 'responded', 'resolved', 'open_at_final'])->default('internal');
            $t->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->dateTime('sent_at')->nullable(); $t->timestamps();
        });
        Schema::create('note_responses', function (Blueprint $t) {
            $t->id(); $t->foreignId('review_note_id')->constrained()->restrictOnDelete();
            $t->foreignId('responded_by')->constrained('users')->restrictOnDelete(); $t->text('response');
            $t->foreignId('revised_version_id')->nullable()->constrained('proposal_item_versions')->restrictOnDelete();
            $t->dateTime('responded_at'); $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['note_responses', 'review_notes', 'review_results', 'item_reviews', 'review_aspects', 'assignment_transfers', 'assignment_items', 'assignment_members', 'review_assignments'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
