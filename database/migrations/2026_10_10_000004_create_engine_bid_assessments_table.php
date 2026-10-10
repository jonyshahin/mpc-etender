<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a procurement officer records about one bid before the engine scores it.
 *
 * Stage A: one agreed number per judgement criterion, typed by one person
 * (technical, references, payment flexibility), plus the two document gates.
 *
 * Gates are human attestations, not computed facts (INTEGRATION-DESIGN §1.3):
 * each carries who attested it and when, because in a contested award "the
 * gate passed" with no name against it is worth nothing. A null gate means not
 * yet attested — distinct from false, which is an attested failure.
 *
 * `references` is an SQL reserved word, hence the _score suffixes throughout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_bid_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bid_id')->unique()->constrained()->cascadeOnDelete();

            $table->decimal('technical_score', 5, 2)->nullable();
            $table->decimal('references_score', 5, 2)->nullable();
            $table->decimal('payment_flexibility_score', 5, 2)->nullable();
            $table->foreignUuid('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scored_at')->nullable();

            $table->boolean('bank_guarantee')->nullable();
            $table->foreignUuid('bank_guarantee_attested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('bank_guarantee_attested_at')->nullable();

            $table->boolean('mandatory_documents')->nullable();
            $table->foreignUuid('mandatory_documents_attested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('mandatory_documents_attested_at')->nullable();

            $table->timestamps();

            $table->index('tender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_bid_assessments');
    }
};
