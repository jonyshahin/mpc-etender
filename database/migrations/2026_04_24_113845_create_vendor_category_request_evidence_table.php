<?php

// Table: vendor_category_request_evidence — Purpose: uploaded supporting files
// (trade license, portfolio, equipment list, certifications) attached to a
// VendorCategoryRequest. Stored on S3 via FileUploadService; `path` holds the
// S3 object key. `disk` column omitted — FileUploadService hardcodes s3.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_category_request_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')
                ->constrained('vendor_category_requests')
                ->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->foreignUuid('uploaded_by_vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_category_request_evidence');
    }
};
