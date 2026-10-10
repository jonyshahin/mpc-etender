<?php

use Tests\Support\EngineResults;
use Tests\Support\FakeDecisionEngine;

/**
 * The test stand-in for the Decision Engine must compute what the engine does.
 *
 * Every engine-facing feature test runs against FakeDecisionEngine, so a fake
 * that drifted from the real arithmetic would let eTender's screens be tested
 * against numbers the engine never produces. The fixtures were captured by
 * running eTender's own schema and these exact inputs through the engine's
 * code (its Postgres-backed test harness, 2026-10-10). BEF-01's historical
 * result — 95.69 / 94.90 / 88.84, won by 0.79 — falls out of it unchanged.
 */
function fidelityProfile(string $formula = 'relative_to_best'): array
{
    return [
        'schema_key' => 'etender_bid_evaluation',
        'schema_version' => 1,
        'key' => 'PRJ-T001-abc123',
        'label' => 'Probe tender',
        'context' => ['tender_number' => 'PRJ-T001', 'contract_type' => 'installation'],
        'criteria' => [
            ['key' => 'submission_deadline', 'active' => true],
            ['key' => 'bank_guarantee', 'active' => true],
            ['key' => 'mandatory_documents', 'active' => true],
            ['key' => 'price', 'active' => true, 'weight' => 40],
            ['key' => 'technical', 'active' => true, 'weight' => 20],
            ['key' => 'timeline', 'active' => true, 'weight' => 20, 'formula' => ['type' => $formula, 'direction' => 'lower_is_better']],
            ['key' => 'references', 'active' => true, 'weight' => 10],
            ['key' => 'payment_flexibility', 'active' => true, 'weight' => 10],
        ],
        'review_policy' => ['confidence_below' => 0.7, 'any_criterion_below' => 40, 'always_review_if' => [], 'margin_below' => 2.0],
    ];
}

function fidelityItem(string $ref, float $amount, int $days, ?array $scores, array $gates): array
{
    return array_filter([
        'reference' => $ref,
        'input' => ['bidder_name' => "Vendor {$ref}", 'bid_amount' => $amount, 'currency' => 'USD', 'duration_days' => $days, 'bid_date' => '2026-10-01'],
        'scores' => $scores,
        'gates' => $gates,
    ], fn ($v) => $v !== null);
}

function realEngineFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/fixtures/decision-engine/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}

/** The comparable substance of a batch result: everything but ids and clocks. */
function substance(array $batch): array
{
    return [
        'status' => $batch['status'],
        'included' => $batch['baseline']['included'],
        'excluded' => $batch['baseline']['excluded'],
        'best_price' => (float) $batch['baseline']['price']['best'],
        'best_days' => (float) $batch['baseline']['timeline']['best'],
        'ranking' => array_map(fn ($e) => [$e['reference'], (float) $e['weighted_total'], $e['rank'], $e['complete']], $batch['ranking']['order']),
        'provisional' => $batch['ranking']['provisional'],
        'margin' => $batch['ranking']['margin'] === null ? null : (float) $batch['ranking']['margin'],
        'review_reasons' => $batch['ranking']['review_reasons'],
        'decisions' => collect($batch['decisions'])->mapWithKeys(fn ($d) => [$d['reference'] => [
            'status' => $d['status'],
            'value' => $d['decision']['value'],
            'weighted_score' => $d['weighted_score'] === null ? null : (float) $d['weighted_score'],
            'missing' => $d['missing_criteria'],
            'gates' => array_map(fn ($g) => [$g['key'], $g['passed']], $d['gates']),
            // A list, not a map: the order the criteria come back in is part
            // of what the screens rely on.
            'weighted' => collect($d['criteria'])->map(fn ($c) => [$c['key'], $c['weighted'] === null ? null : (float) $c['weighted']])->all(),
        ]])->all(),
    ];
}

beforeEach(function () {
    FakeDecisionEngine::$profiles['PRJ-T001-abc123'] = ['definition' => fidelityProfile(), 'status' => 'locked', 'locked_at' => '2026-10-10T18:48:46Z'];
});

test('a completed batch matches the engine, including a gate-rejected bid', function () {
    $body = [
        'schema_key' => 'etender_bid_evaluation', 'schema_version' => 1, 'profile_key' => 'PRJ-T001-abc123',
        'items' => [
            fidelityItem('BID-1', 15000, 14, ['technical' => 89, 'references' => 100, 'payment_flexibility' => 100], ['bank_guarantee' => true, 'mandatory_documents' => true]),
            fidelityItem('BID-2', 14250, 16, ['technical' => 71, 'references' => 75, 'payment_flexibility' => 100], ['bank_guarantee' => true, 'mandatory_documents' => true]),
            fidelityItem('BID-3', 14250, 14, ['technical' => 82, 'references' => 85, 'payment_flexibility' => 100], ['bank_guarantee' => true, 'mandatory_documents' => true]),
            fidelityItem('BID-4', 9000, 10, ['technical' => 90, 'references' => 90, 'payment_flexibility' => 90], ['bank_guarantee' => false, 'mandatory_documents' => true]),
        ],
        'rank' => true,
    ];

    expect(substance(EngineResults::completed($body, 'bat_x')))
        ->toEqual(substance(realEngineFixture('batch-completed')));
});

test('a partial batch matches the engine: missing scores, and a review-gate failure', function () {
    $body = [
        'schema_key' => 'etender_bid_evaluation', 'schema_version' => 1, 'profile_key' => 'PRJ-T001-abc123',
        'items' => [
            fidelityItem('BID-1', 15000, 14, ['technical' => 89, 'references' => 100, 'payment_flexibility' => 100], ['bank_guarantee' => true, 'mandatory_documents' => true]),
            fidelityItem('BID-2', 14250, 16, null, ['bank_guarantee' => true, 'mandatory_documents' => true]),
            fidelityItem('BID-3', 14250, 14, ['technical' => 82, 'references' => 85, 'payment_flexibility' => 100], ['bank_guarantee' => true, 'mandatory_documents' => false]),
        ],
        'rank' => true,
    ];

    expect(substance(EngineResults::completed($body, 'bat_y')))
        ->toEqual(substance(realEngineFixture('batch-partial')));
});
