<?php

// Table: addenda — Purpose: official tender amendments issued after publication.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addenda', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignUuid('issued_by')->constrained('users')->restrictOnDelete();
            $table->integer('addendum_number');
            $table->string('subject', 255);
            $table->text('content_en');
            $table->text('content_ar')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->boolean('extends_deadline')->default(false);
            $table->timestamp('new_deadline')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addenda');
    }
};
