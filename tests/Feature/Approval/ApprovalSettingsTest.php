<?php

use App\Models\ApprovalRequest;
use App\Models\EvaluationReport;
use App\Models\SystemSetting;
use App\Models\Tender;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * BUG-33 — the whole `approvals` settings group was inert.
 *
 * ApprovalService read `approval_threshold_level1` / `_level2`, but
 * SystemSettingSeeder seeds `approval.level1_threshold` / `.level2_threshold`.
 * The keys never matched, so the lookups returned null and the hardcoded
 * defaults always won. `approval.expiry_days` was read nowhere at all —
 * `now()->addDays(7)` appeared verbatim in four places.
 *
 * These assert the settings actually drive behaviour, so a re-regression to
 * hardcoded values fails rather than passing quietly.
 *
 * They read `required_level`, not `approval_level`: a chain now starts at level
 * 1 and escalates, so `approval_level` is the level a request currently sits at
 * while `required_level` is how high it must go. See ApprovalChainTest.
 */
function setApprovalSetting(string $key, string $value): void
{
    SystemSetting::updateOrCreate(
        ['key' => $key],
        ['value' => $value, 'group' => 'approvals', 'type' => 'string', 'description' => 'test'],
    );
}

function raiseApproval(float $estimatedValue): ApprovalRequest
{
    $tender = Tender::factory()->create(['estimated_value' => $estimatedValue]);
    $report = EvaluationReport::factory()->create(['tender_id' => $tender->id]);

    return app(ApprovalService::class)->requestApproval($tender, $report, User::factory()->create());
}

test('approval level falls back to the built-in thresholds when nothing is configured', function () {
    expect(raiseApproval(10_000)->required_level)->toBe(1);
    expect(raiseApproval(200_000)->required_level)->toBe(2);
    expect(raiseApproval(900_000)->required_level)->toBe(3);
});

test('the configured level 1 threshold changes how an award is routed', function () {
    // 200k is Level 2 under the default 50k threshold.
    expect(raiseApproval(200_000)->required_level)->toBe(2);

    // Raise Level 1's ceiling above it and the same value must route to Level 1.
    setApprovalSetting('approval.level1_threshold', '250000');

    expect(raiseApproval(200_000)->required_level)->toBe(1);
});

test('the configured level 2 threshold changes how an award is routed', function () {
    expect(raiseApproval(900_000)->required_level)->toBe(3);

    setApprovalSetting('approval.level2_threshold', '1000000');

    expect(raiseApproval(900_000)->required_level)->toBe(2);
});

test('lowering the thresholds escalates a value that previously sat at level 1', function () {
    expect(raiseApproval(10_000)->required_level)->toBe(1);

    setApprovalSetting('approval.level1_threshold', '5000');
    setApprovalSetting('approval.level2_threshold', '8000');

    expect(raiseApproval(10_000)->required_level)->toBe(3);
});

test('the approval deadline honours approval.expiry_days', function () {
    setApprovalSetting('approval.expiry_days', '3');

    $request = raiseApproval(10_000);

    expect($request->deadline->toDateString())->toBe(now()->addDays(3)->toDateString());
});

test('the approval deadline falls back to seven days when unset', function () {
    $request = raiseApproval(10_000);

    expect($request->deadline->toDateString())->toBe(now()->addDays(7)->toDateString());
});

test('a zero or blank expiry setting does not produce an already-expired deadline', function () {
    setApprovalSetting('approval.expiry_days', '0');

    $request = raiseApproval(10_000);

    // Clamped to at least one day — a 0 would make the request expire the moment
    // it is raised, and escalateExpired() would immediately bump it.
    expect($request->deadline->isFuture())->toBeTrue();
});
