<?php

// Table: vendor_category_requests — Purpose: request-and-approve workflow replacing
// direct vendor self-serve category toggling. One row per vendor request; open
// workflow enforced to one-at-a-time at the service layer.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_category_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained()->cascadeOnDelete();
            $table->text('justification');
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'withdrawn'])
                ->default('pending')
                ->index();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_comments')->nullable();
            $table->text('withdraw_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_category_requests');
    }
};
