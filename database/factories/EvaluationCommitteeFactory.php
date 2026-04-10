<?php

namespace Database\Factories;

use App\Enums\CommitteeType;
use App\Models\EvaluationCommittee;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationCommittee>
 */
class EvaluationCommitteeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'name' => fake()->randomElement(['Technical Evaluation Committee', 'Financial Evaluation Committee', 'Combined Committee']),
            'committee_type' => fake()->randomElement(CommitteeType::cases()),
            'status' => 'pending',
            'formed_at' => now(),
            'completed_at' => null,
        ];
    }
}
