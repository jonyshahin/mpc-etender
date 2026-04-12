<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\StoreScoresRequest;
use App\Models\Bid;
use App\Models\CommitteeMember;
use App\Models\EvaluationScore;
use App\Models\Tender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScoringController extends Controller
{
    public function index(Request $request, Tender $tender): Response
    {
        $user = $request->user();

        // Get committees the user belongs to for this tender
        $memberRecords = CommitteeMember::whereHas('committee', fn ($q) => $q->where('tender_id', $tender->id))
            ->where('user_id', $user->id)
            ->with('committee')
            ->get();

        $envelope = $memberRecords->first()?->committee?->committee_type?->value ?? 'technical';

        $criteria = $tender->evaluationCriteria()
            ->where('envelope', $envelope)
            ->orderBy('sort_order')
            ->get();

        $bids = $tender->bids()
            ->with('vendor:id,company_name')
            ->whereNotIn('status', ['withdrawn', 'disqualified'])
            ->get();

        // Get existing scores by this evaluator
        $existingScores = EvaluationScore::where('evaluator_id', $user->id)
            ->whereIn('bid_id', $bids->pluck('id'))
            ->get()
            ->groupBy('bid_id');

        return Inertia::render('evaluation/Scoring', [
            'tender' => $tender->only('id', 'reference_number', 'title_en', 'is_two_envelope'),
            'criteria' => $criteria,
            'bids' => $bids,
            'existingScores' => $existingScores,
            'envelope' => $envelope,
            'hasCompleted' => $memberRecords->first()?->has_scored ?? false,
        ]);
    }

    public function scoreBid(Request $request, Tender $tender, Bid $bid): Response
    {
        $user = $request->user();

        $memberRecord = CommitteeMember::whereHas('committee', fn ($q) => $q->where('tender_id', $tender->id))
            ->where('user_id', $user->id)
            ->with('committee')
            ->firstOrFail();

        $envelope = $memberRecord->committee->committee_type->value;

        $criteria = $tender->evaluationCriteria()
            ->where('envelope', $envelope)
            ->orderBy('sort_order')
            ->get();

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
        $user = $request->user();
        $data = $request->validated();

        foreach ($data['scores'] as $scoreData) {
            // Validate score is within max_score
            $criterion = $tender->evaluationCriteria()->find($scoreData['criterion_id']);
            if ($criterion && $scoreData['score'] > $criterion->max_score) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => __('Score for :name exceeds maximum of :max.', [
                        'name' => $criterion->name_en,
                        'max' => $criterion->max_score,
                    ]),
                ]);

                return back();
            }

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

        // Mark as completed if requested
        if ($data['complete'] ?? false) {
            CommitteeMember::whereHas('committee', fn ($q) => $q->where('tender_id', $tender->id))
                ->where('user_id', $user->id)
                ->update(['has_scored' => true, 'scored_at' => now()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scores saved.')]);

        return back();
    }

    public function myProgress(Request $request, Tender $tender): Response
    {
        $user = $request->user();

        $memberRecords = CommitteeMember::whereHas('committee', fn ($q) => $q->where('tender_id', $tender->id))
            ->where('user_id', $user->id)
            ->with('committee')
            ->get();

        $bids = $tender->bids()
            ->with('vendor:id,company_name')
            ->whereNotIn('status', ['withdrawn', 'disqualified'])
            ->get();

        $scoredBidIds = EvaluationScore::where('evaluator_id', $user->id)
            ->whereIn('bid_id', $bids->pluck('id'))
            ->distinct('bid_id')
            ->pluck('bid_id');

        return Inertia::render('evaluation/MyProgress', [
            'tender' => $tender->only('id', 'reference_number', 'title_en'),
            'bids' => $bids,
            'scoredBidIds' => $scoredBidIds,
            'hasCompleted' => $memberRecords->first()?->has_scored ?? false,
        ]);
    }
}
