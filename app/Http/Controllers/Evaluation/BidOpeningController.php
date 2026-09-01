<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\OpenBidsRequest;
use App\Models\BidOpeningRequest;
use App\Models\Tender;
use App\Models\User;
use App\Services\BidOpeningRequestService;
use App\Services\BidSealingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BidOpeningController extends Controller
{
    public function __construct(
        private BidSealingService $sealingService,
        private BidOpeningRequestService $openingRequests,
    ) {}

    public function summary(Request $request, Tender $tender): Response
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

        $pending = $this->openingRequests->actionableFor($tender);
        $viewer = $request->user();

        return Inertia::render('evaluation/BidOpening', [
            'tender' => $tender->only('id', 'reference_number', 'title_en', 'status', 'opening_date', 'submission_deadline'),
            'bids' => $bids,
            'authorizers' => $authorizers,
            'canOpen' => $this->sealingService->canOpen($tender) && $tender->status->value === 'submission_closed',
            'isOpened' => $tender->status->value === 'under_evaluation' || $tender->bids()->where('is_sealed', false)->exists(),
            // The ceremony is two-step now: whoever is looking needs to know
            // which half they are, and what they are waiting for.
            'pendingRequest' => $pending === null ? null : [
                'id' => $pending->id,
                'requested_by_name' => $pending->requester?->name,
                'authorizer_name' => $pending->authorizer?->name,
                'requested_at' => $pending->requested_at,
                'expires_at' => $pending->expires_at,
                'viewer_is_authorizer' => $viewer->id === $pending->authorizer_id,
                'viewer_is_requester' => $viewer->id === $pending->requested_by,
            ],
        ]);
    }

    /**
     * The opener's half: nominate who must countersign.
     *
     * This used to open the bids outright. `authorizer_id` was an
     * attacker-chosen field validated only as existing-and-different, so one
     * person could produce a complete "dual" authorisation — and
     * BidSealingService then wrote that name into an append-only audit row.
     */
    public function open(OpenBidsRequest $request, Tender $tender): RedirectResponse
    {
        $this->authorize('view', $tender);

        $authorizer = User::findOrFail($request->validated('authorizer_id'));

        try {
            $this->openingRequests->request($tender, $request->user(), $authorizer);
        } catch (ValidationException $e) {
            return $this->refuse($e);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Opening requested. :name must now confirm it.', ['name' => $authorizer->name]),
        ]);

        return back();
    }

    /** The authorizer's half, from their own session. */
    public function confirm(Request $request, Tender $tender, BidOpeningRequest $openingRequest): RedirectResponse
    {
        $this->authorize('view', $tender);
        abort_unless($openingRequest->tender_id === $tender->id, 404);

        try {
            $this->openingRequests->confirm($openingRequest, $request->user());
        } catch (ValidationException $e) {
            return $this->refuse($e);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bids opened successfully.')]);

        return back();
    }

    /** Either party may call it off. */
    public function cancel(Request $request, Tender $tender, BidOpeningRequest $openingRequest): RedirectResponse
    {
        $this->authorize('view', $tender);
        abort_unless($openingRequest->tender_id === $tender->id, 404);

        try {
            $this->openingRequests->cancel($openingRequest, $request->user());
        } catch (ValidationException $e) {
            return $this->refuse($e);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Opening request cancelled.')]);

        return back();
    }

    /**
     * Surface a refusal as a toast.
     *
     * The service refuses on keys this page renders no field for, so letting
     * the error bag bubble would leave the user staring at a silent no-op.
     */
    private function refuse(ValidationException $e): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $e->validator->errors()->first()]);

        return back();
    }
}
