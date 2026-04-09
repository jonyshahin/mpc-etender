<?php

// Table: evaluation_reports — Purpose: aggregated ranking and recommendation produced from committee scores.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignUuid('generated_by')->constrained('users')->restrictOnDelete();
            $table->string('report_type', 30)->default('final');
            $table->text('summary')->nullable();
            $table->json('ranking_data');
            $table->foreignUuid('recommended_bid_id')->nullable()->constrained('bids')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('file_path', 500)->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_reports');
    }
};
