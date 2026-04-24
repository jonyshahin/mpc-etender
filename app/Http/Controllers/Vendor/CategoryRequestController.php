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

        return Inertia::render('vendor/CategoryRequests/Index', [
            'requests' => $vendor->categoryRequests()
                ->with(['items.category:id,name_en,name_ar', 'evidence'])
                ->latest()
                ->get(),
            'currentCategories' => $vendor->categories()
                ->orderBy('name_en')
                ->get(['categories.id', 'name_en', 'name_ar']),
        ]);
    }

    public function create(Request $request): Response
    {
        $vendor = $request->user('vendor');

        return Inertia::render('vendor/CategoryRequests/Create', [
            'categories' => Category::active()
                ->roots()
                ->with('children:id,name_en,name_ar,parent_id,is_active')
                ->orderBy('sort_order')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
            'currentCategoryIds' => $vendor->categories()->pluck('categories.id'),
            'hasOpenRequest' => $vendor->categoryRequests()->open()->exists(),
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
