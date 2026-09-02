<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreCategoryChangeRequest;
use App\Models\Category;
use App\Models\Vendor;
use App\Models\VendorCategoryRequest;
use App\Models\VendorCategoryRequestEvidence;
use App\Services\FileUploadService;
use App\Services\VendorCategoryRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryRequestController extends Controller
{
    public function __construct(
        private VendorCategoryRequestService $service,
        private FileUploadService $files,
    ) {}

    /**
     * Columns the list may be ordered by.
     *
     * A whitelist because orderBy() does not check the column: Laravel
     * validates the direction and throws on anything but asc/desc, but hands
     * the column straight to the query grammar.
     */
    private const SORTABLE = ['created_at', 'status', 'reviewed_at'];

    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        // The table marked three columns sortable and navigated with
        // ?sort=&direction=; this method hardcoded orderByDesc('created_at')
        // and read neither, so clicking a header reloaded the same order.
        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $requests = $vendor->categoryRequests()
            ->withCount([
                'items as adds_count' => fn ($q) => $q->where('operation', 'add'),
                'items as removes_count' => fn ($q) => $q->where('operation', 'remove'),
                'evidence as evidence_count',
            ])
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('vendor/CategoryRequests/Index', [
            'requests' => $requests,
            'filters' => [
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => $this->summary($vendor),
        ]);
    }

    /**
     * Counts per state, every state present.
     *
     * @return array<string, int>
     */
    private function summary(Vendor $vendor): array
    {
        $byStatus = $vendor->categoryRequests()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'open' => (int) (($byStatus['pending'] ?? 0) + ($byStatus['under_review'] ?? 0)),
            'approved' => (int) ($byStatus['approved'] ?? 0),
            'rejected' => (int) ($byStatus['rejected'] ?? 0),
        ];
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $vendor = $request->user('vendor');

        // Server-side guard: the React list page already disables the CTA when an
        // open request exists, but a vendor typing the URL directly or reloading
        // a stale tab could bypass that. Redirect back with an error toast.
        if ($vendor->categoryRequests()->open()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('vendor.category_requests.open_request_exists'),
            ]);

            return redirect()->route('vendor.category-requests.index');
        }

        return Inertia::render('vendor/CategoryRequests/Create', [
            'availableCategories' => Category::active()
                ->orderBy('sort_order')
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
            'currentlyApprovedIds' => $vendor->categories()->pluck('categories.id')->toArray(),
        ]);
    }

    public function store(StoreCategoryChangeRequest $request): RedirectResponse
    {
        $vendor = $request->user('vendor');

        $this->service->submit(
            vendor: $vendor,
            justification: $request->input('justification'),
            addCategoryIds: $request->input('add_categories', []),
            removeCategoryIds: $request->input('remove_categories', []),
            evidenceFiles: $request->file('evidence', []),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_category_request_submitted'),
        ]);

        return redirect()->route('vendor.category-requests.index');
    }

    public function show(Request $request, VendorCategoryRequest $categoryRequest): Response
    {
        $vendor = $request->user('vendor');
        abort_unless($categoryRequest->vendor_id === $vendor->id, 403);

        $categoryRequest->load([
            'items.category:id,name_en,name_ar,parent_id',
            'evidence:id,request_id,original_name,mime_type,size,path,created_at',
            'reviewer:id,name',
        ]);

        $mapItem = fn ($i) => [
            'category_id' => $i->category_id,
            'name_en' => $i->category?->name_en,
            'name_ar' => $i->category?->name_ar,
            'parent_id' => $i->category?->parent_id,
        ];

        return Inertia::render('vendor/CategoryRequests/Show', [
            'request' => [
                'id' => $categoryRequest->id,
                'status' => $categoryRequest->status,
                'justification' => $categoryRequest->justification,
                'reviewer_comments' => $categoryRequest->reviewer_comments,
                'withdraw_reason' => $categoryRequest->withdraw_reason,
                'reviewer' => $categoryRequest->reviewer
                    ? ['id' => $categoryRequest->reviewer->id, 'name' => $categoryRequest->reviewer->name]
                    : null,
                'reviewed_at' => $categoryRequest->reviewed_at?->toIso8601String(),
                'created_at' => $categoryRequest->created_at->toIso8601String(),
                'updated_at' => $categoryRequest->updated_at->toIso8601String(),
                'adds' => $categoryRequest->items->where('operation', 'add')->map($mapItem)->values(),
                'removes' => $categoryRequest->items->where('operation', 'remove')->map($mapItem)->values(),
                'evidence' => $categoryRequest->evidence->map(fn ($e) => [
                    'id' => $e->id,
                    'original_name' => $e->original_name,
                    'mime_type' => $e->mime_type,
                    'size' => $e->size,
                    'created_at' => $e->created_at->toIso8601String(),
                    'download_url' => route('vendor.category-requests.evidence.download', [
                        $categoryRequest->id,
                        $e->id,
                    ]),
                ])->values(),
            ],
        ]);
    }

    public function destroy(Request $request, VendorCategoryRequest $categoryRequest): RedirectResponse
    {
        $vendor = $request->user('vendor');

        // Service asserts ownership + open-state; we normalize empty-string
        // reason to null so the service persists a clean NULL rather than "".
        $reason = trim((string) $request->input('reason', '')) ?: null;

        $this->service->withdraw($categoryRequest, $vendor, $reason);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('vendor.category_requests.withdraw_success'),
        ]);

        return redirect()->route('vendor.category-requests.index');
    }

    public function downloadEvidence(
        Request $request,
        VendorCategoryRequest $categoryRequest,
        VendorCategoryRequestEvidence $evidence,
    ): RedirectResponse {
        $vendor = $request->user('vendor');

        abort_unless($categoryRequest->vendor_id === $vendor->id, 403);
        abort_unless($evidence->request_id === $categoryRequest->id, 404);

        // CLAUDE.md holds every document read to document_access_logs. This
        // one handed out a presigned S3 URL and recorded nothing, so evidence
        // a vendor filed could be fetched repeatedly with no trace — the one
        // document path in the portal that was not logged.
        $this->files->logAccess(
            documentType: VendorCategoryRequestEvidence::class,
            documentId: $evidence->id,
            action: 'downloaded',
            vendorId: $vendor->id,
        );

        return redirect()->away($this->files->getTemporaryUrl($evidence->path, 10));
    }
}
