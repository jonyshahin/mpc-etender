<?php

namespace Database\Factories;

use App\Models\Bid;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationScore>
 */
class EvaluationScoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bid_id' => Bid::factory(),
            'criterion_id' => EvaluationCriterion::factory(),
            'evaluator_id' => User::factory(),
            'score' => fake()->randomFloat(2, 0, 100),
            'justification' => fake()->optional()->sentence(),
            'scored_at' => now(),
        ];
    }
}
