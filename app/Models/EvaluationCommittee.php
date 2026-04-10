<?php

namespace App\Models;

use App\Enums\CommitteeType;
use Database\Factories\EvaluationCommitteeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Group of evaluators assigned to score bids for a tender.
 *
 * Relationships: tender, members (users via committee_members).
 */
class EvaluationCommittee extends Model
{
    /** @use HasFactory<EvaluationCommitteeFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tender_id',
        'name',
        'committee_type',
        'status',
        'formed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'committee_type' => CommitteeType::class,
            'formed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'committee_members')
            ->withPivot('role', 'has_scored', 'scored_at')
            ->withTimestamps();
    }

    public function committeeMemberRecords(): HasMany
    {
        return $this->hasMany(CommitteeMember::class, 'committee_id');
    }
}
