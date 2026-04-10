<?php

namespace App\Models;

use App\Enums\TenderStatus;
use App\Enums\TenderType;
use Database\Factories\TenderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tender record belonging to a project, with deadlines and envelope configuration.
 *
 * Relationships: project, creator, categories, documents, boqSections, addenda,
 *   clarifications, bids, evaluationCriteria, committees, reports, awards.
 */
class Tender extends Model
{
    /** @use HasFactory<TenderFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'created_by',
        'reference_number',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'tender_type',
        'status',
        'estimated_value',
        'currency',
        'publish_date',
        'submission_deadline',
        'opening_date',
        'is_two_envelope',
        'technical_pass_score',
        'requires_site_visit',
        'site_visit_date',
        'notes_internal',
        'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'tender_type' => TenderType::class,
            'status' => TenderStatus::class,
            'estimated_value' => 'decimal:2',
            'publish_date' => 'datetime',
            'submission_deadline' => 'datetime',
            'opening_date' => 'datetime',
            'is_two_envelope' => 'boolean',
            'technical_pass_score' => 'decimal:2',
            'requires_site_visit' => 'boolean',
            'site_visit_date' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'tender_categories')
            ->using(Concerns\UuidPivot::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TenderDocument::class);
    }

    public function boqSections(): HasMany
    {
        return $this->hasMany(BoqSection::class);
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(Addendum::class);
    }

    public function clarifications(): HasMany
    {
        return $this->hasMany(Clarification::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function evaluationCriteria(): HasMany
    {
        return $this->hasMany(EvaluationCriterion::class);
    }

    public function committees(): HasMany
    {
        return $this->hasMany(EvaluationCommittee::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(EvaluationReport::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    // ── Scopes ──

    public function scopePublished($query)
    {
        return $query->where('status', TenderStatus::Published);
    }

    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeOpenForBids($query)
    {
        return $query->where('status', TenderStatus::Published)
            ->where('submission_deadline', '>', now());
    }

    public function scopeDeadlineSoon($query, int $days = 3)
    {
        return $query->where('status', TenderStatus::Published)
            ->whereBetween('submission_deadline', [now(), now()->addDays($days)]);
    }

    // ── Accessors ──

    public function getIsOpenForSubmissionAttribute(): bool
    {
        return $this->status === TenderStatus::Published
            && $this->submission_deadline->isFuture();
    }
}
