<?php

namespace App\Models;

use Database\Factories\BoqSectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Top-level section of a tender's bill of quantities.
 *
 * Relationships: tender, items.
 */
class BoqSection extends Model
{
    /** @use HasFactory<BoqSectionFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tender_id',
        'title',
        'title_ar',
        'sort_order',
    ];

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoqItem::class, 'section_id');
    }
}
