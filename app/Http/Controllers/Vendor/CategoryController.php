<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of the vendor's approved categories. Direct pivot mutation
 * (`PUT /vendor/categories`) was removed in C.1 — all category changes now
 * flow through the request-and-approve workflow handled by
 * Vendor\CategoryRequestController.
 */
class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->user('vendor');

        // active() on both sides. The tree renders Category::active(), so an
        // id plucked from the raw pivot for a category MPC has since retired
        // pointed at a row that is not on the page — leaving the tally above
        // the tree counting something the vendor could not find in it.
        $approvedIds = $vendor->categories()->active()->pluck('categories.id');

        return Inertia::render('vendor/Categories', [
            'categories' => Category::active()
                ->roots()
                ->with([
                    'children' => fn ($q) => $q->active()
                        ->orderBy('sort_order')
                        ->orderBy('name_en')
                        ->select('id', 'name_en', 'name_ar', 'parent_id'),
                ])
                ->orderBy('sort_order')
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
            'selectedCategoryIds' => $approvedIds,
            'hasOpenRequest' => $vendor->categoryRequests()->open()->exists(),
            'latestRequestId' => $vendor->categoryRequests()
                ->open()
                ->latest()
                ->value('id'),
        ]);
    }
}
