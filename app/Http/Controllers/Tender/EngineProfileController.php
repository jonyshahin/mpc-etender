<?php

namespace App\Http\Controllers\Tender;

use App\Enums\TenderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tender\UpdateTenderEngineProfileRequest;
use App\Models\EvaluationCriteriaTemplate;
use App\Models\Tender;
use App\Models\TenderEngineProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * A draft tender's engine scoring criteria. Editable until the tender opens;
 * the lock on publish freezes them (TenderEngineProfile refuses the write).
 */
class EngineProfileController extends Controller
{
    public function update(UpdateTenderEngineProfileRequest $request, Tender $tender): RedirectResponse
    {
        // Under the tender's row lock, the one TenderService takes to open it.
        // Authorisation saw a draft, but a publish may have opened the tender
        // since — or be finishing right now, in which case this waits for it
        // and then finds the tender no longer editable.
        DB::transaction(function () use ($request, $tender) {
            $current = Tender::whereKey($tender->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== TenderStatus::Draft) {
                throw ValidationException::withMessages(['contract_type' => __('engine.criteria.no_longer_editable')]);
            }

            $profile = $current->engineProfile()->lockForUpdate()->first()
                ?? TenderEngineProfile::fromTemplate($current, EvaluationCriteriaTemplate::standard());

            $profile->fill($request->validated())->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('engine.criteria.saved')]);

        return back();
    }
}
