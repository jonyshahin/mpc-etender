<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * MPC's standard criteria for engine scoring (INTEGRATION-DESIGN §3).
 *
 * A tender copies the template when it is created and a procurement officer
 * may adjust the copy while the tender is a draft; the template itself is the
 * starting point, not a live setting — editing it never reaches a tender that
 * already has its copy.
 *
 * Weights are whole percentages summing to 100. The five criteria are fixed by
 * eTender's engine schema (etender_bid_evaluation), so they are columns, not a
 * free-form list: a weight for a criterion the schema does not have could not
 * be sent anyway.
 *
 * The standard row is inserted here rather than by a seeder, so it exists the
 * moment the table does — production runs migrations on deploy but only named
 * seeders. Insert-if-missing: a template an admin later adjusts is never reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_criteria_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 50)->unique();
            $table->string('name');

            $table->unsignedTinyInteger('weight_price');
            $table->unsignedTinyInteger('weight_technical');
            $table->unsignedTinyInteger('weight_timeline');
            $table->unsignedTinyInteger('weight_references');
            $table->unsignedTinyInteger('weight_payment_flexibility');

            // Officer-attested gates. On by default for new tenders: the BEF
            // forms had none, and the reconstruction notes say to add them.
            $table->boolean('gate_bank_guarantee')->default(true);
            $table->boolean('gate_mandatory_documents')->default(true);

            // A gap between the top two weighted totals below this many points
            // flags the whole ranking for review. BEF-01 was decided by 0.79.
            $table->decimal('margin_below', 5, 2)->default(2.00);

            $table->timestamps();
        });

        DB::table('evaluation_criteria_templates')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'key' => 'mpc_standard',
            'name' => 'MPC standard',
            'weight_price' => 40,
            'weight_technical' => 20,
            'weight_timeline' => 20,
            'weight_references' => 10,
            'weight_payment_flexibility' => 10,
            'gate_bank_guarantee' => true,
            'gate_mandatory_documents' => true,
            'margin_below' => 2.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria_templates');
    }
};
