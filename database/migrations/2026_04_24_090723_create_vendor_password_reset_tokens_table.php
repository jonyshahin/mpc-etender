<?php

// Dedicated password reset tokens table for the `vendor` guard. Kept separate
// from `password_reset_tokens` (used by the `users` guard) because emails are
// not unique across users and vendors — sharing a table would let one reset
// silently overwrite the other.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_password_reset_tokens');
    }
};
