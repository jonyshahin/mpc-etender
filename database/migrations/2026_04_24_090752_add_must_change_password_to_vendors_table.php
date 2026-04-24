<?php

// Flag flipped to `true` when an admin sets a temporary password for a vendor.
// While `true`, the vendor is locked to the password-change page until they set
// a new password of their own.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
