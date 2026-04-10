<?php

namespace App\Models;

use Database\Factories\BoqItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Priced line item belonging to a BOQ section.
 *
 * Relationships: section, bidPrices.
 */
class BoqItem extends Model
{
    /** @use HasFactory<BoqItemFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'section_id',
        'item_code',
        'description_en',
        'description_ar',
        'unit',
        'quantity',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(BoqSection::class, 'section_id');
    }

    public function bidPrices(): HasMany
    {
        return $this->hasMany(BidBoqPrice::class, 'boq_item_id');
    }
}
