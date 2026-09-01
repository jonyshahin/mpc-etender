<?php

namespace App\Http\Controllers\Tender;

use App\Enums\TenderStatus;
use App\Exceptions\TenderPublishException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tender\StoreTenderRequest;
use App\Http\Requests\Tender\UpdateTenderRequest;
use App\Models\Category;
use App\Models\EvaluationReport;
use App\Models\EvaluationScore;
use App\Models\Tender;
use App\Services\TenderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenderController extends Controller
{
    public function __construct(
        private TenderService $tenderService,
    ) {}

    /**
     * Columns the list may be ordered by.
     *
     * A whitelist because orderBy() does not validate the column: Laravel
     * checks the direction and throws on anything but asc/desc, but hands the
     * column straight to the grammar, so `?sort=nope` was a 500.
     */
    private const SORTABLE = [
        'reference_number',
        'title_en',
        'status',
        'submission_deadline',
        'bids_count',
        'created_at',
    ];

    public function index(Request $request): Response
    {
        // Tender visibility is the project assignment — a user sees only the
        // projects they are on, so this scope comes before every other filter.
        $projectIds = $request->user()->projects()->pluck('projects.id');

        $search = trim((string) $request->input('search'));
        $status = $request->input('status');

        $base = fn () => Tender::query()
            ->whereIn('project_id', $projectIds)
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('title_en', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            }));

        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $tenders = $base()
            ->with(['project:id,name,code', 'creator:id,name'])
            // select() before withCount(): select() replaces the select
            // list, so calling it afterwards discards the count subquery —
            // the Bids column was rendering empty on every row.
            ->select('id', 'project_id', 'created_by', 'reference_number', 'title_en', 'status', 'submission_deadline', 'created_at')
            ->withCount('bids')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('tender/Index', [
            'tenders' => $tenders,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            // Counts follow the search but not the status, so each tab shows
            // how many the current search would find there.
            'statusCounts' => $this->statusCounts($base()),
            'summary' => $this->summary($base()),
        ]);
    }

    /**
     * How many tenders sit in each status, every status present.
     *
     * @return array<string, int>
     */
    private function statusCounts(Builder $query): array
    {
        $counts = $query->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return collect(TenderStatus::cases())
            ->mapWithKeys(fn (TenderStatus $case) => [$case->value => (int) ($counts[$case->value] ?? 0)])
            ->all();
    }

    /**
     * Headline figures for the list, on the same scope as the rows below them.
     *
     * @return array<string, int>
     */
    private function summary(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->where('status', TenderStatus::Published)->count(),
            'closing_this_week' => (clone $query)
                ->where('status', TenderStatus::Published)
                ->whereBetween('submission_deadline', [now(), now()->addDays(7)])
                ->count(),
            'awaiting_evaluation' => (clone $query)
                ->whereIn('status', [TenderStatus::SubmissionClosed, TenderStatus::UnderEvaluation])
                ->count(),
        ];
    }

    public function create(Request $request): Response
    {
        return Inertia::render('tender/Create', [
            'projects' => $request->user()->projects()->select('projects.id', 'projects.name', 'projects.code')->get(),
            'categories' => Category::active()->roots()
                ->with('children:id,name_en,name_ar,parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
        ]);
    }

    public function store(StoreTenderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $publish = (bool) ($data['publish'] ?? false);
        unset($data['publish']);

        $authDowngraded = false;
        if ($publish && ! $request->user()->hasPermission('tenders.publish')) {
            $publish = false;
            $authDowngraded = true;
        }

        $documents = $request->file('documents', []);
        $documentsMeta = $request->input('documents', []);

        $docsArray = [];
        foreach ($documents as $index => $fileData) {
            $file = is_array($fileData) ? ($fileData['file'] ?? null) : $fileData;
            $meta = $documentsMeta[$index] ?? [];

            if ($file) {
                $docsArray[] = [
                    'file' => $file,
                    'title' => $meta['title'] ?? $file->getClientOriginalName(),
                    'doc_type' => $meta['doc_type'] ?? 'other',
                ];
            }
        }

        try {
            $tender = $this->tenderService->create($data, $request->user(), $docsArray, $publish);
        } catch (TenderPublishException $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('messages.tender_publish_failed', ['reason' => $e->getMessage()]),
            ]);

            return back()->withInput();
        }

        $toastMessage = match (true) {
            $authDowngraded => __('messages.tender_saved_draft_no_publish_permission'),
            $publish => __('messages.tender_published'),
            default => __('messages.tender_saved_draft'),
        };

        Inertia::flash('toast', [
            'type' => $authDowngraded ? 'warning' : 'success',
            'message' => $toastMessage,
        ]);

        return to_route('tenders.show', $tender);
    }

    public function show(Request $request, Tender $tender): Response
    {
        // The only read action in this controller that never called it. Without
        // it any verified internal user could open any tender in any project and
        // read estimated_value — MPC's own cost estimate — plus notes_internal,
        // the full BOQ and the evaluation criteria weights.
        $this->authorize('view', $tender);

        $tender->load([
            'project:id,name,code',
            'creator:id,name',
            'categories:id,name_en',
            'boqSections' => fn ($q) => $q->with('items')->orderBy('sort_order'),
            'documents' => fn ($q) => $q->where('is_current', true)->orderByDesc('created_at'),
            'addenda' => fn ($q) => $q->orderByDesc('addendum_number'),
            'clarifications' => fn ($q) => $q->with('askedBy:id,company_name')->orderByDesc('asked_at'),
            'evaluationCriteria' => fn ($q) => $q->orderBy('envelope')->orderBy('sort_order'),
        ]);

        $tender->loadCount('bids');

        return Inertia::render('tender/Show', [
            'tender' => $tender,
            'canEdit' => $request->user()->can('update', $tender),
            'canPublish' => $request->user()->can('publish', $tender),
            'canCancel' => $request->user()->can('cancel', $tender),
            // The evaluation screens are all tender-scoped and were linked from
            // nowhere in the app — sidebar, this page, anywhere. The whole
            // module could only be reached by typing a URL. Each flag mirrors
            // the policy its destination enforces, so no link leads to a 403.
            'canScore' => $request->user()->can('score', [EvaluationScore::class, $tender]),
            'canViewEvaluationReport' => $request->user()->can('viewAny', [EvaluationReport::class, $tender]),
            // BUG-23: addendum issuance is a separate concern from tender
            // editing. canEdit is correctly gated to Draft (you can't edit
            // a published tender's BOQ/docs/etc), but addenda are precisely
            // the mechanism for amending PUBLISHED tenders. Bundling the
            // addendum form under canEdit hid the form on every published
            // tender's Addenda tab. Mirrors StoreAddendumRequest::authorize.
            'canIssueAddendum' => $request->user()->hasPermission('tenders.issue_addenda')
                && $tender->status === TenderStatus::Published,
        ]);
    }

    public function edit(Request $request, Tender $tender): Response
    {
        $this->authorize('update', $tender);

        $tender->load([
            'categories:id,name_en',
            'boqSections' => fn ($q) => $q->with('items')->orderBy('sort_order'),
            'documents' => fn ($q) => $q->where('is_current', true),
            'evaluationCriteria' => fn ($q) => $q->orderBy('sort_order'),
        ]);

        return Inertia::render('tender/Edit', [
            'tender' => $tender,
            'projects' => $request->user()->projects()->select('projects.id', 'projects.name', 'projects.code')->get(),
            'categories' => Category::active()->roots()
                ->with('children:id,name_en,name_ar,parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
            'tenderCategoryIds' => $tender->categories()->pluck('categories.id'),
        ]);
    }

    public function update(UpdateTenderRequest $request, Tender $tender): RedirectResponse
    {
        $this->authorize('update', $tender);

        $this->tenderService->update($tender, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tender updated.')]);

        return redirect()->route('tenders.show', $tender);
    }

    public function publish(Request $request, Tender $tender): RedirectResponse
    {
        $this->authorize('publish', $tender);

        try {
            $this->tenderService->publish($tender);
        } catch (TenderPublishException $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('messages.tender_publish_failed', ['reason' => $e->getMessage()]),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.tender_published')]);

        return redirect()->route('tenders.show', $tender);
    }

    public function cancel(Request $request, Tender $tender): RedirectResponse
    {
        $this->authorize('cancel', $tender);

        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->tenderService->cancel($tender, $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tender cancelled.')]);

        return redirect()->route('tenders.show', $tender);
    }
}
