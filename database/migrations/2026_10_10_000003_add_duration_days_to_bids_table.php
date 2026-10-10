<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The completion period a vendor commits to, in days (INTEGRATION-DESIGN §1.2).
 *
 * The engine's timeline criterion carries 20% of the weight and is scored
 * relative to the other bids, so it must come from the vendor, sealed with the
 * price — an officer typing it in after opening would be entering a relative
 * input while looking at every price.
 *
 * Nullable because every bid before this has none; required at submission
 * only on tenders whose engine profile is locked. Hidden like total_amount
 * (Bid::$hidden) until a viewer is entitled to see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn('duration_days');
        });
    }
};
