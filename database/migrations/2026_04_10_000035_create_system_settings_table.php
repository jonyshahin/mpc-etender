<?php

// Table: system_settings — Purpose: key/value system configuration grouped by domain. Updated_at only.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 255)->unique();
            $table->text('value');
            $table->string('group', 100);
            $table->string('type', 30)->default('string');
            $table->text('description')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
