<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\OpenBidsRequest;
use App\Models\Tender;
use App\Models\User;
use App\Services\BidSealingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BidOpeningController extends Controller
{
    public function __construct(
        private BidSealingService $sealingService,
    ) {}

    public function summary(Tender $tender): Response
    {
        $this->authorize('view', $tender);

        $bids = $tender->bids()
            ->with('vendor:id,company_name')
            ->select('id', 'vendor_id', 'bid_reference', 'status', 'total_amount', 'is_sealed', 'submitted_at', 'opened_at')
            ->where('status', '!=', 'withdrawn')
            // Ordering by amount ranked the bids cheapest-first on screen, which
            // gave away the commercial standings before the ceremony even
            // without the column. Submission order tells the reader nothing.
            ->orderBy('submitted_at')
            ->get();

        // A price is revealed per bid, and only once that bid has actually been
        // opened under dual authorisation. The column used to be selected and
        // rendered unconditionally, so this screen showed every sealed bid's
        // total to anyone who could view the tender — which is the whole thing
        // the sealing is meant to prevent.
        $bids->each(function ($bid) {
            if (! $bid->is_sealed) {
                $bid->makeVisible('total_amount');
            }
        });

        // Get project team members with bids.open permission for authorizer dropdown
        $authorizers = $tender->project->users()
            ->whereHas('role.permissions', fn ($q) => $q->where('slug', 'bids.open'))
            ->select('users.id', 'users.name')
            ->get();

        return Inertia::render('evaluation/BidOpening', [
            'tender' => $tender->only('id', 'reference_number', 'title_en', 'status', 'opening_date', 'submission_deadline'),
            'bids' => $bids,
            'authorizers' => $authorizers,
            'canOpen' => $this->sealingService->canOpen($tender) && $tender->status->value === 'submission_closed',
            'isOpened' => $tender->status->value === 'under_evaluation' || $tender->bids()->where('is_sealed', false)->exists(),
        ]);
    }

    public function open(OpenBidsRequest $request, Tender $tender): RedirectResponse
    {
        $opener = $request->user();

        // OpenBidsRequest checks a global bids.open permission, unscoped. The
        // authorizer was checked against the project below but the opener never
        // was, so exactly half of the dual-authorisation control was scoped.
        $this->authorize('view', $tender);

        // summary() computes canOpen as "opening date passed AND status is
        // submission_closed", but only the date half was enforced server-side —
        // the status half was advisory UI. A tender still open for submissions
        // could be force-flipped to under_evaluation with its bids unsealed.
        if ($tender->status->value !== 'submission_closed') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Bids can only be opened once submissions have closed.')]);

            return back();
        }

        $authorizer = User::findOrFail($request->validated('authorizer_id'));

        // Verify authorizer has permission and is on the project
        if (! $authorizer->hasPermission('bids.open') || ! $authorizer->isAssignedToProject($tender->project_id)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Authorizer does not have permission.')]);

            return back();
        }

        $this->sealingService->openBids($tender, $opener, $authorizer);

        $tender->update(['status' => 'under_evaluation']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bids opened successfully.')]);

        return back();
    }
}
