<?php

namespace Database\Factories;

use App\Models\EvaluationReport;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationReport>
 */
class EvaluationReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'generated_by' => User::factory(),
            'report_type' => 'final',
            'summary' => fake()->paragraphs(2, true),
            'ranking_data' => [
                ['rank' => 1, 'vendor' => fake()->company(), 'score' => 92.5],
                ['rank' => 2, 'vendor' => fake()->company(), 'score' => 85.0],
                ['rank' => 3, 'vendor' => fake()->company(), 'score' => 78.3],
            ],
            'recommended_bid_id' => null,
            'status' => 'draft',
            'file_path' => null,
            'generated_at' => now(),
        ];
    }
}
