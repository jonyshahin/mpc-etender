<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(),
            'value' => fake()->word(),
            'group' => fake()->randomElement(['general', 'notifications', 'approvals', 'security', 'display']),
            'type' => 'string',
            'description' => fake()->sentence(),
            'updated_at' => now(),
            'updated_by' => null,
        ];
    }
}
