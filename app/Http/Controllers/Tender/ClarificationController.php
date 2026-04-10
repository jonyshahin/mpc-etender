<?php

namespace App\Http\Controllers\Tender;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tender\AnswerClarificationRequest;
use App\Http\Requests\Tender\StoreClarificationRequest;
use App\Models\Clarification;
use App\Models\Tender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClarificationController extends Controller
{
    /**
     * Submit a clarification question (vendor-facing).
     */
    public function store(StoreClarificationRequest $request, Tender $tender): RedirectResponse
    {
        $vendor = $request->user('vendor');

        $tender->clarifications()->create([
            'asked_by' => $vendor->id,
            'question' => $request->validated('question'),
            'asked_at' => now(),
            'is_published' => false,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => __('Question submitted.')]);
    }

    /**
     * Answer a clarification (MPC-facing).
     */
    public function answer(AnswerClarificationRequest $request, Tender $tender, Clarification $clarification): RedirectResponse
    {
        $clarification->update([
            'answer' => $request->validated('answer'),
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => __('Answer saved.')]);
    }

    /**
     * Publish a clarification Q&A (makes it visible to all vendors).
     */
    public function publish(Request $request, Tender $tender, Clarification $clarification): RedirectResponse
    {
        $clarification->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => __('Clarification published.')]);
    }
}
