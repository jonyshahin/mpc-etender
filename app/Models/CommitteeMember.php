<?php

namespace App\Models;

use App\Enums\CommitteeRole;
use Database\Factories\CommitteeMemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot record for a user serving on an evaluation committee with a role.
 *
 * Relationships: committee, user.
 */
class CommitteeMember extends Model
{
    /** @use HasFactory<CommitteeMemberFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'committee_id',
        'user_id',
        'role',
        'has_scored',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => CommitteeRole::class,
            'has_scored' => 'boolean',
            'scored_at' => 'datetime',
        ];
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(EvaluationCommittee::class, 'committee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
