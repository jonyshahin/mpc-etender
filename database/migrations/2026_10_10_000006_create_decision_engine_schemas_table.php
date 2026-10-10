<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which engine schema version holds which version of eTender's schema file.
 *
 * The engine creates a new version on every POST /v1/schemas, and it fills in
 * defaults when it stores a definition (`required: false`, `output_shape`), so
 * the stored definition never compares equal to the file that produced it.
 * Without this record eTender could not tell "already registered" from "new",
 * and would mint a fresh engine version on every publish.
 *
 * Keyed on a hash of the file's content: an edit to the file is a new hash,
 * which registers once and is reused from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_engine_schemas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 100);
            $table->string('content_hash', 64);
            $table->unsignedInteger('version');
            $table->timestamps();

            $table->unique(['key', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_engine_schemas');
    }
};
