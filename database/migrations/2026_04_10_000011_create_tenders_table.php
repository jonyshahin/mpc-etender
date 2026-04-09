<?php

// Table: tenders — Purpose: tender records belonging to projects, with deadlines and envelope config.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('reference_number', 50)->unique();
            $table->string('title_en', 500);
            $table->string('title_ar', 500)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('tender_type', 30);
            $table->string('status', 30)->default('draft');
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamp('publish_date')->nullable();
            $table->timestamp('submission_deadline');
            $table->timestamp('opening_date');
            $table->boolean('is_two_envelope')->default(false);
            $table->decimal('technical_pass_score', 5, 2)->nullable();
            $table->boolean('requires_site_visit')->default(false);
            $table->timestamp('site_visit_date')->nullable();
            $table->text('notes_internal')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('submission_deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenders');
    }
};
