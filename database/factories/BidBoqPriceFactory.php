<?php

namespace Database\Factories;

use App\Models\Bid;
use App\Models\BidBoqPrice;
use App\Models\BoqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BidBoqPrice>
 */
class BidBoqPriceFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(4, 1, 5000);
        $quantity = fake()->randomFloat(3, 1, 1000);

        return [
            'bid_id' => Bid::factory(),
            'boq_item_id' => BoqItem::factory(),
            'unit_price' => $unitPrice,
            'total_price' => round($unitPrice * $quantity, 2),
            'remarks' => null,
        ];
    }
}
