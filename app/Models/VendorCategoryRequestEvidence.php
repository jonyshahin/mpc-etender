<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorCategoryRequestEvidence extends Model
{
    use HasUuids;

    protected $table = 'vendor_category_request_evidence';

    protected $fillable = [
        'request_id',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by_vendor_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(VendorCategoryRequest::class, 'request_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'uploaded_by_vendor_id');
    }
}
