<?php

namespace App\Models;

use Database\Factories\AddendumFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Official tender amendment issued after publication.
 *
 * Relationships: tender, issuer.
 */
class Addendum extends Model
{
    /** @use HasFactory<AddendumFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'addenda';

    protected $fillable = [
        'tender_id',
        'issued_by',
        'addendum_number',
        'subject',
        'content_en',
        'content_ar',
        'file_path',
        'extends_deadline',
        'new_deadline',
        'new_opening_date',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'extends_deadline' => 'boolean',
            'new_deadline' => 'datetime',
            'new_opening_date' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
