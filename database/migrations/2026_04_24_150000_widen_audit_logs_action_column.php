<?php

// Table: audit_logs — widens action column from VARCHAR(30) to VARCHAR(60).
// The original 30 fit the early action vocabulary; the vendor_category_request_*
// events added in C.1 run 32-38 chars, which MySQL rejects (SQLite-backed tests
// did not catch this — SQLite ignores VARCHAR length).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('action', 60)->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('action', 30)->change();
        });
    }
};
