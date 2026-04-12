<?php

namespace App\Http\Controllers\Tender;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tender\StoreEvaluationCriterionRequest;
use App\Models\EvaluationCriterion;
use App\Models\Tender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EvaluationCriteriaController extends Controller
{
    public function store(StoreEvaluationCriterionRequest $request, Tender $tender): RedirectResponse
    {
        $this->authorize('update', $tender);

        $data = $request->validated();
        $maxSort = $tender->evaluationCriteria()->max('sort_order') ?? 0;

        $tender->evaluationCriteria()->create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? $maxSort + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Evaluation criterion added.')]);

        return back();
    }

    public function update(Request $request, Tender $tender, EvaluationCriterion $criterion): RedirectResponse
    {
        $this->authorize('update', $tender);

        $criterion->update($request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'envelope' => ['required', 'in:single,technical,financial'],
            'weight_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_score' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion updated.')]);

        return back();
    }

    public function destroy(Tender $tender, EvaluationCriterion $criterion): RedirectResponse
    {
        $this->authorize('update', $tender);

        $criterion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Criterion deleted.')]);

        return back();
    }
}
