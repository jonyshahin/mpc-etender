<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Services\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnvelopeController extends Controller
{
    public function __construct(
        private EvaluationService $evaluationService,
    ) {}

    /**
     * Complete technical evaluation — disqualify bids below threshold.
     */
    public function completeTechnical(Request $request, Tender $tender): RedirectResponse
    {
        $this->authorize('update', $tender);

        if (! $tender->is_two_envelope) {
            return back()->with('flash', ['type' => 'error', 'message' => __('Not a two-envelope tender.')]);
        }

        $passingBids = $this->evaluationService->getPassingBids($tender);

        return back()->with('flash', [
            'type' => 'success',
            'message' => __(':count bids passed technical evaluation.', ['count' => $passingBids->count()]),
        ]);
    }

    /**
     * Complete financial evaluation — compute final ranking.
     */
    public function completeFinancial(Request $request, Tender $tender): RedirectResponse
    {
        $this->authorize('update', $tender);

        $this->evaluationService->computeFinalRanking($tender);

        return back()->with('flash', ['type' => 'success', 'message' => __('Financial evaluation completed. Final ranking computed.')]);
    }
}
