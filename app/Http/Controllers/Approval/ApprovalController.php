<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ApprovalDecisionRequest;
use App\Http\Requests\Approval\DelegateRequest;
use App\Models\ApprovalRequest;
use App\Models\Tender;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    /**
     * List pending approvals for current user.
     * Filters by user's approval level permission (level1/level2/level3).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = ApprovalRequest::with(['tender', 'report', 'requestedByUser'])
            ->where('status', 'pending');

        // Filter by user's approval level permissions
        $levels = [];
        if ($user->can('approvals.level1')) {
            $levels[] = 1;
        }
        if ($user->can('approvals.level2')) {
            $levels[] = 2;
        }
        if ($user->can('approvals.level3')) {
            $levels[] = 3;
        }

        $approvals = $query->whereIn('approval_level', $levels)
            ->latest('requested_at')
            ->paginate(15);

        return Inertia::render('approval/Index', [
            'approvals' => $approvals,
        ]);
    }

    /**
     * Show full approval context — tender, report, ranking, decisions history.
     */
    public function show(ApprovalRequest $approval): Response
    {
        $approval->load([
            'tender',
            'report.recommendedBid.vendor',
            'decisions.approver',
            'requestedByUser',
        ]);

        return Inertia::render('approval/Show', [
            'approval' => $approval,
        ]);
    }

    /**
     * Approve the approval request.
     */
    public function approve(ApprovalDecisionRequest $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->approvalService->approve(
            $approval,
            $request->user(),
            $request->validated('comments'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval submitted successfully.')]);

        return redirect()->back();
    }

    /**
     * Reject the approval request.
     */
    public function reject(ApprovalDecisionRequest $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->approvalService->reject(
            $approval,
            $request->user(),
            $request->validated('comments'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval rejected.')]);

        return redirect()->back();
    }

    /**
     * Delegate the approval request to another user.
     */
    public function delegate(DelegateRequest $request, ApprovalRequest $approval): RedirectResponse
    {
        $delegatee = User::findOrFail($request->validated('delegatee_id'));

        $this->approvalService->delegate(
            $approval,
            $request->user(),
            $delegatee,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval delegated successfully.')]);

        return redirect()->back();
    }

    /**
     * Create an approval request from a tender's latest evaluation report.
     */
    public function requestApproval(Request $request, Tender $tender): RedirectResponse
    {
        // reports(), not evaluationReports(): the latter does not exist on Tender,
        // so this endpoint raised BadMethodCallException — a 500 on every attempt
        // to start an approval, which is why the levelling bugs below it went
        // unnoticed.
        $report = $tender->reports()->latest()->firstOrFail();

        try {
            $this->approvalService->requestApproval(
                $tender,
                $report,
                $request->user(),
            );
        } catch (ValidationException $e) {
            // As a toast, not the thrown error bag: the service refuses on
            // `currency`, and this page renders only `comments` and
            // `delegatee_id` errors — the refusal would have been invisible.
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->validator->errors()->first()]);

            return redirect()->back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval request created successfully.')]);

        return redirect()->back();
    }
}
