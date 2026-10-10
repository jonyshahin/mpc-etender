<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\SaveEngineAssessmentRequest;
use App\Models\Bid;
use App\Models\EngineBidAssessment;
use App\Models\EngineEvaluation;
use App\Models\Tender;
use App\Services\DecisionEngine\DecisionEngineException;
use App\Services\DecisionEngine\EngineEvaluationService;
use App\Services\DecisionEngine\EngineResultPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Engine scoring for one tender: enter scores, run, and read the result next
 * to the existing evaluation. Shadow mode — nothing here reaches the award.
 */
class EngineEvaluationController extends Controller
{
    public function __construct(
        private EngineEvaluationService $evaluations,
        private EngineResultPresenter $presenter,
    ) {}

    public function show(Request $request, Tender $tender): Response
    {
        $this->authorize('viewEngineEvaluation', $tender);

        $profile = $this->evaluations->lockedProfile($tender);
        $canRun = $request->user()->can('runEngineEvaluation', $tender);
        $bids = $profile ? $this->evaluations->evaluableBids($tender) : collect();
        $missing = $profile ? $this->evaluations->missing($profile, $bids) : [];

        $evaluations = $tender->engineEvaluations()->with(['submitter:id,name', 'profile'])->get();
        $latest = $evaluations->first();
        $latestFinal = $evaluations->first(fn (EngineEvaluation $e) => $e->isFinal());
        $result = $latestFinal ? $this->presenter->result($latestFinal, $bids->keyBy('bid_reference')) : null;

        return Inertia::render('evaluation/EngineEvaluation', [
            'tender' => $tender->only('id', 'reference_number', 'title_en', 'title_ar', 'status', 'currency'),
            'unavailableReason' => $profile ? null : 'engine.unavailable.opened_before_engine',
            'runBlockedReason' => $this->evaluations->cannotRunReason($tender),
            'canRun' => $canRun,
            'profile' => $profile ? $this->profileProps($profile) : null,
            'bids' => $bids->map(fn (Bid $bid) => $this->bidProps($bid, $missing[$bid->id] ?? []))->values(),
            // Two-envelope bids below the technical pass mark: left out of the
            // engine's set so they do not set the price benchmark (§3.1).
            'technicallyExcluded' => $profile
                ? $this->evaluations->technicallyExcludedBids($tender)
                    ->map(fn (Bid $bid) => ['reference' => $bid->bid_reference, 'vendorName' => $bid->vendor?->company_name])
                    ->values()
                : [],
            'technicalPassScore' => $tender->is_two_envelope ? (float) $tender->technical_pass_score : null,
            'latest' => $latest ? $this->evaluationProps($latest) : null,
            'history' => $evaluations->map(fn (EngineEvaluation $e) => $this->evaluationProps($e))->values(),
            'result' => $result,
            'comparison' => $profile ? $this->presenter->comparison($tender, $result, $bids) : null,
        ]);
    }

    public function saveAssessment(SaveEngineAssessmentRequest $request, Tender $tender, Bid $bid): RedirectResponse
    {
        abort_unless($bid->tender_id === $tender->id, 404);

        if ($reason = $this->evaluations->cannotRunReason($tender)) {
            throw ValidationException::withMessages(['assessment' => __($reason)]);
        }

        $this->evaluations->saveAssessment($bid, $request->validated(), $request->user());

        return back();
    }

    public function run(Request $request, Tender $tender): RedirectResponse
    {
        $this->authorize('runEngineEvaluation', $tender);

        try {
            $this->evaluations->submit($tender, $request->user());
        } catch (DecisionEngineException $e) {
            // Nothing was recorded: the batch either never reached the engine
            // or its id never came back. Retrying is safe (§6).
            Inertia::flash('toast', ['type' => 'error', 'message' => __('engine.run.failed', ['reason' => $e->getMessage()])]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('engine.run.submitted')]);

        return back();
    }

    /**
     * Polled by the results screen while a batch is running. Returns the state
     * only; the screen reloads its props once the batch has finalised.
     */
    public function status(Request $request, Tender $tender, EngineEvaluation $evaluation): JsonResponse
    {
        $this->authorize('viewEngineEvaluation', $tender);
        abort_unless($evaluation->tender_id === $tender->id, 404);

        $unreachable = null;

        try {
            $this->evaluations->refresh($evaluation);
        } catch (DecisionEngineException $e) {
            // The stored state is untouched; say so rather than guessing.
            $unreachable = $e->getMessage();
        }

        return response()->json($this->evaluationProps($evaluation->fresh(['submitter:id,name']), $unreachable));
    }

    private function profileProps($profile): array
    {
        return [
            'profileKey' => $profile->profile_key,
            'lockedAt' => $profile->locked_at?->toIso8601String(),
            'lockedBy' => $profile->locker?->name,
            'weights' => $profile->weights(),
            'gates' => [
                'bank_guarantee' => $profile->gate_bank_guarantee,
                'mandatory_documents' => $profile->gate_mandatory_documents,
            ],
            'marginBelow' => (float) $profile->margin_below,
            'contractType' => $profile->contract_type?->value,
            'contractTypeLabel' => $profile->contract_type_label,
            'contractTypeLabelKey' => $profile->contract_type?->labelKey(),
            'timelineFormula' => $profile->timeline_formula?->value,
            'timelineFormulaLabelKey' => $profile->timeline_formula?->labelKey(),
            'timelineFormulaExplanationKey' => $profile->timeline_formula?->explanationKey(),
        ];
    }

    private function bidProps(Bid $bid, array $missing): array
    {
        $a = $bid->engineAssessment;
        $attesters = [
            'bank_guarantee' => $a?->bankGuaranteeAttester?->name,
            'mandatory_documents' => $a?->mandatoryDocumentsAttester?->name,
        ];

        return [
            'id' => $bid->id,
            'reference' => $bid->bid_reference,
            'vendorName' => $bid->vendor?->company_name,
            // Opened bids only reach this list, so the price is no longer sealed.
            'amount' => (float) $bid->total_amount,
            'currency' => $bid->currency,
            'durationDays' => $bid->duration_days,
            'missing' => $missing,
            'assessment' => [
                'technical_score' => $a?->technical_score === null ? null : (float) $a->technical_score,
                'references_score' => $a?->references_score === null ? null : (float) $a->references_score,
                'payment_flexibility_score' => $a?->payment_flexibility_score === null ? null : (float) $a->payment_flexibility_score,
                'scoredBy' => $a?->scorer?->name,
                'scoredAt' => $a?->scored_at?->toIso8601String(),
                'gates' => collect(EngineBidAssessment::GATES)->mapWithKeys(fn ($column, $gate) => [$gate => [
                    'value' => $a?->{$column},
                    'attestedBy' => $attesters[$gate],
                    'attestedAt' => $a?->{$column.'_attested_at'}?->toIso8601String(),
                ]])->all(),
            ],
        ];
    }

    private function evaluationProps(EngineEvaluation $evaluation, ?string $unreachable = null): array
    {
        return [
            'id' => $evaluation->id,
            'batchId' => $evaluation->batch_id,
            'status' => $evaluation->status->value,
            'final' => $evaluation->isFinal(),
            'stuck' => $evaluation->isStuck(),
            'submittedAt' => $evaluation->submitted_at->toIso8601String(),
            'submittedBy' => $evaluation->submitter?->name,
            'finalisedAt' => $evaluation->finalised_at?->toIso8601String(),
            'lastCheckedAt' => $evaluation->last_checked_at?->toIso8601String(),
            'supersedes' => $evaluation->supersedes_batch_id,
            'unreachable' => $unreachable,
            'stuckAfterSeconds' => (int) config('services.decision_engine.stuck_after_seconds'),
        ];
    }
}
