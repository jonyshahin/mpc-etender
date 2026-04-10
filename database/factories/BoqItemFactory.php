<?php

namespace Database\Factories;

use App\Models\BoqItem;
use App\Models\BoqSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqItem>
 */
class BoqItemFactory extends Factory
{
    public function definition(): array
    {
        $items = [
            ['Reinforced Concrete Grade 40', 'm³'],
            ['Steel Rebar 16mm', 'kg'],
            ['HDPE Pipe 200mm', 'lm'],
            ['Ceramic Floor Tiles 60x60', 'm²'],
            ['Electrical Cable 3x2.5mm²', 'lm'],
            ['Paint (2 coats emulsion)', 'm²'],
            ['Excavation in Rock', 'm³'],
            ['Formwork to Columns', 'm²'],
        ];

        $pick = fake()->randomElement($items);

        return [
            'section_id' => BoqSection::factory(),
            'item_code' => strtoupper(fake()->bothify('??-###')),
            'description_en' => $pick[0],
            'description_ar' => null,
            'unit' => $pick[1],
            'quantity' => fake()->randomFloat(3, 1, 10000),
            'sort_order' => fake()->numberBetween(0, 50),
            'notes' => null,
        ];
    }
}
