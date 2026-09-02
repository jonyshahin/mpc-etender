<?php

namespace App\Http\Controllers\Approval;

use App\Concerns\FiltersLists;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ApprovalDecisionRequest;
use App\Http\Requests\Approval\DelegateRequest;
use App\Models\ApprovalRequest;
use App\Models\Tender;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    use FiltersLists;

    /**
     * Columns the queue may be ordered by.
     *
     * A whitelist because orderBy() does not check the column: Laravel
     * validates the direction and throws on anything but asc/desc, but hands
     * the column straight to the grammar. This list never read `sort` at all,
     * so the 500 the tender, vendor and project lists carried was not reachable
     * here — the whitelist arrives with the sortable headers, before there is
     * anything to exploit rather than after.
     */
    private const SORTABLE = [
        'requested_at',
        'deadline',
        'approval_level',
        'value_threshold',
        'status',
    ];

    /** The tab that shows every state rather than one of them. */
    private const ANY_STATUS = 'all';

    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    /**
     * The approvals waiting on this user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // hasPermission(), not can(). `can('approvals.level1')` names neither a
        // registered gate nor a policy ability — AppServiceProvider defines only
        // viewPulse and the model policies — so Gate fell through to its default
        // deny for every user, $levels came back empty, and
        // `whereIn('approval_level', [])` matched nothing. Nobody could see an
        // approval. DashboardService has always used hasPermission() for the
        // same check, which is why its "pending approvals" tile counted rows
        // this page then refused to show.
        $levels = array_values(array_filter(
            [1, 2, 3],
            fn (int $level) => $user->hasPermission("approvals.level{$level}"),
        ));

        // Visibility is the project assignment, exactly as it is for tenders.
        // There was no project scope here at all, so the queue listed every
        // pending approval in the system — reference, title, and the award
        // value it is for — including projects the reader is not assigned to
        // and whose rows ApprovalRequestPolicy::view would refuse to open.
        $projectIds = $user->projects()->pluck('projects.id');

        $search = $this->searchTerm($request);
        $status = $this->statusFilter($this->filterValue($request, 'status'));

        // Everything bar the status filter, so the tab counts come off the same
        // scope the rows do.
        $base = fn () => ApprovalRequest::query()
            ->whereIn('approval_level', $levels)
            ->whereHas('tender', fn (Builder $t) => $t->whereIn('project_id', $projectIds))
            ->when($search !== '', fn (Builder $q) => $q->whereHas('tender', fn (Builder $t) => $t
                ->where('reference_number', 'like', "%{$search}%")
                ->orWhere('title_en', 'like', "%{$search}%")
                ->orWhere('title_ar', 'like', "%{$search}%")));

        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'deadline';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $approvals = $base()
            ->when($status !== self::ANY_STATUS, fn (Builder $q) => $q->where('status', $status))
            ->with([
                'tender:id,project_id,reference_number,title_en,title_ar',
                // requestedBy, not requestedByUser. ApprovalRequest declares no
                // relation by that name, and Eloquent throws
                // BadMethodCallException for an undefined one, so this page was
                // a 500 on every single request — as was show(), below.
                'requestedBy:id,name',
                'report:id,recommended_bid_id',
                'report.recommendedBid:id,vendor_id',
                'report.recommendedBid.vendor:id,company_name',
            ])
            ->orderBy($sort, $direction)
            // Approvals raised by one escalation run share a timestamp to the
            // second, and an unbroken tie makes page 2 a reshuffle of page 1.
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            // Explicit rows rather than the models: what the queue renders, and
            // nothing else the record happens to carry.
            ->through(fn (ApprovalRequest $approval) => $this->row($approval));

        return Inertia::render('approval/Index', [
            'approvals' => $approvals,
            // Complete, because DataTable-style sorting merges this set into
            // every request. A partial echo sorts with the filters wiped.
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusCounts' => $this->statusCounts($base()),
            'summary' => $this->summary($base()),
            'statusOptions' => ApprovalStatus::options(),
            'anyStatus' => self::ANY_STATUS,
            // The instant the counts above were taken at. The page marks a row
            // overdue by comparing against this rather than the browser's
            // clock, so "3 overdue" in the tile is the same three rows that
            // render red — and rendering stays a pure function of its props.
            'now' => now()->toJSON(),
        ]);
    }

    /**
     * A requested status, or the queue's default.
     *
     * Defaults to pending rather than everything: this screen is a work queue,
     * and opening it on years of decided history would bury the rows that still
     * need a signature.
     */
    private function statusFilter(mixed $value): string
    {
        if ($value === self::ANY_STATUS) {
            return self::ANY_STATUS;
        }

        $valid = array_column(ApprovalStatus::cases(), 'value');

        return is_string($value) && in_array($value, $valid, true)
            ? $value
            : ApprovalStatus::Pending->value;
    }

    /**
     * How many approvals sit at each status, every status present.
     *
     * @return array<string, int>
     */
    private function statusCounts(Builder $query): array
    {
        $counts = $query->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return collect(ApprovalStatus::cases())
            ->mapWithKeys(fn (ApprovalStatus $case) => [$case->value => (int) ($counts[$case->value] ?? 0)])
            ->all();
    }

    /**
     * Headline figures, on the same scope as the rows beneath them.
     *
     * @return array<string, int|string|null>
     */
    private function summary(Builder $query): array
    {
        $pending = fn () => (clone $query)->where('status', ApprovalStatus::Pending);

        return [
            'pending' => $pending()->count(),
            // Past its deadline and still unsigned. escalateExpired() moves
            // these on when the scheduler runs; until it does they are the
            // rows that actually need someone.
            'overdue' => $pending()->whereNotNull('deadline')->where('deadline', '<', now())->count(),
            'due_soon' => $pending()
                ->whereNotNull('deadline')
                ->whereBetween('deadline', [now(), now()->addHours(48)])
                ->count(),
            // value_threshold is the award value frozen when the chain was
            // raised, denominated in USD by ApprovalService. Summed rather
            // than the tender's estimated_value: the estimate is what the
            // employer guessed beforehand, not what is being signed for.
            'value' => (string) ($pending()->sum('value_threshold') ?? 0),
        ];
    }

    /**
     * One row, as the queue consumes it.
     *
     * @return array<string, mixed>
     */
    private function row(ApprovalRequest $approval): array
    {
        return [
            'id' => $approval->id,
            'level' => (int) $approval->approval_level,
            'required_level' => (int) $approval->required_level,
            'status' => $approval->status?->value,
            'type' => $approval->approval_type?->value,
            'value' => $approval->value_threshold,
            'requested_at' => $approval->requested_at?->toJSON(),
            'deadline' => $approval->deadline?->toJSON(),
            'requested_by' => $approval->requestedBy?->name,
            'tender' => [
                'id' => $approval->tender?->id,
                'reference_number' => $approval->tender?->reference_number,
                'title_en' => $approval->tender?->title_en,
                'title_ar' => $approval->tender?->title_ar,
            ],
            'recommended_vendor' => $approval->report?->recommendedBid?->vendor?->company_name,
        ];
    }

    /**
     * Show full approval context — tender, report, ranking, decisions history.
     */
    public function show(ApprovalRequest $approval): Response
    {
        // There was no authorization here of any kind, so any verified user
        // could open any approval by id — including one on a project they are
        // not assigned to. The policy this calls is the same rule the queue
        // now scopes by, so a row that lists is a row that opens.
        $this->authorize('view', $approval);

        $approval->load([
            'tender',
            'report.recommendedBid.vendor',
            'decisions.approver:id,name',
            'decisions.delegatedFrom:id,name',
            // Same undefined relation as index() — a 500 on every open.
            'requestedBy:id,name',
            'delegatedTo:id,name',
        ]);

        // An approver is deciding the award against the employer's estimate,
        // so this screen asks for it. Tender hides it by default.
        $approval->tender?->makeVisible('estimated_value');

        return Inertia::render('approval/Show', [
            'approval' => $approval,
            // The delegate picker calls projectUsers.map() unconditionally —
            // JSX props evaluate when the element is built, so a missing prop
            // threw during render whether or not the dialog was open. This
            // screen is the only place Approve, Reject and Delegate live, so
            // it took the whole decision flow down with it.
            //
            // Only who they are and what to call them: the previous payload
            // shipped whole User models, carrying email, phone, last_login_at
            // and two-factor columns to a screen that shows a name.
            'projectUsers' => $approval->tender?->project?->users()
                ->where('users.id', '!=', $approval->requested_by)
                ->select('users.id', 'users.name')
                ->orderBy('users.name')
                ->get() ?? collect(),
        ]);
    }

    /**
     * Approve the approval request.
     */
    public function approve(ApprovalDecisionRequest $request, ApprovalRequest $approval): RedirectResponse
    {
        // Who may sign, and at what level, is settled by ApprovalDecisionRequest
        // through ApprovalRequestPolicy::approve — before validation runs.
        try {
            $this->approvalService->approve(
                $approval,
                $request->user(),
                $request->validated('comments'),
            );
        } catch (ValidationException $e) {
            return $this->refuse($e);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval submitted successfully.')]);

        return redirect()->back();
    }

    /**
     * Surface a service refusal as a toast.
     *
     * As a toast rather than the thrown error bag: the service refuses on
     * `status`, and the decision screen renders only `comments` and
     * `delegatee_id` errors, so the refusal would otherwise be invisible — the
     * same reasoning requestApproval() already applies.
     */
    private function refuse(ValidationException $e): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $e->validator->errors()->first()]);

        return redirect()->back();
    }

    /**
     * Reject the approval request.
     */
    public function reject(ApprovalDecisionRequest $request, ApprovalRequest $approval): RedirectResponse
    {
        try {
            $this->approvalService->reject(
                $approval,
                $request->user(),
                $request->validated('comments'),
            );
        } catch (ValidationException $e) {
            return $this->refuse($e);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval rejected.')]);

        return redirect()->back();
    }

    /**
     * Delegate the approval request to another user.
     */
    public function delegate(DelegateRequest $request, ApprovalRequest $approval): RedirectResponse
    {
        $delegatee = User::findOrFail($request->validated('delegatee_id'));

        try {
            $this->approvalService->delegate(
                $approval,
                $request->user(),
                $delegatee,
            );
        } catch (ValidationException $e) {
            return $this->refuse($e);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval delegated successfully.')]);

        return redirect()->back();
    }

    /**
     * Create an approval request from a tender's latest evaluation report.
     */
    public function requestApproval(Request $request, Tender $tender): RedirectResponse
    {
        // Two checks, because they answer different questions. view() is the
        // project scope — you cannot raise a chain on a tender you cannot
        // see. create() is the entitlement: ApprovalRequestPolicy::create
        // requires evaluations.finalize_reports, and was registered and never
        // called, so authorizing on view() alone let any project member start
        // an award chain. That was my own narrowing when I added the missing
        // check; this is the ability that was meant to guard it.
        $this->authorize('view', $tender);
        $this->authorize('create', ApprovalRequest::class);

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
