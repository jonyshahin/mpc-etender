<?php

// Table: bid_documents — Purpose: technical and financial attachments uploaded as part of a bid.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bid_id')->constrained('bids')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('file_path', 500);
            $table->integer('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('doc_type', 30);
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_documents');
    }
};
