<?php

namespace Database\Factories;

use App\Enums\EngineContractType;
use App\Enums\TimelineFormula;
use App\Models\Tender;
use App\Models\TenderEngineProfile;
use App\Services\DecisionEngine\EngineProfileService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tests\Support\FakeDecisionEngine;

/**
 * @extends Factory<TenderEngineProfile>
 */
class TenderEngineProfileFactory extends Factory
{
    protected $model = TenderEngineProfile::class;

    /** MPC's standard weights, installation contract — ready to publish. */
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory()->published(),
            'template_key' => 'mpc_standard',
            'weight_price' => 40,
            'weight_technical' => 20,
            'weight_timeline' => 20,
            'weight_references' => 10,
            'weight_payment_flexibility' => 10,
            'gate_bank_guarantee' => true,
            'gate_mandatory_documents' => true,
            'margin_below' => 2.00,
            'contract_type' => EngineContractType::Installation,
            'timeline_formula' => TimelineFormula::RelativeToBest,
        ];
    }

    /**
     * Locked in the engine, as on any tender that opened after engine scoring
     * shipped. The definition mirrors what EngineProfileService sends; the
     * fake engine is told about it so batches against it are accepted.
     */
    public function locked(): static
    {
        return $this->state(fn () => [
            'schema_key' => 'etender_bid_evaluation',
            'schema_version' => 1,
            'profile_key' => 'TEST-'.Str::upper(Str::random(8)),
            'locked_at' => now()->subDay(),
        ])->afterCreating(function (TenderEngineProfile $profile) {
            if (! class_exists(FakeDecisionEngine::class)) {
                return;
            }

            $definition = app(EngineProfileService::class)
                ->definition($profile->tender, $profile, 1);
            $definition = ['key' => $profile->profile_key] + $definition;

            // Recorded straight to the table: the model refuses any write to a
            // locked profile, which is the behaviour under test elsewhere.
            TenderEngineProfile::query()->whereKey($profile->id)->toBase()->update(['definition' => json_encode($definition)]);

            FakeDecisionEngine::$profiles[$profile->profile_key] = [
                'definition' => $definition,
                'status' => 'locked',
                'locked_at' => $profile->locked_at->toIso8601ZuluString(),
            ];
        });
    }
}
