<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Which engine schema version holds which content of eTender's schema file.
 * See the migration for why this cannot be read back from the engine.
 */
class DecisionEngineSchema extends Model
{
    use HasUuids;

    protected $fillable = ['key', 'content_hash', 'version'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
