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
use Illuminate\Support\Facades\DB;
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
     * Refuse a request that has already been decided.
     *
     * approve() writes a decision, moves the status, and at the final level
     * creates the Award; reject() writes a decision and moves the tender back
     * to under evaluation. None of it was guarded by state, so a second call on
     * an approved request wrote a second decision and a *second Award for the
     * same tender*, and a call on a rejected one flipped it to approved and
     * awarded it.
     *
     * Nothing could reach these paths while the decision endpoints were 403 for
     * everyone. Opening them is what makes this guard load-bearing, which is
     * why it lands with that fix rather than after it.
     */
    private function assertPending(ApprovalRequest $request): void
    {
        if ($request->status !== ApprovalStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('This approval request has already been decided.'),
            ]);
        }
    }

    /**
     * Run $work on the request, once, with nobody else inside.
     *
     * assertPending() alone read the status off the caller's in-memory model.
     * Two requests that each loaded the record while it was still pending both
     * passed it — which is precisely what a double-clicked Approve button, or
     * two approvers acting at once, produces. At the final level that wrote a
     * second decision and a SECOND AWARD for the same tender; at an
     * intermediate level it forked the chain into two competing next-level
     * requests. There is no unique constraint on awards.tender_id to catch it.
     *
     * Re-reading inside the transaction is what closes it: the second caller
     * sees the row as it now stands rather than as it was when they loaded it.
     * lockForUpdate() adds real serialisation on MySQL, where two callers can
     * genuinely be inside at the same instant.
     *
     * Same shape as BidOpeningRequestService::confirm(), which claims its row
     * for the same reason.
     *
     * @param  callable(ApprovalRequest): void  $work
     */
    private function decideOnce(ApprovalRequest $request, callable $work): void
    {
        DB::transaction(function () use ($request, $work) {
            $fresh = ApprovalRequest::whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPending($fresh);

            $work($fresh);

            // The caller's copy is now stale in exactly the way that caused
            // this, so bring it back in step.
            $request->setRawAttributes($fresh->fresh()->getAttributes(), true);
        });
    }

    /**
     * Approve at current level. If more levels needed, creates next-level request.
     * If final level, triggers award creation.
     */
    public function approve(ApprovalRequest $request, User $approver, string $comments): void
    {
        $this->decideOnce($request, function (ApprovalRequest $request) use ($approver, $comments) {
            $this->applyApproval($request, $approver, $comments);
        });
    }

    private function applyApproval(ApprovalRequest $request, User $approver, string $comments): void
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
        $this->decideOnce($request, function (ApprovalRequest $request) use ($approver, $comments) {
            ApprovalDecision::create([
                'request_id' => $request->id,
                'approver_id' => $approver->id,
                'decision' => 'rejected',
                'comments' => $comments,
                'decided_at' => now(),
            ]);

            $request->update(['status' => ApprovalStatus::Rejected]);
            $request->tender->update(['status' => TenderStatus::UnderEvaluation]);
        });
    }

    /**
     * Delegate approval to another user.
     */
    public function delegate(ApprovalRequest $request, User $delegator, User $delegatee): void
    {
        $this->decideOnce($request, function (ApprovalRequest $request) use ($delegator, $delegatee) {
            if ($delegatee->id === $delegator->id) {
                throw ValidationException::withMessages([
                    'delegatee_id' => __('Choose someone other than yourself.'),
                ]);
            }

            // Project isolation still applies: handing an approval to
            // someone outside the project would show them the award value
            // and the recommended vendor for work they have no part in.
            if (! $delegatee->isAssignedToProject($request->tender->project_id)) {
                throw ValidationException::withMessages([
                    'delegatee_id' => __('That user is not assigned to this project.'),
                ]);
            }

            // The assignment, which is the part that was missing. Without
            // it delegate() wrote a history row, changed nothing about who
            // could sign, and reported success.
            $request->update(['delegated_to' => $delegatee->id]);

            ApprovalDecision::create([
                'request_id' => $request->id,
                'approver_id' => $delegatee->id,
                'decision' => 'delegated',
                'comments' => "Delegated by {$delegator->name}",
                'delegated_from' => $delegator->id,
                'decided_at' => now(),
            ]);
        });
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

        // recommended_bid_id is nullable, and generateReport() leaves it null
        // whenever the ranking comes back empty — every bid withdrawn or
        // disqualified. awardValue() already tolerates that, so a chain can
        // be raised on such a report and run all the way here, where three
        // unconditional dereferences turned the final approval into an
        // uncaught Error. The controller catches only ValidationException,
        // so it surfaced as a 500 rather than anything a user could act on.
        if ($winningBid === null) {
            throw ValidationException::withMessages([
                'report' => __('This evaluation report recommends no bid, so there is nothing to award. Regenerate it once the bids have been scored.'),
            ]);
        }

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
