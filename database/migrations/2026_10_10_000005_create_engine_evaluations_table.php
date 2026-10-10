<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One evaluation submitted to the engine, and what it came back with.
 *
 * A row exists only once the engine has accepted the batch and returned its
 * id: an engine that cannot be reached leaves nothing behind, so a retry
 * starts clean (INTEGRATION-DESIGN §6). `request` is the exact body sent, kept
 * so the scores an evaluation used stay readable after an officer edits them.
 *
 * Once the engine reports `completed` or `partial`, the stored result is final
 * — the engine treats a finalised batch as immutable (DECISIONS §13.2) and so
 * does this copy. Re-running creates a new row whose batch supersedes this one.
 *
 * Shadow mode: nothing here feeds the evaluation report, the approval chain or
 * the award.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tender_engine_profile_id')->constrained()->cascadeOnDelete();

            $table->string('batch_id', 100)->unique();
            $table->string('idempotency_key', 64)->index();
            $table->string('supersedes_batch_id', 100)->nullable();
            $table->json('request');

            $table->string('status', 20);
            $table->json('result')->nullable();

            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('finalised_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();

            $table->index(['tender_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_evaluations');
    }
};
