<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreCategoryChangeRequest;
use App\Models\Category;
use App\Models\VendorCategoryRequest;
use App\Services\VendorCategoryRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryRequestController extends Controller
{
    public function __construct(
        private VendorCategoryRequestService $service,
    ) {}

    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        $requests = $vendor->categoryRequests()
            ->withCount([
                'items as adds_count' => fn ($q) => $q->where('operation', 'add'),
                'items as removes_count' => fn ($q) => $q->where('operation', 'remove'),
                'evidence as evidence_count',
            ])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('vendor/CategoryRequests/Index', [
            'requests' => $requests,
        ]);
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

        return Inertia::render('vendor/CategoryRequests/Show', [
            'request' => $categoryRequest->load(['items.category', 'evidence', 'reviewer:id,name']),
        ]);
    }

    public function destroy(Request $request, VendorCategoryRequest $categoryRequest): RedirectResponse
    {
        $vendor = $request->user('vendor');

        // Ownership + state guards live in the service.
        $this->service->withdraw(
            $categoryRequest,
            $vendor,
            $request->input('reason'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_category_request_withdrawn'),
        ]);

        return redirect()->route('vendor.category-requests.index');
    }
}
