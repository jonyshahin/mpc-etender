<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\VendorDocStatus;
use Database\Factories\VendorDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prequalification document uploaded by a vendor, subject to review.
 *
 * Relationships: vendor, uploader, reviewer.
 */
class VendorDocument extends Model
{
    /** @use HasFactory<VendorDocumentFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'vendor_id',
        'uploaded_by',
        'document_type',
        'title',
        'file_path',
        'file_size',
        'mime_type',
        'issue_date',
        'expiry_date',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'status' => VendorDocStatus::class,
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** The MPC user who filed this on the vendor's behalf; null if the vendor did. */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
