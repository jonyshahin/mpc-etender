<?php

// Table: notification_templates — Purpose: bilingual templates per channel and notification type.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 100)->unique();
            $table->string('channel', 30);
            $table->string('notification_type', 30);
            $table->string('subject_en', 500)->nullable();
            $table->string('subject_ar', 500)->nullable();
            $table->text('body_template_en');
            $table->text('body_template_ar');
            $table->string('whatsapp_template_name', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
