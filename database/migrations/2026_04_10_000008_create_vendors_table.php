<?php

// Table: vendors — Purpose: external supplier accounts (separate auth guard from MPC users).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name', 255);
            $table->string('company_name_ar', 255)->nullable();
            $table->string('trade_license_no', 100)->nullable();
            $table->string('contact_person', 255);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('website', 500)->nullable();
            $table->string('prequalification_status', 30)->default('pending');
            $table->timestamp('qualified_at')->nullable();
            $table->foreignUuid('qualified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('language_pref', 2)->default('ar');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('prequalification_status');
            $table->index('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
