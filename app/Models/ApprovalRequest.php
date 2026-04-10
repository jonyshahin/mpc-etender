<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Request for management approval against an evaluation report.
 *
 * Relationships: tender, report, requestedBy, decisions.
 */
class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tender_id',
        'report_id',
        'requested_by',
        'approval_type',
        'value_threshold',
        'approval_level',
        'status',
        'requested_at',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'approval_type' => ApprovalType::class,
            'status' => ApprovalStatus::class,
            'value_threshold' => 'decimal:2',
            'requested_at' => 'datetime',
            'deadline' => 'datetime',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(EvaluationReport::class, 'report_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class, 'request_id');
    }
}
