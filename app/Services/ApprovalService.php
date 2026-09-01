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
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    /** The currency approval.level*_threshold is denominated in. */
    private const THRESHOLD_CURRENCY = 'USD';

    /**
     * Create an approval request for a tender award.
     *
     * The chain starts at level 1 and escalates. It used to be created already
     * sitting at the maximum level, which made approve()'s escalation branch
     * (`approval_level < required`) unreachable — so a single signature
     * finalised an award of any size and levels 2 and 3 were dead code.
     */
    public function requestApproval(Tender $tender, EvaluationReport $report, User $requestedBy): ApprovalRequest
    {
        $value = $this->awardValue($tender, $report);

        return ApprovalRequest::create([
            'tender_id' => $tender->id,
            'report_id' => $report->id,
            'requested_by' => $requestedBy->id,
            'approval_type' => ApprovalType::Award,
            'value_threshold' => $value,
            'approval_level' => 1,
            'required_level' => $this->determineApprovalLevel($value),
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

        // Frozen when the chain was raised, not recomputed here: re-deriving it
        // from the live tender and the live thresholds let an admin shorten a
        // chain that was already running, just by editing either one.
        $requiredLevel = (int) $request->required_level;

        if ($request->approval_level < $requiredLevel) {
            // Create next level request
            $request->update(['status' => ApprovalStatus::Approved]);

            ApprovalRequest::create([
                'tender_id' => $request->tender_id,
                'report_id' => $request->report_id,
                'requested_by' => $request->requested_by,
                'approval_type' => $request->approval_type,
                'value_threshold' => $request->value_threshold,
                'approval_level' => $request->approval_level + 1,
                'required_level' => $requiredLevel,
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
                    'required_level' => $request->required_level,
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
     * The money the award actually commits.
     *
     * The thresholds are described on /admin/settings as applying to the award
     * value, and createAward() books the award at the winning bid's total — but
     * the level was taken from the tender's *estimate*. A tender estimated at
     * 45k whose winning bid came in at 600k therefore cleared on one Level 1
     * signature. Falls back to the estimate only while no bid is recommended
     * yet, which is the one case where nothing better exists.
     */
    private function awardValue(Tender $tender, EvaluationReport $report): float
    {
        $this->guardCurrency($tender->currency);

        $bid = $report->recommendedBid;

        if ($bid !== null) {
            $this->guardCurrency($bid->currency);

            return (float) $bid->total_amount;
        }

        return (float) $tender->estimated_value;
    }

    /**
     * Thresholds are denominated in USD and nothing converts between currencies.
     *
     * An IQD tender was compared against them raw, so a 50,000,000 IQD award
     * (about 38k USD, a Level 1 value) landed at Level 3 — the thresholds were
     * meaningless for any non-USD tender. Refusing is the honest answer until a
     * rate exists; silently levelling against the wrong currency is not.
     */
    private function guardCurrency(?string $currency): void
    {
        if ($currency !== null && $currency !== self::THRESHOLD_CURRENCY) {
            throw ValidationException::withMessages([
                'currency' => __(
                    'Approval thresholds are set in :currency and this tender is in :actual. Convert the tender to :currency before requesting approval.',
                    ['currency' => self::THRESHOLD_CURRENCY, 'actual' => $currency],
                ),
            ]);
        }
    }

    /**
     * Determine required approval level for an award value.
     * Level 1 (<= $50K), Level 2 ($50K-$500K), Level 3 (> $500K).
     * Thresholds from system_settings.
     */
    private function determineApprovalLevel(float $value): int
    {
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
