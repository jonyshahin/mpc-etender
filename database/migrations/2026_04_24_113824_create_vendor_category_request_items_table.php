<?php

// Table: vendor_category_request_items — Purpose: per-category add/remove delta
// rows belonging to a VendorCategoryRequest. Approval applies these to the
// vendor_categories pivot atomically.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-repair: an earlier deploy attempt created this table successfully
        // but then failed on the ALTER TABLE ... ADD UNIQUE (MySQL 64-char
        // identifier limit). MySQL DDL is not transactional — the CREATE TABLE
        // was NOT rolled back, so the table exists without the unique index and
        // the migration was never recorded. Drop-if-exists makes this migration
        // idempotent against that half-built state.
        Schema::dropIfExists('vendor_category_request_items');

        Schema::create('vendor_category_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')
                ->constrained('vendor_category_requests')
                ->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained()->cascadeOnDelete();
            $table->enum('operation', ['add', 'remove']);
            $table->timestamps();

            // Explicit short name — Laravel's auto-generated name
            // (vendor_category_request_items_request_id_category_id_operation_unique)
            // is 69 chars and exceeds MySQL's 64-char identifier limit.
            $table->unique(['request_id', 'category_id', 'operation'], 'vcri_req_cat_op_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_category_request_items');
    }
};
