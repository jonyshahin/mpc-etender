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
            ->orderBy('total_amount')
            ->get();

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
        $authorizer = User::findOrFail($request->validated('authorizer_id'));

        // Verify authorizer has permission and is on the project
        if (! $authorizer->hasPermission('bids.open') || ! $authorizer->isAssignedToProject($tender->project_id)) {
            return back()->with('flash', ['type' => 'error', 'message' => __('Authorizer does not have permission.')]);
        }

        $this->sealingService->openBids($tender, $opener, $authorizer);

        $tender->update(['status' => 'under_evaluation']);

        return back()->with('flash', ['type' => 'success', 'message' => __('Bids opened successfully.')]);
    }
}
