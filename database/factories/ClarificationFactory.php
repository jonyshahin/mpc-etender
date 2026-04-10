<?php

namespace Database\Factories;

use App\Models\Clarification;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clarification>
 */
class ClarificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'asked_by' => null,
            'answered_by' => null,
            'question' => fake()->paragraph(),
            'answer' => null,
            'is_published' => false,
            'asked_at' => now(),
            'answered_at' => null,
            'published_at' => null,
        ];
    }
}
