<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorCategoryUpdateRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
        ]);
    }

    public function update(VendorCategoryUpdateRequest $request): RedirectResponse
    {
        $vendor = $request->user('vendor');
        $vendor->categories()->sync($request->validated('category_ids'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Categories updated successfully.')]);

        return back();
    }
}
