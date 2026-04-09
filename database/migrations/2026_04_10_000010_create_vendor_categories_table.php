<?php

// Table: vendor_categories — Purpose: pivot mapping vendors to the categories they are qualified for.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['vendor_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_categories');
    }
};
