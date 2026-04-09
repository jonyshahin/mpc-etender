<?php

// Table: projects — Purpose: construction projects under which tenders are issued.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('client_name', 255)->nullable();
            $table->string('status', 30)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
