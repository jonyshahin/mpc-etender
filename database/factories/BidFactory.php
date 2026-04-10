<?php

namespace Database\Factories;

use App\Enums\BidStatus;
use App\Enums\EnvelopeType;
use App\Models\Bid;
use App\Models\Tender;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bid>
 */
class BidFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'vendor_id' => Vendor::factory(),
            'bid_reference' => 'BID-'.strtoupper(fake()->unique()->bothify('??-####')),
            'envelope_type' => EnvelopeType::Single,
            'encrypted_pricing_data' => null,
            'total_amount' => fake()->randomFloat(2, 10000, 5000000),
            'currency' => 'USD',
            'technical_notes' => fake()->optional()->paragraph(),
            'status' => BidStatus::Draft,
            'is_sealed' => true,
            'submitted_at' => null,
            'opened_at' => null,
            'opened_by' => null,
            'withdrawal_reason' => null,
            'submission_ip' => null,
            'submission_user_agent' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => BidStatus::Submitted,
            'submitted_at' => now(),
            'submission_ip' => fake()->ipv4(),
            'submission_user_agent' => fake()->userAgent(),
        ]);
    }

    public function opened(): static
    {
        return $this->state(fn () => [
            'status' => BidStatus::Opened,
            'is_sealed' => false,
            'submitted_at' => now()->subWeek(),
            'opened_at' => now(),
        ]);
    }
}
