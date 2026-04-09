<?php

// Table: evaluation_committees — Purpose: groups of evaluators assigned to score a tender.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_committees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('committee_type', 30)->default('technical');
            $table->string('status', 30)->default('pending');
            $table->timestamp('formed_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_committees');
    }
};
