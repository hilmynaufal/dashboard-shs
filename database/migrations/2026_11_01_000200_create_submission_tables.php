<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('submission_period_id')->constrained()->restrictOnDelete();
            $t->enum('type', ['SHS', 'SBU']); $t->string('number', 100)->nullable()->unique();
            $t->string('title'); $t->enum('status', ['draft', 'submitted', 'verification', 'review', 'completed'])->default('draft');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->dateTime('submitted_at')->nullable(); $t->timestamps();
            $t->index(['organization_id', 'submission_period_id', 'status']);
        });
        Schema::create('proposal_items', function (Blueprint $t) {
            $t->id(); $t->uuid('public_id')->unique();
            $t->foreignId('proposal_id')->constrained()->restrictOnDelete();
            $t->enum('status', ['draft', 'submitted', 'needs_completion', 'ready', 'assigned', 'reviewing', 'awaiting_response', 'recheck', 'reviewed'])->default('draft');
            $t->unsignedInteger('lock_version')->default(0); $t->timestamps();
            $t->index(['proposal_id', 'status']);
        });
        Schema::create('proposal_item_versions', function (Blueprint $t) {
            $t->id(); $t->foreignId('proposal_item_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version_number');
            $t->foreignId('item_classification_id')->constrained()->restrictOnDelete();
            $t->foreignId('unit_id')->constrained()->restrictOnDelete();
            $t->string('description'); $t->text('specification');
            $t->decimal('proposed_price', 20, 2)->unsigned();
            $t->decimal('tkdn_percent', 5, 2)->unsigned()->nullable();
            $t->enum('origin', ['new', 'existing'])->nullable(); $t->text('justification')->nullable();
            $t->json('reference_snapshot');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->text('change_reason')->nullable(); $t->dateTime('submitted_at')->nullable(); $t->timestamps();
            $t->unique(['proposal_item_id', 'version_number'], 'item_version_number_unique');
            $t->unique(['proposal_item_id', 'id'], 'item_version_identity_unique');
        });
        Schema::create('item_version_accounts', function (Blueprint $t) {
            $t->foreignId('item_version_id')->constrained('proposal_item_versions')->restrictOnDelete();
            $t->foreignId('account_code_id')->constrained()->restrictOnDelete();
            $t->string('code_snapshot', 60); $t->string('name_snapshot');
            $t->primary(['item_version_id', 'account_code_id']);
        });
        Schema::create('price_sources', function (Blueprint $t) {
            $t->id(); $t->foreignId('item_version_id')->constrained('proposal_item_versions')->restrictOnDelete();
            $t->enum('type', ['survey', 'regulation', 'study', 'other']); $t->string('name');
            $t->text('source_location')->nullable(); $t->date('source_date')->nullable();
            $t->foreignId('reference_document_id')->nullable()->constrained()->restrictOnDelete();
            $t->json('reference_snapshot')->nullable(); $t->string('unit_description')->nullable();
            $t->text('specification')->nullable();
            foreach (['base_price', 'tax_amount', 'shipping_amount', 'other_amount', 'total_price'] as $amount) {
                $t->decimal($amount, 20, 2)->unsigned()->nullable();
            }
            $t->enum('tax_status', ['included', 'excluded', 'unknown'])->default('unknown');
            $t->text('calculation_notes')->nullable(); $t->timestamps();
        });
        Schema::create('evidence_files', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('original_name'); $t->string('disk', 50)->default('local');
            $t->string('path')->unique(); $t->string('mime_type', 100);
            $t->unsignedBigInteger('byte_size'); $t->char('sha256', 64)->index(); $t->timestamps();
        });
        Schema::create('item_version_evidence', function (Blueprint $t) {
            $t->foreignId('item_version_id')->constrained('proposal_item_versions')->restrictOnDelete();
            $t->foreignId('evidence_file_id')->constrained()->restrictOnDelete();
            $t->string('purpose', 100); $t->primary(['item_version_id', 'evidence_file_id']);
        });
        Schema::create('verification_results', function (Blueprint $t) {
            $t->id(); $t->foreignId('item_version_id')->constrained('proposal_item_versions')->restrictOnDelete();
            $t->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $t->enum('result', ['ready', 'needs_completion']); $t->text('reason')->nullable();
            $t->dateTime('verified_at'); $t->timestamps();
        });
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id(); $t->foreignId('proposal_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->char('file_sha256', 64); $t->string('sheet_name', 100);
            $t->enum('status', ['preview', 'processing', 'completed', 'failed'])->default('preview');
            $t->json('mapping')->nullable(); $t->json('result_summary')->nullable(); $t->timestamps();
            $t->unique(['proposal_id', 'file_sha256', 'sheet_name'], 'import_batch_identity_unique');
        });
        Schema::create('import_rows', function (Blueprint $t) {
            $t->id(); $t->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('row_number'); $t->json('raw_values'); $t->json('errors')->nullable();
            $t->foreignId('item_version_id')->nullable()->constrained('proposal_item_versions')->restrictOnDelete();
            $t->unique(['import_batch_id', 'row_number']); $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['import_rows', 'import_batches', 'verification_results', 'item_version_evidence', 'evidence_files', 'price_sources', 'item_version_accounts', 'proposal_item_versions', 'proposal_items', 'proposals'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
