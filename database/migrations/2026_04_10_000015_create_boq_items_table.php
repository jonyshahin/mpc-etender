<?php

// Table: boq_items — Purpose: priced line items belonging to a BOQ section.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boq_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('section_id')->constrained('boq_sections')->cascadeOnDelete();
            $table->string('item_code', 50);
            $table->text('description_en');
            $table->text('description_ar')->nullable();
            $table->string('unit', 50);
            $table->decimal('quantity', 15, 3);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boq_items');
    }
};
