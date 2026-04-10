<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Project;
use App\Models\Tender;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    /**
     * Portfolio-wide dashboard for managers.
     */
    public function portfolio(Request $request): Response
    {
        return Inertia::render('dashboard/Portfolio', [
            'overview' => $this->dashboardService->portfolioOverview(),
            'kpis' => $this->dashboardService->kpiMetrics(),
            'pendingApprovals' => ApprovalRequest::where('status', 'pending')->count(),
            'recentTenders' => Tender::with('project:id,name,code')
                ->orderByDesc('created_at')
                ->take(5)
                ->get(['id', 'project_id', 'reference_number', 'title_en', 'status', 'created_at']),
        ]);
    }

    /**
     * Project-level procurement dashboard.
     */
    public function project(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('dashboard/Project', [
            'project' => $project->only('id', 'name', 'code', 'status'),
            'overview' => $this->dashboardService->projectOverview($project),
            'tenders' => $project->tenders()
                ->withCount('bids')
                ->orderByDesc('created_at')
                ->get(['id', 'reference_number', 'title_en', 'status', 'submission_deadline', 'created_at']),
        ]);
    }
}
