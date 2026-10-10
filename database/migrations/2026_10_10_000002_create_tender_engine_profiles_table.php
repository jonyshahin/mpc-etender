<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One tender's engine scoring criteria — editable while the tender is a draft,
 * frozen the moment the engine confirms the lock (INTEGRATION-DESIGN §3).
 *
 * Until locked this is eTender's working copy of the template. On publish
 * eTender sends it to the engine, locks it there, and only then records
 * profile_key, locked_at and the exact definition it sent, in the same
 * transaction that opens the tender. A row with locked_at set is immutable —
 * the model refuses the update — because changing weights after bids are seen
 * is precisely what the lock exists to prevent.
 *
 * Tenders that opened before this shipped have no row and are not scored by
 * the engine: locking them now would be "open first, lock later" (§3.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_engine_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('template_key', 50)->nullable();

            $table->unsignedTinyInteger('weight_price');
            $table->unsignedTinyInteger('weight_technical');
            $table->unsignedTinyInteger('weight_timeline');
            $table->unsignedTinyInteger('weight_references');
            $table->unsignedTinyInteger('weight_payment_flexibility');
            $table->boolean('gate_bank_guarantee');
            $table->boolean('gate_mandatory_documents');
            $table->decimal('margin_below', 5, 2);

            // Picks the timeline formula. Null until the officer chooses one:
            // there is no sensible default, so publishing requires it.
            $table->string('contract_type', 30)->nullable();
            // Only for a contract type outside the seeded two, whose formula
            // the officer chose explicitly.
            $table->string('contract_type_label')->nullable();
            $table->string('timeline_formula', 30)->nullable();

            // Set together, once, when the engine confirms the lock.
            $table->string('schema_key', 100)->nullable();
            $table->unsignedInteger('schema_version')->nullable();
            $table->string('profile_key', 150)->nullable()->unique();
            $table->json('definition')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignUuid('locked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        // Drafts that already exist get their copy of the standard template
        // now, so they can still be published: an officer only has to choose
        // the contract type. Tenders past draft get nothing — they opened
        // without a lock and stay on the existing evaluation (§3.2).
        $template = DB::table('evaluation_criteria_templates')->where('key', 'mpc_standard')->first();

        DB::table('tenders')
            ->where('status', 'draft')
            ->whereNotIn('id', DB::table('tender_engine_profiles')->select('tender_id'))
            ->pluck('id')
            ->each(fn (string $tenderId) => DB::table('tender_engine_profiles')->insert([
                'id' => (string) Str::uuid(),
                'tender_id' => $tenderId,
                'template_key' => $template->key,
                'weight_price' => $template->weight_price,
                'weight_technical' => $template->weight_technical,
                'weight_timeline' => $template->weight_timeline,
                'weight_references' => $template->weight_references,
                'weight_payment_flexibility' => $template->weight_payment_flexibility,
                'gate_bank_guarantee' => $template->gate_bank_guarantee,
                'gate_mandatory_documents' => $template->gate_mandatory_documents,
                'margin_below' => $template->margin_below,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_engine_profiles');
    }
};
