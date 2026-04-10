<?php

namespace Database\Factories;

use App\Enums\BidDocType;
use App\Models\Bid;
use App\Models\BidDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BidDocument>
 */
class BidDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bid_id' => Bid::factory(),
            'title' => fake()->words(3, true),
            'file_path' => 'bid-docs/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(50000, 10000000),
            'mime_type' => 'application/pdf',
            'doc_type' => fake()->randomElement(BidDocType::cases()),
            'uploaded_at' => now(),
        ];
    }
}
