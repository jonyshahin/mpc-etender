<?php

namespace Database\Factories;

use App\Enums\BidOpeningRequestStatus;
use App\Models\BidOpeningRequest;
use App\Models\Tender;
use App\Models\User;
use App\Services\BidOpeningRequestService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BidOpeningRequest>
 */
class BidOpeningRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'requested_by' => User::factory(),
            'authorizer_id' => User::factory(),
            'status' => BidOpeningRequestStatus::Pending,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(BidOpeningRequestService::WINDOW_MINUTES),
        ];
    }

    /** Past its window: still Pending, but no longer confirmable. */
    public function expired(): static
    {
        return $this->state(fn () => [
            'requested_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);
    }
}
