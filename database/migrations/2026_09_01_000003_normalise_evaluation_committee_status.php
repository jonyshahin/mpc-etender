<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One vocabulary for evaluation_committees.status.
 *
 * The column defaulted to 'pending' while store() wrote 'active' and update()
 * accepted only active|completed. Nothing offered 'pending' and nothing read
 * it, but any writer that omitted the column would have produced a row the
 * new enum cast cannot load.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('evaluation_committees')
            ->whereNotIn('status', ['active', 'completed'])
            ->update(['status' => 'active']);

        Schema::table('evaluation_committees', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_committees', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });
    }
};
