<?php

// Table: evaluation_criteria — Purpose: weighted scoring criteria per tender, grouped by envelope.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->string('name_en', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('envelope', 30)->default('technical');
            $table->decimal('weight_percentage', 5, 2);
            $table->decimal('max_score', 5, 2)->default(100);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
    }
};
