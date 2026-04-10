<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $categories = [
            ['Civil Works', 'أعمال مدنية'],
            ['MEP', 'ميكانيك وكهرباء وسباكة'],
            ['Electrical', 'كهربائية'],
            ['Plumbing', 'سباكة'],
            ['HVAC', 'تكييف'],
            ['Finishing', 'تشطيبات'],
            ['Steelwork', 'أعمال حديدية'],
        ];

        $pick = fake()->randomElement($categories);

        return [
            'name_en' => $pick[0],
            'name_ar' => $pick[1],
            'parent_id' => null,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function child(Category $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
