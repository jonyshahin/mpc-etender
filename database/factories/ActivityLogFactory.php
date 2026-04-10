<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'vendor_id' => null,
            'description' => fake()->sentence(),
            'subject_type' => null,
            'subject_id' => null,
            'properties' => null,
            'created_at' => now(),
        ];
    }
}
