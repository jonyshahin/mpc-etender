<?php

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Award;
use App\Models\Bid;
use App\Models\EvaluationReport;
use App\Models\Tender;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A tender sitting on a single-level approval, ready to be signed.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function approvalReadyToSign(): ApprovalRequest
{
    $tender = Tender::factory()->create(['is_two_envelope' => false, 'currency' => 'USD']);
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'total_amount' => 10_000]);
    $report = EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => $bid->id,
    ]);

    return app(ApprovalService::class)->requestApproval($tender, $report, User::factory()->create());
}

/**
 * assertPending() reads the status off the in-memory model, with no transaction,
 * no row lock and no conditional claim — so two requests that each loaded the
 * record before either wrote both pass the guard.
 *
 * Loading the model twice is exactly what two concurrent HTTP requests do. There
 * is no unique constraint on awards.tender_id, so nothing downstream catches it
 * either.
 *
 * The repo already has the fix for this shape: BidOpeningRequestService::confirm()
 * claims the row with a conditional UPDATE inside DB::transaction and throws when
 * zero rows matched.
 */
test('a bid cannot be awarded twice by two simultaneous approvals', function () {
    $request = approvalReadyToSign();
    $service = app(ApprovalService::class);

    // Two requests in flight, each holding its own copy loaded while the row
    // was still pending.
    $first = ApprovalRequest::findOrFail($request->id);
    $second = ApprovalRequest::findOrFail($request->id);

    $service->approve($first, User::factory()->create(), 'signed');

    try {
        $service->approve($second, User::factory()->create(), 'signed again');
    } catch (Throwable) {
        // Refusing the second is the correct outcome.
    }

    expect(Award::count())->toBe(1, 'one tender, one award');
    expect($request->fresh()->decisions()->count())->toBe(1, 'one signature recorded, not two');
});

/**
 * The same race at an intermediate level forks the chain rather than duplicating
 * the award: both calls escalate, so the tender ends up with two competing
 * level-2 requests.
 */
test('a simultaneous approval does not fork the chain into two next-level requests', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => false, 'currency' => 'USD']);
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'total_amount' => 900_000]);
    $report = EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => $bid->id,
    ]);

    $service = app(ApprovalService::class);
    $raised = $service->requestApproval($tender, $report, User::factory()->create());

    expect($raised->required_level)->toBeGreaterThan(1);

    $first = ApprovalRequest::findOrFail($raised->id);
    $second = ApprovalRequest::findOrFail($raised->id);

    $service->approve($first, User::factory()->create(), 'signed');

    try {
        $service->approve($second, User::factory()->create(), 'signed again');
    } catch (Throwable) {
        // Correct.
    }

    expect(ApprovalRequest::where('tender_id', $tender->id)
        ->where('status', ApprovalStatus::Pending)
        ->count())->toBe(1, 'one chain, not two');
});
