<?php

// Table: clarifications — Purpose: vendor questions and MPC answers attached to a tender.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clarifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignUuid('asked_by')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignUuid('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('asked_at')->useCurrent();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clarifications');
    }
};
