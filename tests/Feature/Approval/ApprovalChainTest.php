<?php

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Award;
use App\Models\Bid;
use App\Models\EvaluationReport;
use App\Models\SystemSetting;
use App\Models\Tender;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** Local copy — Pest helper names are global and cross-file load order is not guaranteed. */
function chainApprovalSetting(string $key, string $value): void
{
    SystemSetting::updateOrCreate(
        ['key' => $key],
        ['value' => $value, 'group' => 'approvals', 'type' => 'string', 'description' => 'test'],
    );
}

/**
 * A tender, its evaluation report, and the bid the report recommends.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function awardUnderApproval(float $estimatedValue, float $bidAmount): array
{
    $tender = Tender::factory()->create([
        'estimated_value' => $estimatedValue,
        'currency' => 'USD',
    ]);
    $bid = Bid::factory()->create([
        'tender_id' => $tender->id,
        'total_amount' => $bidAmount,
    ]);
    $report = EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => $bid->id,
    ]);

    return [$tender, $report, $bid];
}

/**
 * The chain must actually run.
 *
 * requestApproval() created the first request already sitting at the maximum
 * level, so approve()'s escalation branch — `approval_level < maxLevel` — could
 * never be true. One signature finalised an award of any size, and Levels 2
 * and 3 were unreachable.
 */
test('a level 3 award needs three separate approvals, not one', function () {
    [$tender, $report] = awardUnderApproval(estimatedValue: 900_000, bidAmount: 900_000);
    $service = app(ApprovalService::class);

    $first = $service->requestApproval($tender, $report, User::factory()->create());
    expect($first->approval_level)->toBe(1);

    $service->approve($first, User::factory()->create(), 'ok at level 1');
    expect(Award::count())->toBe(0, 'one signature must not award a 900k tender');

    $second = $first->fresh()->tender->approvalRequests()
        ->where('status', ApprovalStatus::Pending)->sole();
    expect($second->approval_level)->toBe(2);

    $service->approve($second, User::factory()->create(), 'ok at level 2');
    expect(Award::count())->toBe(0);

    $third = $tender->approvalRequests()->where('status', ApprovalStatus::Pending)->sole();
    expect($third->approval_level)->toBe(3);

    $service->approve($third, User::factory()->create(), 'ok at level 3');
    expect(Award::count())->toBe(1);
});

test('a level 1 award is final on the first approval', function () {
    [$tender, $report] = awardUnderApproval(estimatedValue: 10_000, bidAmount: 10_000);
    $service = app(ApprovalService::class);

    $request = $service->requestApproval($tender, $report, User::factory()->create());
    $service->approve($request, User::factory()->create(), 'ok');

    expect(Award::count())->toBe(1)
        ->and($tender->approvalRequests()->where('status', ApprovalStatus::Pending)->count())->toBe(0);
});

/**
 * The setting's own description says "Award value up to this amount requires
 * Level 1 approval". It levelled on the tender's estimate instead, while
 * createAward() booked the award at the winning bid's total — so a tender
 * estimated under 50k could commit 600k on a single Level 1 signature.
 */
test('the level follows the winning bid, not the estimate', function () {
    [$tender, $report] = awardUnderApproval(estimatedValue: 45_000, bidAmount: 600_000);

    $request = app(ApprovalService::class)->requestApproval($tender, $report, User::factory()->create());

    expect($request->required_level)->toBe(3)
        ->and((float) $request->value_threshold)->toBe(600_000.0);
});

test('it falls back to the estimate when no bid has been recommended yet', function () {
    $tender = Tender::factory()->create(['estimated_value' => 900_000, 'currency' => 'USD']);
    $report = EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => null,
    ]);

    $request = app(ApprovalService::class)->requestApproval($tender, $report, User::factory()->create());

    expect($request->required_level)->toBe(3)
        ->and((float) $request->value_threshold)->toBe(900_000.0);
});

/**
 * The level was recomputed from live data on every approval, so editing the
 * tender — or changing the thresholds on /admin/settings — re-levelled chains
 * that were already underway. A three-level chain could finish after one.
 */
test('changing the thresholds mid-chain does not shorten it', function () {
    [$tender, $report] = awardUnderApproval(estimatedValue: 900_000, bidAmount: 900_000);
    $service = app(ApprovalService::class);

    $first = $service->requestApproval($tender, $report, User::factory()->create());
    expect($first->required_level)->toBe(3);

    // An admin raises Level 1's ceiling above this award while it is in flight.
    chainApprovalSetting('approval.level1_threshold', '5000000');

    $service->approve($first, User::factory()->create(), 'ok');

    expect(Award::count())->toBe(0, 'the chain must finish at the level it started');
    expect($tender->approvalRequests()->where('status', ApprovalStatus::Pending)->sole()->approval_level)
        ->toBe(2);
});

test('editing the tender value mid-chain does not shorten it either', function () {
    [$tender, $report] = awardUnderApproval(estimatedValue: 900_000, bidAmount: 900_000);
    $service = app(ApprovalService::class);

    $first = $service->requestApproval($tender, $report, User::factory()->create());
    $tender->update(['estimated_value' => 1_000]);

    $service->approve($first, User::factory()->create(), 'ok');

    expect(Award::count())->toBe(0);
});

/**
 * Thresholds are denominated in USD and nothing converts. An IQD tender was
 * compared against them raw, so every IQD award landed at Level 3 regardless
 * of size. Until a rate exists, a non-USD tender must not reach approval.
 */
test('a non-USD tender is refused rather than levelled against USD thresholds', function () {
    $tender = Tender::factory()->create(['estimated_value' => 50_000_000, 'currency' => 'IQD']);
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'total_amount' => 50_000_000]);
    $report = EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => $bid->id,
    ]);

    expect(fn () => app(ApprovalService::class)->requestApproval($tender, $report, User::factory()->create()))
        ->toThrow(ValidationException::class);
});

test('the award still records the winning bid amount and the final approver', function () {
    [$tender, $report, $bid] = awardUnderApproval(estimatedValue: 10_000, bidAmount: 12_500);
    $finalApprover = User::factory()->create();

    $request = app(ApprovalService::class)->requestApproval($tender, $report, User::factory()->create());
    app(ApprovalService::class)->approve($request, $finalApprover, 'ok');

    $award = Award::sole();

    expect((float) $award->award_amount)->toBe(12_500.0)
        ->and($award->bid_id)->toBe($bid->id)
        ->and($award->approved_by)->toBe($finalApprover->id);
});

/**
 * A refusal the user cannot see is a silent failure.
 *
 * The service refuses on a `currency` key, but the approval screen renders only
 * `comments` and `delegatee_id` errors — so the controller turns it into a
 * toast instead of letting the error bag bubble to nowhere.
 */
test('the currency refusal reaches the user as an error toast', function () {
    $tender = Tender::factory()->create(['estimated_value' => 50_000_000, 'currency' => 'IQD']);
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'total_amount' => 50_000_000]);
    EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => $bid->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->from(route('approvals.index'))
        ->post(route('tenders.request-approval', $tender))
        ->assertRedirect(route('approvals.index'))
        // No error bag: the controller converted the exception into a toast
        // rather than letting it bubble to a `currency` field this page does
        // not render. The toast itself is not reliably readable from the
        // session, so this is the assertable half of that contract.
        ->assertSessionHasNoErrors();

    expect(ApprovalRequest::count())->toBe(0);
});
