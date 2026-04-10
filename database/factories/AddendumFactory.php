<?php

namespace Database\Factories;

use App\Models\Addendum;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Addendum>
 */
class AddendumFactory extends Factory
{
    protected $model = Addendum::class;

    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'issued_by' => User::factory(),
            'addendum_number' => fake()->numberBetween(1, 5),
            'subject' => fake()->sentence(),
            'content_en' => fake()->paragraphs(2, true),
            'content_ar' => null,
            'file_path' => null,
            'extends_deadline' => false,
            'new_deadline' => null,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()]);
    }
}
