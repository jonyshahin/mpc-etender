<?php

namespace App\Http\Controllers\Tender;

use App\Exceptions\TenderPublishException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tender\StoreTenderRequest;
use App\Http\Requests\Tender\UpdateTenderRequest;
use App\Models\Category;
use App\Models\Tender;
use App\Services\TenderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenderController extends Controller
{
    public function __construct(
        private TenderService $tenderService,
    ) {}

    public function index(Request $request): Response
    {
        $query = Tender::with(['project:id,name,code', 'creator:id,name'])
            ->withCount('bids')
            ->select('id', 'project_id', 'created_by', 'reference_number', 'title_en', 'status', 'submission_deadline', 'created_at');

        // Scope to user's projects
        $projectIds = $request->user()->projects()->pluck('projects.id');
        $query->whereIn('project_id', $projectIds);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $query->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'));

        return Inertia::render('tender/Index', [
            'tenders' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only('search', 'status', 'sort', 'direction'),
        ]);
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

        $this->tenderService->publish($tender);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tender published successfully.')]);

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
