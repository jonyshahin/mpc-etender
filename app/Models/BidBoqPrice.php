<?php

namespace App\Models;

use Database\Factories\BidBoqPriceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-line-item unit and total prices submitted by a vendor in a bid.
 *
 * Relationships: bid, boqItem.
 */
class BidBoqPrice extends Model
{
    /** @use HasFactory<BidBoqPriceFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'bid_id',
        'boq_item_id',
        'unit_price',
        'total_price',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'total_price' => 'decimal:2',
        ];
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }
}
