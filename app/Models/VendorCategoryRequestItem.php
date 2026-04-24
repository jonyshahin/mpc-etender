<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorCategoryRequestItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'request_id',
        'category_id',
        'operation',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(VendorCategoryRequest::class, 'request_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
