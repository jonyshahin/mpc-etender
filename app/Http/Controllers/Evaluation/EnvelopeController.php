<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Services\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Not a two-envelope tender.')]);

            return back();
        }

        $passingBids = $this->evaluationService->getPassingBids($tender);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count bids passed technical evaluation.', ['count' => $passingBids->count()]),
        ]);

        return back();
    }

    /**
     * Complete financial evaluation — compute final ranking.
     */
    public function completeFinancial(Request $request, Tender $tender): RedirectResponse
    {
        $this->authorize('update', $tender);

        $this->evaluationService->computeFinalRanking($tender);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Financial evaluation completed. Final ranking computed.')]);

        return back();
    }
}
