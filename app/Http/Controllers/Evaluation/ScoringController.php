<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\StoreScoresRequest;
use App\Models\Bid;
use App\Models\CommitteeMember;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\Tender;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScoringController extends Controller
{
    public function __construct(
        private readonly EvaluationService $evaluationService,
    ) {}

    /**
     * {tender} and {bid} are bound independently by the router.
     *
     * Nothing tied them together, so a committee member on tender A could
     * pass a bid from tender B and read or score it.
     */
    private function assertBidBelongsTo(Tender $tender, Bid $bid): void
    {
        abort_unless($bid->tender_id === $tender->id, 404);
    }

    /**
     * Mark the evaluator done — but only once every bid really is scored.
     *
     * 'complete' arrives from the ScoreBid screen, which scores ONE bid, and
     * the old handler set has_scored on every membership for the tender. So
     * finishing the first bid showed the evaluator as done while the rest
     * still read 'Not scored', and the committee screen agreed.
     *
     * @param  Collection<string, EvaluationCriterion>  $scorable
     */
    private function markCompleteIfEverythingScored(Tender $tender, Bid $bid, User $user, $scorable): void
    {
        $bidIds = $tender->bids()
            ->whereNotIn('status', ['withdrawn', 'disqualified'])
            ->pluck('id');

        $criterionIds = $scorable->keys();
        $required = $bidIds->count() * $criterionIds->count();

        if ($required === 0) {
            return;
        }

        $written = EvaluationScore::where('evaluator_id', $user->id)
            ->whereIn('bid_id', $bidIds)
            ->whereIn('criterion_id', $criterionIds)
            ->count();

        if ($written < $required) {
            return;
        }

        CommitteeMember::whereHas('committee', fn ($q) => $q->where('tender_id', $tender->id))
            ->where('user_id', $user->id)
            ->update(['has_scored' => true, 'scored_at' => now()]);
    }

    public function index(Request $request, Tender $tender): Response
    {
        // EvaluationScorePolicy::score() already checked permission *and*
        // committee membership; this controller simply never called it. Without
        // it, a user on no committee fell through to the 'technical' default
        // below and the page rendered every bid on the tender.
        $this->authorize('score', [EvaluationScore::class, $tender]);

        $user = $request->user();

        // Get committees the user belongs to for this tender
        $memberRecords = CommitteeMember::whereHas('committee', fn ($q) => $q->where('tender_id', $tender->id))
            ->where('user_id', $user->id)
            ->with('committee')
            ->get();

        $envelopes = $this->evaluationService->scorableEnvelopes(
            $tender,
            $memberRecords->pluck('committee.committee_type')->filter(),
        );

        $criteria = $tender->evaluationCriteria()
            ->whereIn('envelope', $envelopes)
            ->orderBy('sort_order')
            ->get();

        $bids = $tender->bids()
            ->with('vendor:id,company_name')
            // Only what the page shows. Whole Bid models also carried
            // submission_ip, submission_user_agent, technical_notes and
            // withdrawal_reason into the props of a screen that displays none
            // of them.
            ->select('id', 'vendor_id', 'bid_reference', 'status')
            ->whereNotIn('status', ['withdrawn', 'disqualified'])
            ->get();

        // Scoped to the criteria actually on screen. Ungated, this returned
        // every score the evaluator had written across all envelopes, and the
        // page compared its length against a filtered criteria list — so a
        // part-scored bid could show a green tick and a finished one could
        // read 'Not scored'.
        $existingScores = EvaluationScore::where('evaluator_id', $user->id)
            ->whereIn('bid_id', $bids->pluck('id'))
            ->whereIn('criterion_id', $criteria->pluck('id'))
            ->get()
            ->groupBy('bid_id');

        return Inertia::render('evaluation/Scoring', [
            'tender' => $tender->only('id', 'reference_number', 'title_en', 'is_two_envelope'),
            'criteria' => $criteria,
            'bids' => $bids,
            'existingScores' => $existingScores,
            'envelopes' => $envelopes,
            'hasCompleted' => $memberRecords->every(fn ($m) => $m->has_scored) && $memberRecords->isNotEmpty(),
        ]);
    }

    public function scoreBid(Request $request, Tender $tender, Bid $bid): Response
    {
        $this->authorize('score', [EvaluationScore::class, $tender]);

        $user = $request->user();

        $memberRecords = CommitteeMember::whereHas('committee', fn ($q) => $q->where('tender_id', $tender->id))
            ->where('user_id', $user->id)
            ->with('committee')
            ->get();

        $envelopes = $this->evaluationService->scorableEnvelopes(
            $tender,
            $memberRecords->pluck('committee.committee_type')->filter(),
        );

        $criteria = $tender->evaluationCriteria()
            ->whereIn('envelope', $envelopes)
            ->orderBy('sort_order')
            ->get();

        $this->assertBidBelongsTo($tender, $bid);

        $existingScores = EvaluationScore::where('evaluator_id', $user->id)
            ->where('bid_id', $bid->id)
            ->get()
            ->keyBy('criterion_id');

        return Inertia::render('evaluation/ScoreBid', [
            'tender' => $tender->only('id', 'reference_number', 'title_en'),
            'bid' => $bid->load('vendor:id,company_name'),
            'criteria' => $criteria,
            'existingScores' => $existingScores,
        ]);
    }

    public function storeScores(StoreScoresRequest $request, Tender $tender, Bid $bid): RedirectResponse
    {
        // StoreScoresRequest::authorize() tests the global evaluations.score
        // permission only, so any holder could score any bid on any tender.
        $this->authorize('score', [EvaluationScore::class, $tender]);
        $this->assertBidBelongsTo($tender, $bid);

        $user = $request->user();
        $data = $request->validated();

        $memberRecords = CommitteeMember::whereHas('committee', fn ($q) => $q->where('tender_id', $tender->id))
            ->where('user_id', $user->id)
            ->with('committee')
            ->get();

        // StoreScoresRequest checks only that the criterion id exists SOMEWHERE,
        // so scores could be posted against another tender's criteria, or
        // against an envelope this evaluator has no standing to judge.
        $scorable = $tender->evaluationCriteria()
            ->whereIn('envelope', $this->evaluationService->scorableEnvelopes(
                $tender,
                $memberRecords->pluck('committee.committee_type')->filter(),
            ))
            ->get()
            ->keyBy('id');

        // Every score is checked before any is written. The guard used to sit
        // inside the write loop and `return back()` from the middle of it, so a
        // rejected submission left some criteria saved and some not while the
        // screen still showed everything the user had typed.
        foreach ($data['scores'] as $scoreData) {
            $criterion = $scorable->get($scoreData['criterion_id']);
            abort_if($criterion === null, 404);

            if ($scoreData['score'] > $criterion->max_score) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => __('Score for :name exceeds maximum of :max.', [
                        'name' => $criterion->name_en,
                        'max' => $criterion->max_score,
                    ]),
                ]);

                return back();
            }
        }

        DB::transaction(function () use ($data, $bid, $user, $tender, $scorable) {
            foreach ($data['scores'] as $scoreData) {
                EvaluationScore::updateOrCreate(
                    [
                        'bid_id' => $bid->id,
                        'criterion_id' => $scoreData['criterion_id'],
                        'evaluator_id' => $user->id,
                    ],
                    [
                        'score' => $scoreData['score'],
                        'justification' => $scoreData['justification'] ?? null,
                        'scored_at' => now(),
                    ]
                );
            }

            if ($data['complete'] ?? false) {
                $this->markCompleteIfEverythingScored($tender, $bid, $user, $scorable);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scores saved.')]);

        return back();
    }
}
