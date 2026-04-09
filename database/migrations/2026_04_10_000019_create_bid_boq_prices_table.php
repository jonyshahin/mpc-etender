<?php

// Table: bid_boq_prices — Purpose: per-line-item unit and total prices submitted by a vendor in a bid.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_boq_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bid_id')->constrained('bids')->cascadeOnDelete();
            $table->foreignUuid('boq_item_id')->constrained('boq_items')->cascadeOnDelete();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_price', 15, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_boq_prices');
    }
};
