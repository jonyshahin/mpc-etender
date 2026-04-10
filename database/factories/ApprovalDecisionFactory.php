<?php

namespace Database\Factories;

use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalDecision>
 */
class ApprovalDecisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_id' => ApprovalRequest::factory(),
            'approver_id' => User::factory(),
            'decision' => fake()->randomElement(['approved', 'rejected', 'returned_for_revision']),
            'comments' => fake()->optional()->sentence(),
            'delegated_from' => null,
            'decided_at' => now(),
        ];
    }
}
