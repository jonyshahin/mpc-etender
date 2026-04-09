<?php

// Table: awards — Purpose: contract award records linking a winning bid to a vendor and approver.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignUuid('bid_id')->constrained('bids')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $table->decimal('award_amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->text('justification')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('letter_file_path', 500)->nullable();
            $table->timestamp('awarded_at')->useCurrent();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
