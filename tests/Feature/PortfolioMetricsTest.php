<?php

use App\Enums\TenderStatus;
use App\Models\Award;
use App\Models\Bid;
use App\Models\Tender;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * These metrics were unreachable from any test.
 *
 * kpiMetrics() used AVG(DATEDIFF(...)) and monthlySpend() used DATE_FORMAT —
 * both MySQL-only, both throwing the moment anything else was behind them,
 * including the SQLite suite. So the only assurance they carried was that
 * production happened to run MySQL.
 */
test('the average cycle time is the days from publish to award', function () {
    Tender::factory()->create([
        'status' => TenderStatus::Awarded,
        'publish_date' => now()->subDays(30),
        'updated_at' => now()->subDays(20),
    ]);
    Tender::factory()->create([
        'status' => TenderStatus::Awarded,
        'publish_date' => now()->subDays(40),
        'updated_at' => now()->subDays(20),
    ]);

    // A tender that never published must not drag the average down.
    Tender::factory()->create([
        'status' => TenderStatus::Awarded,
        'publish_date' => null,
    ]);

    $metrics = app(DashboardService::class)->kpiMetrics();

    // 10 days and 20 days.
    expect($metrics['avg_cycle_time_days'])->toBe(15.0);
});

test('cycle time is zero rather than a division error when nothing is awarded', function () {
    $metrics = app(DashboardService::class)->kpiMetrics();

    expect($metrics['avg_cycle_time_days'])->toBe(0.0)
        ->and($metrics['avg_bids_per_tender'])->toBe(0.0);
});

test('the savings rate compares the award against the estimate', function () {
    $tender = Tender::factory()->create([
        'status' => TenderStatus::Awarded,
        'estimated_value' => 1_000_000,
    ]);
    $bid = Bid::factory()->create(['tender_id' => $tender->id]);
    Award::factory()->create([
        'tender_id' => $tender->id,
        'bid_id' => $bid->id,
        'award_amount' => 900_000,
    ]);

    expect(app(DashboardService::class)->kpiMetrics()['savings_rate_percent'])->toBe(10.0);
});

/**
 * The old implementation grouped on the UTC month, so an award made in the
 * small hours of Baghdad time landed in the month before.
 */
test('monthly spend groups awards by the project timezone month', function () {
    $tender = Tender::factory()->create();
    $bid = Bid::factory()->create(['tender_id' => $tender->id]);

    // 2026-08-31 22:00 UTC is 2026-09-01 01:00 in Asia/Baghdad.
    Award::factory()->create([
        'tender_id' => $tender->id,
        'bid_id' => $bid->id,
        'award_amount' => 250_000,
        'awarded_at' => '2026-08-31 22:00:00',
    ]);

    $months = collect(app(DashboardService::class)->portfolioOverview()['monthly_spend']);
    $september = $months->firstWhere('month', '2026-09');

    expect($september)->not->toBeNull()
        ->and((float) $september['total'])->toBe(250_000.0);

    expect((float) ($months->firstWhere('month', '2026-08')['total'] ?? 0))->toBe(0.0);
})->skip(fn () => now()->lt('2026-09-01') || now()->gt('2027-08-01'), 'only meaningful inside the trailing 12-month window');

test('monthly spend gap-fills months with no awards', function () {
    $months = collect(app(DashboardService::class)->portfolioOverview()['monthly_spend']);

    // Twelve months, every one present, so the chart never implies a
    // continuous series across a month it simply skipped.
    expect($months)->toHaveCount(12)
        ->and($months->every(fn ($m) => array_key_exists('total', $m)))->toBeTrue();
});
