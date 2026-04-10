<?php

namespace Database\Factories;

use App\Enums\AwardStatus;
use App\Models\Award;
use App\Models\Bid;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'bid_id' => Bid::factory(),
            'vendor_id' => Vendor::factory(),
            'approved_by' => User::factory(),
            'award_amount' => fake()->randomFloat(2, 50000, 5000000),
            'currency' => 'USD',
            'justification' => fake()->paragraph(),
            'status' => AwardStatus::Pending,
            'letter_file_path' => null,
            'awarded_at' => now(),
            'notified_at' => null,
            'accepted_at' => null,
        ];
    }
}
