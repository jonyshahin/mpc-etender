<?php

// Table: evaluation_scores — Purpose: individual evaluator scores for a bid against a criterion.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bid_id')->constrained('bids')->cascadeOnDelete();
            $table->foreignUuid('criterion_id')->constrained('evaluation_criteria')->cascadeOnDelete();
            $table->foreignUuid('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('justification')->nullable();
            $table->timestamp('scored_at')->useCurrent();
            $table->timestamps();

            $table->unique(['bid_id', 'criterion_id', 'evaluator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
    }
};
