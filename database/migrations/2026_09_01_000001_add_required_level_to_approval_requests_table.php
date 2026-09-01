<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many approval levels a request must clear, frozen when it is raised.
 *
 * `approval_level` is the level a request currently sits at. There was nowhere
 * to record how high the chain had to go, so approve() recomputed it from the
 * live tender and the live thresholds on every decision — which meant editing
 * a tender, or changing approval.level1_threshold on /admin/settings, silently
 * re-levelled chains that were already running.
 *
 * Existing rows carry approval_level forward as their required level: those
 * were created already sitting at the maximum, so this preserves exactly the
 * behaviour any in-flight request would have had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->integer('required_level')->default(1)->after('approval_level');
        });

        DB::table('approval_requests')->update([
            'required_level' => DB::raw('approval_level'),
        ]);
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropColumn('required_level');
        });
    }
};
