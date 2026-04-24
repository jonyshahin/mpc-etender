<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of the vendor's approved categories. Direct pivot mutation
 * (`PUT /vendor/categories`) was removed in C.1 — all category changes now
 * flow through the request-and-approve workflow handled by
 * Vendor\CategoryRequestController. The React page linked to from here should
 * render approved categories as read-only and link to the "Request change"
 * flow at /vendor/category-requests/create.
 */
class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        return Inertia::render('vendor/Categories', [
            'categories' => Category::active()
                ->roots()
                ->with('children:id,name_en,name_ar,parent_id,is_active')
                ->orderBy('sort_order')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
            'selectedCategoryIds' => $vendor->categories()->pluck('categories.id'),
            'hasOpenRequest' => $vendor->categoryRequests()->open()->exists(),
            'latestRequestId' => $vendor->categoryRequests()
                ->open()
                ->latest()
                ->value('id'),
        ]);
    }
}
