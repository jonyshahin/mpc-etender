<?php

namespace App\Http\Controllers\Tender;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tender\StoreBoqItemRequest;
use App\Http\Requests\Tender\StoreBoqSectionRequest;
use App\Models\BoqItem;
use App\Models\BoqSection;
use App\Models\Tender;
use App\Services\BoqService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BoqController extends Controller
{
    public function __construct(
        private BoqService $boqService,
    ) {}

    public function storeSection(StoreBoqSectionRequest $request, Tender $tender): RedirectResponse
    {
        $this->authorize('update', $tender);

        $this->boqService->createSection($tender, $request->validated());

        return back()->with('flash', ['type' => 'success', 'message' => __('BOQ section added.')]);
    }

    public function updateSection(Request $request, Tender $tender, BoqSection $section): RedirectResponse
    {
        $this->authorize('update', $tender);

        $section->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]));

        return back()->with('flash', ['type' => 'success', 'message' => __('BOQ section updated.')]);
    }

    public function destroySection(Tender $tender, BoqSection $section): RedirectResponse
    {
        $this->authorize('update', $tender);

        $section->items()->delete();
        $section->delete();

        return back()->with('flash', ['type' => 'success', 'message' => __('BOQ section deleted.')]);
    }

    public function storeItem(StoreBoqItemRequest $request, Tender $tender, BoqSection $section): RedirectResponse
    {
        $this->authorize('update', $tender);

        $this->boqService->createItem($section, $request->validated());

        return back()->with('flash', ['type' => 'success', 'message' => __('BOQ item added.')]);
    }

    public function updateItem(Request $request, Tender $tender, BoqItem $item): RedirectResponse
    {
        $this->authorize('update', $tender);

        $item->update($request->validate([
            'item_code' => ['required', 'string', 'max:50'],
            'description_en' => ['required', 'string', 'max:1000'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'unit' => ['required', 'string', 'max:20'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('flash', ['type' => 'success', 'message' => __('BOQ item updated.')]);
    }

    public function destroyItem(Tender $tender, BoqItem $item): RedirectResponse
    {
        $this->authorize('update', $tender);

        $item->delete();

        return back()->with('flash', ['type' => 'success', 'message' => __('BOQ item deleted.')]);
    }

    public function import(Request $request, Tender $tender): RedirectResponse
    {
        $this->authorize('update', $tender);

        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv']]);

        $count = $this->boqService->importFromExcel($tender, $request->file('file'));

        return back()->with('flash', ['type' => 'success', 'message' => __(':count items imported.', ['count' => $count])]);
    }
}
