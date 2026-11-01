<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id(); $t->string('code', 50)->unique(); $t->string('name');
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('is_active')->default(true); $t->string('position')->nullable();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id(); $t->string('code', 50)->unique(); $t->string('name'); $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id(); $t->string('code', 100)->unique(); $t->string('name'); $t->timestamps();
        });
        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->primary(['role_id', 'user_id']); $t->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained()->restrictOnDelete();
            $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });
        Schema::create('submission_periods', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('budget_year'); $t->string('name');
            $t->dateTime('opens_at'); $t->dateTime('closes_at');
            $t->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
            $t->index(['budget_year', 'status']);
        });
        Schema::create('item_classifications', function (Blueprint $t) {
            $t->id(); $t->string('code', 60)->unique(); $t->string('name');
            $t->foreignId('parent_id')->nullable()->constrained('item_classifications')->restrictOnDelete();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        foreach (['account_codes', 'units'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id(); $t->string('code', 60)->unique(); $t->string('name');
                $t->boolean('is_active')->default(true); $t->timestamps();
            });
        }
        Schema::create('reference_documents', function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->string('number')->nullable();
            $t->string('reference_version', 80); $t->date('effective_from')->nullable();
            $t->date('effective_until')->nullable(); $t->unsignedSmallInteger('budget_year')->nullable();
            $t->text('description')->nullable(); $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reference_documents', 'units', 'account_codes', 'item_classifications', 'submission_periods', 'permission_role', 'role_user', 'permissions', 'roles'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('organization_id'); $t->dropColumn(['is_active', 'position']);
        });
        Schema::dropIfExists('organizations');
    }
};
