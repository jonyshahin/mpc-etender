<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UuidPivot extends Pivot
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}
