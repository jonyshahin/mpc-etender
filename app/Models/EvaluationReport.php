<?php

namespace App\Models;

use Database\Factories\EvaluationReportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aggregated ranking and recommendation produced from committee scores.
 *
 * Relationships: tender, generatedBy, recommendedBid, approvalRequests.
 */
class EvaluationReport extends Model
{
    /** @use HasFactory<EvaluationReportFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tender_id',
        'generated_by',
        'report_type',
        'summary',
        'ranking_data',
        'recommended_bid_id',
        'status',
        'file_path',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'ranking_data' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function recommendedBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'recommended_bid_id');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'report_id');
    }
}
