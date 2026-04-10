<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    private static int $counter = 0;

    public function definition(): array
    {
        $names = [
            'Baghdad Central Hospital Expansion',
            'Basra Port Terminal Upgrade',
            'Erbil International Airport Renovation',
            'Najaf City Center Development',
            'Karbala Water Treatment Plant',
            'Mosul Bridge Reconstruction',
            'Sulaymaniyah Industrial Zone',
            'Kirkuk Oil Refinery Maintenance',
        ];

        $name = $names[self::$counter % count($names)] ?? fake()->company().' Project';
        self::$counter++;

        return [
            'name' => $name,
            'name_ar' => null,
            'code' => 'PRJ-'.strtoupper(fake()->unique()->bothify('??###')),
            'description' => fake()->paragraph(),
            'location' => fake()->city().', Iraq',
            'client_name' => fake()->company(),
            'status' => 'active',
            'start_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'end_date' => fake()->dateTimeBetween('+6 months', '+3 years'),
            'created_by' => User::factory(),
        ];
    }
}
