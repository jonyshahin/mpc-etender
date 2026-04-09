<?php

// Table: approval_requests — Purpose: requests for management approval against an evaluation report.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignUuid('report_id')->constrained('evaluation_reports')->cascadeOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('approval_type', 30);
            $table->decimal('value_threshold', 15, 2)->nullable();
            $table->integer('approval_level')->default(1);
            $table->string('status', 30)->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('deadline')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
