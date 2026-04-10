<?php

namespace App\Models;

use App\Enums\TenderDocType;
use Database\Factories\TenderDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Specification, drawing, or other attachment for a tender with versioning.
 *
 * Relationships: tender, uploader.
 */
class TenderDocument extends Model
{
    /** @use HasFactory<TenderDocumentFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tender_id',
        'uploaded_by',
        'title',
        'file_path',
        'file_size',
        'mime_type',
        'doc_type',
        'version',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'doc_type' => TenderDocType::class,
            'is_current' => 'boolean',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
