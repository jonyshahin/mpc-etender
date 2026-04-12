<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::withCount('vendors')
            ->with('children:id,name_en,name_ar,parent_id,is_active,sort_order')
            ->roots()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        return Inertia::render('admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created successfully.')]);

        return redirect()->route('admin.categories.index');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated successfully.')]);

        return redirect()->route('admin.categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a category with sub-categories.')]);

            return back();
        }

        if ($category->vendors()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a category with assigned vendors.')]);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted successfully.')]);

        return redirect()->route('admin.categories.index');
    }
}
