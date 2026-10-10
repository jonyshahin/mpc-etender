<?php

namespace App\Models;

use App\Enums\EngineContractType;
use App\Enums\TimelineFormula;
use Database\Factories\TenderEngineProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A tender's engine scoring criteria: a working copy while the tender is a
 * draft, frozen once the engine confirms the lock.
 *
 * Relationships: tender, locker.
 */
class TenderEngineProfile extends Model
{
    /** @use HasFactory<TenderEngineProfileFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'tender_id',
        'template_key',
        'weight_price',
        'weight_technical',
        'weight_timeline',
        'weight_references',
        'weight_payment_flexibility',
        'gate_bank_guarantee',
        'gate_mandatory_documents',
        'margin_below',
        'contract_type',
        'contract_type_label',
        'timeline_formula',
        'schema_key',
        'schema_version',
        'profile_key',
        'definition',
        'locked_at',
        'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'weight_price' => 'integer',
            'weight_technical' => 'integer',
            'weight_timeline' => 'integer',
            'weight_references' => 'integer',
            'weight_payment_flexibility' => 'integer',
            'gate_bank_guarantee' => 'boolean',
            'gate_mandatory_documents' => 'boolean',
            'margin_below' => 'decimal:2',
            'contract_type' => EngineContractType::class,
            'timeline_formula' => TimelineFormula::class,
            'schema_version' => 'integer',
            'definition' => 'array',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Once locked, never changed — the model layer's half of the lock, behind
     * the engine's own refusal. A locked row that could be edited here would
     * let eTender display weights the engine never used.
     */
    protected static function booted(): void
    {
        // A seeded contract type fixes its own timeline formula; only `other`
        // takes the officer's explicit choice (INTEGRATION-DESIGN §1.4). Set
        // here so no form or request can store a pairing that disagrees.
        static::saving(function (self $profile) {
            $fixed = $profile->contract_type?->timelineFormula();

            if ($fixed !== null) {
                $profile->timeline_formula = $fixed;
            }

            if ($profile->contract_type !== EngineContractType::Other) {
                $profile->contract_type_label = null;
            }
        });

        static::updating(function (self $profile) {
            if ($profile->getOriginal('locked_at') !== null) {
                throw new LogicException("Tender engine profile {$profile->id} is locked and cannot be changed.");
            }
        });

        static::deleting(function (self $profile) {
            if ($profile->locked_at !== null) {
                throw new LogicException("Tender engine profile {$profile->id} is locked and cannot be deleted.");
            }
        });
    }

    /** A copy of the standard template for a new tender. */
    public static function fromTemplate(Tender $tender, ?EvaluationCriteriaTemplate $template = null): self
    {
        $template ??= EvaluationCriteriaTemplate::standard();

        return new self([
            'tender_id' => $tender->id,
            'template_key' => $template->key,
            'weight_price' => $template->weight_price,
            'weight_technical' => $template->weight_technical,
            'weight_timeline' => $template->weight_timeline,
            'weight_references' => $template->weight_references,
            'weight_payment_flexibility' => $template->weight_payment_flexibility,
            'gate_bank_guarantee' => $template->gate_bank_guarantee,
            'gate_mandatory_documents' => $template->gate_mandatory_documents,
            'margin_below' => $template->margin_below,
        ]);
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** @return array<string, int> criterion key => whole-percentage weight */
    public function weights(): array
    {
        return array_map(fn (string $column) => (int) $this->{$column}, EvaluationCriteriaTemplate::WEIGHTS);
    }

    /**
     * Why this profile cannot be sent to the engine yet, or null when it can.
     * Shown to the officer as the reason publishing is blocked.
     */
    public function incompleteReason(): ?string
    {
        if (array_sum($this->weights()) !== 100) {
            return __('engine.publish.weights_not_100');
        }

        if ($this->contract_type === null) {
            return __('engine.publish.no_contract_type');
        }

        if ($this->timeline_formula === null) {
            return __('engine.publish.no_timeline_formula');
        }

        if ($this->contract_type === EngineContractType::Other && blank($this->contract_type_label)) {
            return __('engine.publish.no_contract_type_label');
        }

        return null;
    }
}
