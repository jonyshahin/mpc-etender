<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Services\EvaluationService;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private EvaluationService $evaluationService,
        private FileUploadService $fileUploadService,
    ) {}

    public function generate(Request $request, Tender $tender): RedirectResponse
    {
        $this->authorize('view', $tender);

        $this->evaluationService->generateReport($tender, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Evaluation report generated.')]);

        return back();
    }

    public function show(Request $request, Tender $tender): Response
    {
        $this->authorize('view', $tender);

        $report = $tender->reports()->latest()->first();
        $ranking = $report?->ranking_data ?? $this->evaluationService->computeFinalRanking($tender);

        return Inertia::render('evaluation/Report', [
            'tender' => $tender->only('id', 'reference_number', 'title_en', 'status', 'is_two_envelope', 'estimated_value', 'currency'),
            'report' => $report,
            'ranking' => $ranking,
            'criteria' => $tender->evaluationCriteria()->orderBy('envelope')->orderBy('sort_order')->get(),
        ]);
    }

    public function downloadPdf(Request $request, Tender $tender)
    {
        $this->authorize('view', $tender);

        $report = $tender->reports()->latest()->firstOrFail();

        $this->fileUploadService->logAccess('evaluation_report', $report->id, 'downloaded', $request->user()->id);

        $url = $this->fileUploadService->getTemporaryUrl($report->file_path);

        return Inertia::location($url);
    }
}
