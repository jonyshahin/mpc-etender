<?php

// Table: addenda — BUG-26: add new_opening_date so deadline-extension
// addenda also cascade the tender's opening_date alongside its
// submission_deadline. Without this column the controller has nowhere
// to persist the new opening date, leaving tenders in the un-openable
// state where submission_deadline >= opening_date.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addenda', function (Blueprint $table) {
            $table->timestamp('new_opening_date')->nullable()->after('new_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('addenda', function (Blueprint $table) {
            $table->dropColumn('new_opening_date');
        });
    }
};
