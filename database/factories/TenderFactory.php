<?php

namespace Database\Factories;

use App\Enums\TenderStatus;
use App\Enums\TenderType;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tender>
 */
class TenderFactory extends Factory
{
    public function definition(): array
    {
        $deadline = fake()->dateTimeBetween('+2 weeks', '+3 months');

        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'reference_number' => 'TND-'.strtoupper(fake()->unique()->bothify('??-####')),
            'title_en' => fake()->randomElement([
                'Supply and Installation of HVAC Systems',
                'Road Paving and Infrastructure Works',
                'Structural Steel Fabrication and Erection',
                'Electrical Substation Construction',
                'Water Supply Network Extension',
                'Interior Finishing and Fit-Out',
            ]),
            'title_ar' => null,
            'description_en' => fake()->paragraphs(2, true),
            'description_ar' => null,
            'tender_type' => fake()->randomElement(TenderType::cases()),
            'status' => TenderStatus::Draft,
            'estimated_value' => fake()->randomFloat(2, 50000, 5000000),
            'currency' => 'USD',
            'publish_date' => null,
            'submission_deadline' => $deadline,
            'opening_date' => (clone $deadline)->modify('+1 day'),
            'is_two_envelope' => fake()->boolean(30),
            'technical_pass_score' => 70.00,
            'requires_site_visit' => fake()->boolean(20),
            'site_visit_date' => null,
            'notes_internal' => null,
            'cancelled_reason' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => TenderStatus::Published,
            'publish_date' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => TenderStatus::SubmissionClosed,
            'publish_date' => now()->subMonth(),
            'submission_deadline' => now()->subDay(),
            'opening_date' => now(),
        ]);
    }
}
