<?php

namespace Database\Factories;

use App\Enums\TenderStatus;
use App\Enums\TenderType;
use App\Models\BoqItem;
use App\Models\BoqSection;
use App\Models\Category;
use App\Models\EvaluationCriterion;
use App\Models\Project;
use App\Models\Tender;
use App\Models\TenderDocument;
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

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => TenderStatus::Draft,
            'publish_date' => null,
            // Pin envelope mode so withCriteria() defaults align deterministically
            // with the publish prerequisite check (per-envelope coverage).
            'is_two_envelope' => false,
            'technical_pass_score' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => TenderStatus::Cancelled,
            'cancelled_reason' => 'Cancelled by factory state',
        ]);
    }

    public function awarded(): static
    {
        return $this->state(fn () => [
            'status' => TenderStatus::Awarded,
            'publish_date' => now()->subWeeks(4),
        ]);
    }

    public function twoEnvelope(int $passScore = 70): static
    {
        return $this->state(fn () => [
            'is_two_envelope' => true,
            'technical_pass_score' => $passScore,
        ]);
    }

    public function withBoq(int $sections = 1, int $itemsPerSection = 2): static
    {
        return $this->afterCreating(function (Tender $tender) use ($sections, $itemsPerSection) {
            for ($s = 0; $s < $sections; $s++) {
                $section = BoqSection::factory()->create([
                    'tender_id' => $tender->id,
                    'sort_order' => $s,
                ]);
                BoqItem::factory()->count($itemsPerSection)->create([
                    'section_id' => $section->id,
                ]);
            }
        });
    }

    public function withCriteria(int $count = 1, string $envelope = 'financial'): static
    {
        return $this->afterCreating(function (Tender $tender) use ($count, $envelope) {
            $per = intdiv(100, $count);
            $remainder = 100 - ($per * $count);
            for ($i = 0; $i < $count; $i++) {
                EvaluationCriterion::factory()->create([
                    'tender_id' => $tender->id,
                    'envelope' => $envelope,
                    'weight_percentage' => $per + ($i === 0 ? $remainder : 0),
                    'sort_order' => $i,
                ]);
            }
        });
    }

    public function withBoqAndCriteria(): static
    {
        return $this->withBoq()->withCriteria();
    }

    public function withDocument(int $count = 1): static
    {
        return $this->afterCreating(function (Tender $tender) use ($count) {
            TenderDocument::factory()->count($count)->create(['tender_id' => $tender->id]);
        });
    }

    public function withCategories(int $count = 1): static
    {
        return $this->afterCreating(function (Tender $tender) use ($count) {
            $categories = Category::factory()->count($count)->create();
            $tender->categories()->sync($categories->pluck('id')->toArray());
        });
    }
}
