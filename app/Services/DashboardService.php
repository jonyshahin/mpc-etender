<?php

namespace App\Services;

use App\Enums\TenderStatus;
use App\Models\Award;
use App\Models\Bid;
use App\Models\Project;
use App\Models\Tender;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard analytics and KPI computation for procurement reporting.
 */
class DashboardService
{
    /**
     * Project-level procurement overview.
     */
    public function projectOverview(Project $project): array
    {
        $tenders = $project->tenders();

        return [
            'total_tenders' => $tenders->count(),
            'active_tenders' => $tenders->where('status', TenderStatus::Published)->count(),
            'awarded_tenders' => (clone $tenders)->where('status', TenderStatus::Awarded)->count(),
            'total_bids' => Bid::whereIn('tender_id', $project->tenders()->pluck('id'))->count(),
            'total_award_value' => Award::whereIn('tender_id', $project->tenders()->pluck('id'))->sum('award_amount'),
            'tender_pipeline' => $this->tenderPipeline($project),
        ];
    }

    /**
     * Portfolio-wide spend dashboard across all projects.
     */
    public function portfolioOverview(): array
    {
        return [
            'total_projects' => Project::count(),
            'active_projects' => Project::active()->count(),
            'total_tenders' => Tender::count(),
            'published_tenders' => Tender::where('status', TenderStatus::Published)->count(),
            'awarded_tenders' => Tender::where('status', TenderStatus::Awarded)->count(),
            'total_vendors' => Vendor::count(),
            'qualified_vendors' => Vendor::qualified()->count(),
            'total_bids' => Bid::count(),
            'total_spend' => Award::sum('award_amount'),
            'spend_by_project' => $this->spendByProject(),
            'tender_status_distribution' => $this->tenderStatusDistribution(),
            'monthly_spend' => $this->monthlySpend(),
        ];
    }

    /**
     * KPI metrics: cycle time, participation rate, savings.
     */
    public function kpiMetrics(): array
    {
        // Average cycle time: days from publish to award
        $avgCycleTime = Tender::where('status', TenderStatus::Awarded)
            ->whereNotNull('publish_date')
            ->selectRaw('AVG(DATEDIFF(updated_at, publish_date)) as avg_days')
            ->value('avg_days');

        // Participation rate: average bids per tender
        $avgBidsPerTender = Tender::where('status', '!=', TenderStatus::Draft)
            ->withCount('bids')
            ->get()
            ->avg('bids_count');

        // Savings rate: (estimated - award) / estimated
        $savingsData = Tender::where('status', TenderStatus::Awarded)
            ->whereHas('awards')
            ->with('awards')
            ->whereNotNull('estimated_value')
            ->where('estimated_value', '>', 0)
            ->get();

        $totalEstimated = $savingsData->sum('estimated_value');
        $totalAwarded = $savingsData->sum(fn ($t) => $t->awards->first()?->award_amount ?? 0);
        $savingsRate = $totalEstimated > 0 ? (($totalEstimated - $totalAwarded) / $totalEstimated) * 100 : 0;

        return [
            'avg_cycle_time_days' => round((float) $avgCycleTime, 1),
            'avg_bids_per_tender' => round((float) $avgBidsPerTender, 1),
            'savings_rate_percent' => round($savingsRate, 1),
            'total_estimated' => $totalEstimated,
            'total_awarded' => $totalAwarded,
        ];
    }

    /**
     * Tender pipeline for a project grouped by status.
     */
    private function tenderPipeline(Project $project): array
    {
        return $project->tenders()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Spend aggregated by project.
     */
    private function spendByProject(): array
    {
        return Award::join('tenders', 'awards.tender_id', '=', 'tenders.id')
            ->join('projects', 'tenders.project_id', '=', 'projects.id')
            ->select('projects.name', DB::raw('SUM(awards.award_amount) as total'))
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('total')
            ->take(10)
            ->get()
            ->toArray();
    }

    /**
     * Tender distribution by status.
     */
    private function tenderStatusDistribution(): array
    {
        return Tender::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Monthly spend trend (last 12 months).
     */
    private function monthlySpend(): array
    {
        return Award::where('awarded_at', '>=', now()->subYear())
            ->select(
                DB::raw("DATE_FORMAT(awarded_at, '%Y-%m') as month"),
                DB::raw('SUM(award_amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();
    }
}
