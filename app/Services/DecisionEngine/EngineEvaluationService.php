<?php

namespace App\Services\DecisionEngine;

use App\Enums\BidStatus;
use App\Enums\CommitteeType;
use App\Enums\EngineEvaluationStatus;
use App\Enums\TenderStatus;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\EngineBidAssessment;
use App\Models\EngineEvaluation;
use App\Models\Tender;
use App\Models\TenderEngineProfile;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Runs a tender's bids through the Decision Engine, in shadow mode.
 *
 * Shadow mode (INTEGRATION-DESIGN §1.1): nothing here touches the evaluation
 * report, the approval chain or the award. The engine's ranking is shown next
 * to the existing module's so the two can be compared; it decides nothing.
 *
 * Evaluation is a set operation — price and duration are scored relative to
 * the other bids — so it always goes through the batch endpoint, all bids at
 * once, and only after the dual-authorised opening has revealed the prices.
 */
class EngineEvaluationService
{
    /** Bid statuses that never enter a batch: unopened, pulled, or excluded. */
    private const EXCLUDED_BID_STATUSES = [
        BidStatus::Draft,
        BidStatus::Submitted,
        BidStatus::Withdrawn,
        BidStatus::Disqualified,
    ];

    public function __construct(
        private DecisionEngineClient $engine,
        private EvaluationService $evaluations,
    ) {}

    /**
     * The locked profile the engine scores this tender against, or null for a
     * tender that opened before engine scoring existed (§3.2).
     */
    public function lockedProfile(Tender $tender): ?TenderEngineProfile
    {
        $profile = $tender->engineProfile;

        return $profile?->isLocked() ? $profile : null;
    }

    /**
     * Why an evaluation cannot be run now, as a translation key, or null.
     *
     * Only UnderEvaluation: before the opening the prices are sealed, so there
     * is nothing to score, and running while bids still arrive would show a
     * ranking that shifts as competitors submit.
     */
    public function cannotRunReason(Tender $tender): ?string
    {
        if ($this->lockedProfile($tender) === null) {
            return 'engine.unavailable.opened_before_engine';
        }

        if ($tender->status !== TenderStatus::UnderEvaluation) {
            return in_array($tender->status, [TenderStatus::Published, TenderStatus::SubmissionClosed], true)
                ? 'engine.unavailable.not_opened'
                : 'engine.unavailable.evaluation_over';
        }

        // Two-envelope: price is scored relative to the other bids, so a bid
        // that will fail the technical envelope must not be in the set — it
        // would set the price benchmark for the ones that pass (§3.1).
        if ($tender->is_two_envelope && ! $this->technicalStage($tender)['complete']) {
            return 'engine.unavailable.technical_pending';
        }

        return null;
    }

    /** @return Collection<int, Bid> the bids a batch is built from, in reference order */
    public function evaluableBids(Tender $tender): Collection
    {
        $bids = $this->openedBids($tender);

        if (! $tender->is_two_envelope) {
            return $bids;
        }

        $stage = $this->technicalStage($tender);

        // Until committees have scored the technical envelope nothing can be
        // run anyway (cannotRunReason); show every bid meanwhile.
        return $stage['complete']
            ? $bids->reject(fn (Bid $bid) => in_array($bid->id, $stage['failed'], true))->values()
            : $bids;
    }

    /**
     * Bids left out of a two-envelope evaluation for falling below the
     * technical pass mark, so the screen can say who and why.
     *
     * @return Collection<int, Bid>
     */
    public function technicallyExcludedBids(Tender $tender): Collection
    {
        if (! $tender->is_two_envelope) {
            return collect();
        }

        $stage = $this->technicalStage($tender);

        return $stage['complete']
            ? $this->openedBids($tender)->filter(fn (Bid $bid) => in_array($bid->id, $stage['failed'], true))->values()
            : collect();
    }

    /**
     * Where the existing module's technical envelope stands, read without
     * changing anything.
     *
     * The module's own "complete technical" step disqualifies bids below the
     * pass mark but records no completion, so the stage is judged from the
     * scores themselves: complete when every opened bid carries technical
     * committee scores, and a bid fails when its technical total is under
     * the tender's pass mark.
     *
     * @return array{complete: bool, failed: array<int, string>}
     */
    private function technicalStage(Tender $tender): array
    {
        $bids = $this->openedBids($tender);
        $technical = collect($this->evaluations->aggregateScores($tender, CommitteeType::Technical->criterionEnvelopes()))
            ->keyBy('bid_id');

        $complete = $bids->isNotEmpty()
            && $bids->every(fn (Bid $bid) => ($technical[$bid->id]['criteria_scores'] ?? []) !== []);

        $passMark = (float) ($tender->technical_pass_score ?? 0);

        return [
            'complete' => $complete,
            'failed' => $complete
                ? $bids->filter(fn (Bid $bid) => (float) $technical[$bid->id]['weighted_total'] < $passMark)->pluck('id')->all()
                : [],
        ];
    }

    /** @return Collection<int, Bid> every bid that has been opened and is still in contention */
    private function openedBids(Tender $tender): Collection
    {
        return $tender->bids()
            ->whereNotIn('status', array_map(fn (BidStatus $s) => $s->value, self::EXCLUDED_BID_STATUSES))
            ->where('is_sealed', false)
            ->with([
                'vendor:id,company_name',
                'engineAssessment.scorer:id,name',
                'engineAssessment.bankGuaranteeAttester:id,name',
                'engineAssessment.mandatoryDocumentsAttester:id,name',
            ])
            ->orderBy('bid_reference')
            ->get();
    }

    /**
     * What each bid still needs before the engine can score it completely.
     *
     * Checked before sending rather than left to the engine: a missing officer
     * score is eTender's gap, not a result, and sending it would come back as
     * a provisional ranking that looks like an outcome.
     *
     * @return array<string, array<int, string>> bid id => missing item keys
     */
    public function missing(TenderEngineProfile $profile, Collection $bids): array
    {
        $weights = $profile->weights();
        $missing = [];

        foreach ($bids as $bid) {
            $assessment = $bid->engineAssessment;
            $gaps = [];

            if ($bid->duration_days === null) {
                $gaps[] = 'duration';
            }

            foreach (EngineBidAssessment::SCORES as $criterion => $column) {
                if ($weights[$criterion] > 0 && $assessment?->{$column} === null) {
                    $gaps[] = $criterion;
                }
            }

            foreach (EngineBidAssessment::GATES as $gate => $column) {
                if ($profile->{'gate_'.$gate} && $assessment?->{$column} === null) {
                    $gaps[] = $gate;
                }
            }

            if ($gaps !== []) {
                $missing[$bid->id] = $gaps;
            }
        }

        return $missing;
    }

    /**
     * Record an officer's scores and gate attestations for one bid.
     *
     * Each gate carries who attested it and when (§1.3); a score set carries
     * who last entered it. Only fields actually sent are touched, and a gate's
     * attestation is re-stamped only when its value changes.
     */
    public function saveAssessment(Bid $bid, array $data, User $officer): EngineBidAssessment
    {
        $assessment = EngineBidAssessment::firstOrNew(
            ['bid_id' => $bid->id],
            ['tender_id' => $bid->tender_id],
        );

        $scoresChanged = false;
        foreach (EngineBidAssessment::SCORES as $column) {
            if (array_key_exists($column, $data)) {
                $value = $data[$column] === null ? null : round((float) $data[$column], 2);
                $current = $assessment->{$column} === null ? null : (float) $assessment->{$column};
                if ($value !== $current) {
                    $assessment->{$column} = $value;
                    $scoresChanged = true;
                }
            }
        }

        if ($scoresChanged) {
            $assessment->scored_by = $officer->id;
            $assessment->scored_at = now();
        }

        foreach (EngineBidAssessment::GATES as $column) {
            if (array_key_exists($column, $data)) {
                $value = $data[$column] === null ? null : (bool) $data[$column];
                if ($value !== $assessment->{$column}) {
                    $assessment->{$column} = $value;
                    $assessment->{$column.'_attested_by'} = $value === null ? null : $officer->id;
                    $assessment->{$column.'_attested_at'} = $value === null ? null : now();
                }
            }
        }

        $assessment->save();

        return $assessment;
    }

    /**
     * Submit every evaluable bid to the engine as one batch.
     *
     * Nothing is written in eTender until the engine has accepted the batch
     * and returned its id, so an unreachable engine leaves no trace and a
     * retry starts clean (§6). The Idempotency-Key is a hash of the exact
     * request: an unchanged retry carries the same key.
     *
     * @throws ValidationException when a bid is not ready, or a batch is still running
     * @throws DecisionEngineException when the engine cannot be reached or refuses
     */
    public function submit(Tender $tender, User $officer): EngineEvaluation
    {
        if ($reason = $this->cannotRunReason($tender)) {
            throw ValidationException::withMessages(['evaluation' => __($reason)]);
        }

        // One submission per tender at a time: the engine does not yet honour
        // Idempotency-Key on batches, so two clicks must not become two batches.
        try {
            $wait = (int) config('services.decision_engine.submit_lock_wait_seconds', 10);

            return Cache::lock("engine-evaluation:{$tender->id}", 30)->block($wait, function () use ($tender, $officer) {
                $latest = $tender->engineEvaluations()->first();

                if ($latest !== null && ! $latest->isFinal()) {
                    // Includes a stuck batch: the engine refuses to supersede an
                    // unfinished one, so a second would leave two live evaluations
                    // for one tender with no link between them (§9).
                    throw ValidationException::withMessages(['evaluation' => __('engine.run.already_running')]);
                }

                $profile = $this->lockedProfile($tender);
                $bids = $this->evaluableBids($tender);

                if ($bids->isEmpty()) {
                    throw ValidationException::withMessages(['evaluation' => __('engine.run.no_bids')]);
                }

                if ($missing = $this->missing($profile, $bids)) {
                    throw ValidationException::withMessages(['evaluation' => __('engine.run.incomplete', ['count' => count($missing)])]);
                }

                $request = $this->buildRequest($tender, $profile, $bids, $latest);
                $key = hash('sha256', EngineProfileService::canonical($request));

                $accepted = $this->engine->submitBatch($request, $key);

                return DB::transaction(function () use ($tender, $profile, $request, $key, $accepted, $latest, $officer) {
                    $evaluation = EngineEvaluation::firstOrCreate(
                        ['batch_id' => $accepted['batch_id']],
                        [
                            'tender_id' => $tender->id,
                            'tender_engine_profile_id' => $profile->id,
                            'idempotency_key' => $key,
                            'supersedes_batch_id' => $latest?->batch_id,
                            'request' => $request,
                            'status' => EngineEvaluationStatus::tryFrom($accepted['status'] ?? '') ?? EngineEvaluationStatus::Queued,
                            'submitted_by' => $officer->id,
                            'submitted_at' => now(),
                        ],
                    );

                    if ($evaluation->wasRecentlyCreated) {
                        AuditLog::create([
                            'user_id' => $officer->id,
                            'auditable_type' => Tender::class,
                            'auditable_id' => $tender->id,
                            'action' => 'engine_evaluation_submitted',
                            'old_values' => null,
                            'new_values' => [
                                'batch_id' => $evaluation->batch_id,
                                'supersedes' => $evaluation->supersedes_batch_id,
                                'bids' => count($request['items']),
                            ],
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'created_at' => now(),
                        ]);
                    }

                    return $evaluation;
                });
            });
        } catch (LockTimeoutException) {
            // Another submission for this tender held the lock throughout:
            // it is still talking to the engine. Say so, rather than a 500.
            throw ValidationException::withMessages(['evaluation' => __('engine.run.already_running')]);
        }
    }

    /**
     * Ask the engine how a batch is doing, and keep the result once it is
     * final. A final result is never fetched again — it cannot change.
     *
     * @throws DecisionEngineException when the engine cannot be reached; the
     *                                 stored state is left exactly as it was
     */
    public function refresh(EngineEvaluation $evaluation): EngineEvaluation
    {
        if ($evaluation->isFinal()) {
            return $evaluation;
        }

        $batch = $this->engine->batch($evaluation->batch_id);
        $status = EngineEvaluationStatus::tryFrom((string) ($batch['status'] ?? ''));

        // A batch whose profile is not this tender's is not this tender's
        // evaluation, whatever id it was fetched by. Refuse to store it.
        $expectedProfile = $evaluation->profile->profile_key;
        if (($batch['profile']['key'] ?? null) !== $expectedProfile || $status === null) {
            throw DecisionEngineException::unavailable(__('engine.error.unexpected_batch'));
        }

        if ($status->isFinal()) {
            $evaluation->update([
                'status' => $status,
                'result' => $batch,
                'finalised_at' => isset($batch['completed_at']) ? Carbon::parse($batch['completed_at']) : now(),
                'last_checked_at' => now(),
            ]);
        } else {
            $evaluation->update([
                'status' => $status,
                'last_checked_at' => now(),
            ]);
        }

        return $evaluation;
    }

    /**
     * The batch request for these bids. Bid references identify the items, so
     * the engine's record reads the same as eTender's screens.
     */
    public function buildRequest(Tender $tender, TenderEngineProfile $profile, Collection $bids, ?EngineEvaluation $previous): array
    {
        $weights = $profile->weights();

        $items = $bids->map(function (Bid $bid) use ($profile, $weights) {
            $assessment = $bid->engineAssessment;

            $scores = [];
            foreach (EngineBidAssessment::SCORES as $criterion => $column) {
                if ($weights[$criterion] > 0) {
                    $scores[$criterion] = (float) $assessment->{$column};
                }
            }

            $gates = [];
            foreach (EngineBidAssessment::GATES as $gate => $column) {
                if ($profile->{'gate_'.$gate}) {
                    $gates[$gate] = (bool) $assessment->{$column};
                }
            }

            $input = [
                'bidder_name' => $bid->vendor?->company_name ?? $bid->bid_reference,
                'bid_amount' => (float) $bid->total_amount,
                'currency' => $bid->currency,
                'duration_days' => (int) $bid->duration_days,
                'bid_date' => ($bid->submitted_at ?? $bid->created_at)->toDateString(),
            ];
            if (filled($bid->technical_notes)) {
                $input['technical_notes'] = $bid->technical_notes;
            }

            return array_filter([
                'reference' => $bid->bid_reference,
                'input' => $input,
                'scores' => $scores ?: null,
                'gates' => $gates ?: null,
            ], fn ($v) => $v !== null);
        })->values()->all();

        $request = [
            'schema_key' => $profile->schema_key,
            'schema_version' => $profile->schema_version,
            'profile_key' => $profile->profile_key,
            'items' => $items,
            'context' => $profile->definition['context'] ?? ['tender_number' => $tender->reference_number],
            'rank' => true,
        ];

        // A re-run cites the evaluation it replaces, which the engine requires
        // to be finalised and on the same profile; it computes its own baseline.
        if ($previous !== null && $previous->isFinal()) {
            $request['supersedes'] = $previous->batch_id;
        }

        return $request;
    }
}
