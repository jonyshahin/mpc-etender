<?php

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationCriterion>
 */
class EvaluationCriterionFactory extends Factory
{
    public function definition(): array
    {
        $criteria = [
            'Technical Approach',
            'Past Experience',
            'Key Personnel',
            'Work Program',
            'Equipment List',
            'Financial Capacity',
        ];

        return [
            'tender_id' => Tender::factory(),
            'name_en' => fake()->randomElement($criteria),
            'name_ar' => null,
            'envelope' => 'technical',
            'weight_percentage' => fake()->randomFloat(2, 5, 40),
            'max_score' => 100,
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
