<?php

namespace App\Services;

use App\Enums\TenderStatus;
use App\Enums\VendorDocStatus;
use App\Enums\VendorStatus;
use App\Models\ApprovalRequest;
use App\Models\Award;
use App\Models\Bid;
use App\Models\CommitteeMember;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategoryRequest;
use App\Models\VendorDocument;
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
        // Average cycle time: days from publish to award.
        //
        // Computed in PHP rather than with AVG(DATEDIFF(...)): DATEDIFF is
        // MySQL-only and does not exist in SQLite, so this line threw the
        // moment anything but MySQL was behind it — including the test
        // suite, which is why nothing here was covered.
        $awarded = Tender::where('status', TenderStatus::Awarded)
            ->whereNotNull('publish_date')
            ->get(['publish_date', 'updated_at']);

        $avgCycleTime = $awarded->isEmpty()
            ? 0
            : $awarded->avg(fn (Tender $tender) => $tender->publish_date->diffInDays($tender->updated_at));

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
     * The landing dashboard an internal user sees after signing in.
     *
     * Two halves. The headline figures describe the portfolio; the "needs your
     * attention" queues describe *this* user's work, gated on the permissions
     * they actually hold, so a procurement officer and an evaluator get
     * different lists from the same page.
     *
     * @return array<string, mixed>
     */
    public function landing(User $user): array
    {
        return [
            'headline' => $this->headline(),
            'attention' => $this->attentionFor($user),
            'statusDistribution' => $this->statusDistributionSeries(),
            'awardTrend' => $this->awardTrend(),
            'closingSoon' => $this->closingSoon(),
        ];
    }

    /** Portfolio figures the whole team shares. */
    private function headline(): array
    {
        $estimated = (float) Tender::where('status', TenderStatus::Awarded)
            ->where('estimated_value', '>', 0)
            ->sum('estimated_value');
        $awarded = (float) Award::sum('award_amount');

        return [
            'active_tenders' => Tender::where('status', TenderStatus::Published)->count(),
            'bids_received' => Bid::count(),
            'qualified_vendors' => Vendor::qualified()->count(),
            'total_vendors' => Vendor::count(),
            'awarded_value' => $awarded,
            // Only meaningful once something has been awarded against an
            // estimate; showing "0% saved" on an empty system reads as a result
            // rather than an absence.
            'savings_rate' => $estimated > 0 ? round((($estimated - $awarded) / $estimated) * 100, 1) : null,
        ];
    }

    /**
     * Queues this user can act on, each with its own route.
     *
     * Every entry is permission-gated: an item a user cannot open must not
     * appear, or the dashboard becomes a list of dead ends.
     *
     * @return array<int, array<string, mixed>>
     */
    private function attentionFor(User $user): array
    {
        $items = [];

        $levels = array_values(array_filter(
            [1, 2, 3],
            fn (int $level) => $user->hasPermission("approvals.level{$level}"),
        ));

        if ($levels !== []) {
            $items[] = [
                'key' => 'approvals',
                'count' => ApprovalRequest::where('status', 'pending')
                    ->whereIn('approval_level', $levels)
                    ->count(),
                'href' => '/approvals',
                'tone' => 'critical',
            ];
        }

        if ($user->hasPermission('evaluations.score')) {
            $items[] = [
                'key' => 'evaluations',
                'count' => CommitteeMember::where('user_id', $user->id)
                    ->where('has_scored', false)
                    ->count(),
                'href' => '/evaluations',
                'tone' => 'warning',
            ];
        }

        if ($user->hasPermission('vendors.review_docs')) {
            $items[] = [
                'key' => 'vendor_documents',
                'count' => VendorDocument::where('status', VendorDocStatus::Pending)->count(),
                'href' => '/admin/vendors',
                'tone' => 'default',
            ];
        }

        if ($user->hasPermission('vendors.review_category_requests')) {
            $items[] = [
                'key' => 'category_requests',
                'count' => VendorCategoryRequest::where('status', 'pending')->count(),
                'href' => '/admin/vendor-category-requests',
                'tone' => 'default',
            ];
        }

        if ($user->hasPermission('vendors.qualify')) {
            $items[] = [
                'key' => 'vendor_prequalification',
                'count' => Vendor::where('prequalification_status', VendorStatus::Pending)->count(),
                'href' => '/admin/vendors',
                'tone' => 'default',
            ];
        }

        return $items;
    }

    /**
     * Tender counts by status, in pipeline order rather than count order.
     *
     * The sequence is the point — a reader scanning it is following work from
     * draft through to awarded, so sorting by size would destroy the meaning.
     *
     * @return array<int, array{status: string, count: int}>
     */
    private function statusDistributionSeries(): array
    {
        $counts = Tender::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return collect(TenderStatus::cases())
            ->map(fn (TenderStatus $status) => [
                'status' => $status->value,
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Award value per month for the last 12 months, gap-filled.
     *
     * Grouped in PHP rather than SQL on purpose: DATE_FORMAT is MySQL-only and
     * throws under the SQLite test suite, and grouping on the UTC month puts an
     * award made at 02:00 Baghdad on the 1st into the previous one. The row
     * count here is small enough that portability and correct boundaries win.
     *
     * monthlySpend() now delegates here rather than keeping its own
     * DATE_FORMAT copy of the same question.
     *
     * @return array<int, array{month: string, label: string, total: float}>
     */
    private function awardTrend(): array
    {
        $start = now()->timezone(config('mpc.timezone'))->startOfMonth()->subMonths(11);

        $totals = Award::whereNotNull('awarded_at')
            ->where('awarded_at', '>=', $start->copy()->utc())
            ->get(['awarded_at', 'award_amount'])
            ->groupBy(fn (Award $award) => $award->awarded_at
                ->timezone(config('mpc.timezone'))
                ->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('award_amount'));

        // Every month present even at zero, so the axis is a continuous
        // timeline rather than a line joining whichever months had activity.
        return collect(range(0, 11))
            ->map(function (int $offset) use ($start, $totals) {
                $month = $start->copy()->addMonths($offset);

                return [
                    'month' => $month->format('Y-m'),
                    'label' => $month->format('M'),
                    'total' => $totals[$month->format('Y-m')] ?? 0.0,
                ];
            })
            ->all();
    }

    /**
     * Published tenders whose deadline falls inside the next week.
     *
     * @return array<int, array<string, mixed>>
     */
    private function closingSoon(): array
    {
        return Tender::with('project:id,name,code')
            ->where('status', TenderStatus::Published)
            ->whereNotNull('submission_deadline')
            ->whereBetween('submission_deadline', [now(), now()->addDays(7)])
            ->orderBy('submission_deadline')
            ->take(5)
            ->get(['id', 'project_id', 'reference_number', 'title_en', 'submission_deadline'])
            ->map(fn (Tender $tender) => [
                'id' => $tender->id,
                'reference_number' => $tender->reference_number,
                'title' => $tender->title_en,
                'project' => $tender->project?->code,
                'submission_deadline' => $tender->submission_deadline,
                'bids_count' => $tender->bids()->count(),
            ])
            ->all();
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
     *
     * Delegates to awardTrend(), which already answers exactly this
     * question and answers it better. This method grouped with
     * DATE_FORMAT — MySQL-only — and grouped on the UTC month, so an award
     * made at 02:00 Baghdad on the 1st landed in the previous month. It
     * also skipped months with no awards, leaving the chart to imply a
     * continuous series across a gap.
     *
     * The returned rows carry an extra 'label' key the portfolio page does
     * not read; month and total are unchanged.
     *
     * @return array<int, array{month: string, label: string, total: float}>
     */
    private function monthlySpend(): array
    {
        return $this->awardTrend();
    }
}
