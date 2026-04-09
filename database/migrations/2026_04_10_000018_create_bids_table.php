<?php

// Table: bids — Purpose: vendor submissions against a tender; pricing encrypted at rest until opening.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('bid_reference', 50)->unique();
            $table->string('envelope_type', 30)->default('single');
            $table->text('encrypted_pricing_data')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->text('technical_notes')->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('is_sealed')->default(true);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->foreignUuid('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('withdrawal_reason')->nullable();
            $table->string('submission_ip', 45)->nullable();
            $table->string('submission_user_agent', 500)->nullable();
            $table->timestamps();

            $table->unique(['tender_id', 'vendor_id']);
            $table->index(['tender_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
