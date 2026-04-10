<?php

namespace Database\Factories;

use App\Models\BoqSection;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqSection>
 */
class BoqSectionFactory extends Factory
{
    public function definition(): array
    {
        $sections = ['Preliminaries', 'Substructure', 'Superstructure', 'Finishes', 'MEP', 'External Works'];

        return [
            'tender_id' => Tender::factory(),
            'title' => fake()->randomElement($sections),
            'title_ar' => null,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
