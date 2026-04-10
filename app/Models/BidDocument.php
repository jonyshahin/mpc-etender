<?php

namespace App\Models;

use App\Enums\BidDocType;
use Database\Factories\BidDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Technical or financial attachment uploaded as part of a bid.
 *
 * Relationships: bid.
 */
class BidDocument extends Model
{
    /** @use HasFactory<BidDocumentFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'bid_id',
        'title',
        'file_path',
        'file_size',
        'mime_type',
        'doc_type',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'doc_type' => BidDocType::class,
            'uploaded_at' => 'datetime',
        ];
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }
}
