<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;
use App\Models\EvaluationReport;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'report_id' => EvaluationReport::factory(),
            'requested_by' => User::factory(),
            'approval_type' => ApprovalType::Award,
            'value_threshold' => fake()->randomFloat(2, 50000, 500000),
            'approval_level' => 1,
            'status' => ApprovalStatus::Pending,
            'requested_at' => now(),
            'deadline' => now()->addDays(7),
        ];
    }
}
