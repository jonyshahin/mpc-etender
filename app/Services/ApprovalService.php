<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\AwardStatus;
use App\Enums\TenderStatus;
use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\Award;
use App\Models\EvaluationReport;
use App\Models\SystemSetting;
use App\Models\Tender;
use App\Models\User;
use Carbon\CarbonInterface;

class ApprovalService
{
    /**
     * Create an approval request for a tender award.
     * Auto-determines approval level from tender value and system settings.
     */
    public function requestApproval(Tender $tender, EvaluationReport $report, User $requestedBy): ApprovalRequest
    {
        $level = $this->determineApprovalLevel($tender);

        return ApprovalRequest::create([
            'tender_id' => $tender->id,
            'report_id' => $report->id,
            'requested_by' => $requestedBy->id,
            'approval_type' => ApprovalType::Award,
            'value_threshold' => $tender->estimated_value,
            'approval_level' => $level,
            'status' => ApprovalStatus::Pending,
            'requested_at' => now(),
            'deadline' => $this->approvalDeadline(),
        ]);
    }

    /**
     * Approve at current level. If more levels needed, creates next-level request.
     * If final level, triggers award creation.
     */
    public function approve(ApprovalRequest $request, User $approver, string $comments): void
    {
        ApprovalDecision::create([
            'request_id' => $request->id,
            'approver_id' => $approver->id,
            'decision' => 'approved',
            'comments' => $comments,
            'decided_at' => now(),
        ]);

        $maxLevel = $this->determineApprovalLevel($request->tender);

        if ($request->approval_level < $maxLevel) {
            // Create next level request
            $request->update(['status' => ApprovalStatus::Approved]);

            ApprovalRequest::create([
                'tender_id' => $request->tender_id,
                'report_id' => $request->report_id,
                'requested_by' => $request->requested_by,
                'approval_type' => $request->approval_type,
                'value_threshold' => $request->value_threshold,
                'approval_level' => $request->approval_level + 1,
                'status' => ApprovalStatus::Pending,
                'requested_at' => now(),
                'deadline' => $this->approvalDeadline(),
            ]);
        } else {
            // Final level — create award
            $request->update(['status' => ApprovalStatus::Approved]);
            $this->createAward($request->tender, $request);
        }
    }

    /**
     * Reject the approval. Sets tender back to under_evaluation.
     */
    public function reject(ApprovalRequest $request, User $approver, string $comments): void
    {
        ApprovalDecision::create([
            'request_id' => $request->id,
            'approver_id' => $approver->id,
            'decision' => 'rejected',
            'comments' => $comments,
            'decided_at' => now(),
        ]);

        $request->update(['status' => ApprovalStatus::Rejected]);
        $request->tender->update(['status' => TenderStatus::UnderEvaluation]);
    }

    /**
     * Delegate approval to another user.
     */
    public function delegate(ApprovalRequest $request, User $delegator, User $delegatee): void
    {
        ApprovalDecision::create([
            'request_id' => $request->id,
            'approver_id' => $delegatee->id,
            'decision' => 'delegated',
            'comments' => "Delegated by {$delegator->name}",
            'delegated_from' => $delegator->id,
            'decided_at' => now(),
        ]);
    }

    /**
     * Auto-escalate expired approvals.
     */
    public function escalateExpired(): int
    {
        $expired = ApprovalRequest::where('status', ApprovalStatus::Pending)
            ->where('deadline', '<', now())
            ->get();

        foreach ($expired as $request) {
            $request->update([
                'status' => ApprovalStatus::Escalated,
                'deadline' => $this->approvalDeadline(),
            ]);

            // Create escalated request at next level (capped at max 3)
            if ($request->approval_level < 3) {
                ApprovalRequest::create([
                    'tender_id' => $request->tender_id,
                    'report_id' => $request->report_id,
                    'requested_by' => $request->requested_by,
                    'approval_type' => $request->approval_type,
                    'value_threshold' => $request->value_threshold,
                    'approval_level' => $request->approval_level + 1,
                    'status' => ApprovalStatus::Pending,
                    'requested_at' => now(),
                    'deadline' => $this->approvalDeadline(),
                ]);
            }
        }

        return $expired->count();
    }

    /**
     * After final approval: create the award record.
     */
    public function createAward(Tender $tender, ApprovalRequest $request): Award
    {
        $report = $request->report;
        $winningBid = $report->recommendedBid;

        $tender->update(['status' => TenderStatus::Awarded]);

        return Award::create([
            'tender_id' => $tender->id,
            'bid_id' => $winningBid->id,
            'vendor_id' => $winningBid->vendor_id,
            'approved_by' => $request->decisions()->latest()->first()?->approver_id,
            'award_amount' => $winningBid->total_amount,
            'currency' => $tender->currency,
            'justification' => $report->summary,
            'status' => AwardStatus::Pending,
            'awarded_at' => now(),
        ]);
    }

    /**
     * Deadline for a newly raised or escalated approval.
     *
     * Was hardcoded to 7 days in four places while `approval.expiry_days` sat
     * seeded and editable but read by nothing. (BUG-33)
     */
    private function approvalDeadline(): CarbonInterface
    {
        $days = (int) (SystemSetting::where('key', 'approval.expiry_days')->value('value') ?: 7);

        return now()->addDays(max($days, 1));
    }

    /**
     * Determine required approval level based on tender value.
     * Level 1 (< $50K), Level 2 ($50K-$500K), Level 3 (> $500K).
     * Thresholds from system_settings.
     */
    private function determineApprovalLevel(Tender $tender): int
    {
        $value = (float) $tender->estimated_value;

        // Keys must match SystemSettingSeeder exactly. These previously read
        // `approval_threshold_level1/2`, which are seeded nowhere — so the
        // lookups always returned null and the defaults below silently won,
        // making the thresholds on /admin/settings inert. (BUG-33)
        $threshold1 = (float) (SystemSetting::where('key', 'approval.level1_threshold')->value('value') ?? 50000);
        $threshold2 = (float) (SystemSetting::where('key', 'approval.level2_threshold')->value('value') ?? 500000);

        if ($value <= $threshold1) {
            return 1;
        }
        if ($value <= $threshold2) {
            return 2;
        }

        return 3;
    }
}
