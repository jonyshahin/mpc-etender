<?php

// Table: tender_categories — Purpose: pivot mapping tenders to one or more work categories.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tender_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_categories');
    }
};
