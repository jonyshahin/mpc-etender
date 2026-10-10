<?php

namespace Tests\Support;

/**
 * Builds a batch result the way the Decision Engine does, for the fake.
 *
 * Mirrors the engine's phase 1 arithmetic for eTender's schema: gates first
 * (a failed gate takes the bid out of scoring and out of the baseline), price
 * and timeline relative to the best surviving bid, caller scores as given,
 * weights normalised, ranking by weighted total, margin between the top two.
 *
 * Kept honest by EngineFakeFidelityTest, which feeds it the inputs of a batch
 * run through the engine's real code and requires the same numbers back.
 */
final class EngineResults
{
    private const GATE_ON_FAIL = ['bank_guarantee' => 'reject', 'mandatory_documents' => 'review'];

    public static function completed(array $body, string $batchId): array
    {
        $profile = FakeDecisionEngine::$profiles[$body['profile_key']]['definition'];
        $criteria = collect($profile['criteria'])->keyBy('key');
        $active = fn (string $key) => (bool) ($criteria[$key]['active'] ?? false);

        $weightKeys = array_filter(['price', 'technical', 'timeline', 'references', 'payment_flexibility'], $active);
        $weightSum = array_sum(array_map(fn ($k) => $criteria[$k]['weight'], $weightKeys));
        $weight = fn (string $k) => $criteria[$k]['weight'] / $weightSum;

        // 1. Gates, per item.
        $items = [];
        foreach ($body['items'] as $item) {
            $gates = [['key' => 'submission_deadline', 'passed' => true, 'source' => 'platform']];
            $failedGate = null;
            $unresolved = false;

            foreach (array_keys(self::GATE_ON_FAIL) as $gate) {
                if (! $active($gate)) {
                    continue;
                }
                $passed = $item['gates'][$gate] ?? null;
                $gates[] = ['key' => $gate, 'passed' => $passed, 'source' => $passed === null ? null : 'caller'];
                $unresolved = $unresolved || $passed === null;
                $failedGate ??= $passed === false ? $gate : null;
            }

            $items[] = ['item' => $item, 'gates' => $gates, 'failedGate' => $failedGate, 'unresolved' => $unresolved];
        }

        // 2. Baseline: only bids that passed every gate.
        $baselineItems = array_filter($items, fn ($i) => $i['failedGate'] === null && ! $i['unresolved']);
        $bestPrice = min(array_map(fn ($i) => (float) $i['item']['input']['bid_amount'], $baselineItems) ?: [0]);
        $bestDays = min(array_map(fn ($i) => (float) $i['item']['input']['duration_days'], $baselineItems) ?: [0]);
        $timelineFormula = $criteria['timeline']['formula']['type'] ?? 'relative_to_best';

        $relative = fn (float $v, float $best) => $best > 0 ? max(0.0, 100 - ($v - $best) / $best * 100) : 0.0;
        $ratio = fn (float $v, float $best) => $v > 0 ? $best / $v * 100 : 0.0;

        $decisions = [];
        $totals = [];

        foreach ($items as $position => $i) {
            $ref = $i['item']['reference'];
            $decision = [
                'id' => 'dec_fake_'.$position,
                'status' => 'completed',
                'batch_id' => $batchId,
                'reference' => $ref,
                'gates' => $i['gates'],
                'criteria' => [],
                'weighted_score' => null,
                'missing_criteria' => [],
                'needs_human_review' => false,
                'review_reasons' => [],
                'error' => null,
                'decision' => ['value' => null, 'confidence' => null],
            ];

            if ($i['failedGate'] !== null) {
                $decision['decision']['value'] = self::GATE_ON_FAIL[$i['failedGate']];
                $decisions[] = $decision;

                continue;
            }

            if ($i['unresolved']) {
                $decision['status'] = 'incomplete';
                $decisions[] = $decision;

                continue;
            }

            // In schema order, as the engine returns them.
            $scores = [];
            foreach (['price', 'technical', 'timeline', 'references', 'payment_flexibility'] as $key) {
                if (! $active($key)) {
                    continue;
                }

                if ($key === 'price') {
                    $scores[$key] = [$relative((float) $i['item']['input']['bid_amount'], $bestPrice), 'engine', 'batch'];
                } elseif ($key === 'timeline') {
                    $days = (float) $i['item']['input']['duration_days'];
                    $scores[$key] = [
                        $timelineFormula === 'ratio_to_best' ? $ratio($days, $bestDays) : $relative($days, $bestDays),
                        'engine',
                        'batch',
                    ];
                } else {
                    $value = $i['item']['scores'][$key] ?? null;
                    $scores[$key] = [$value === null ? null : (float) $value, $value === null ? null : 'caller', 'item'];
                }
            }

            $sum = 0.0;
            $missing = [];
            $belowThreshold = false;
            foreach ($scores as $key => [$score, $source, $scope]) {
                if ($score === null) {
                    $missing[] = $key;
                    $decision['criteria'][] = ['key' => $key, 'scope' => $scope, 'score' => null, 'normalised' => null, 'weight' => round($weight($key), 6), 'weighted' => null, 'source' => null];

                    continue;
                }
                $weighted = $score * $weight($key);
                $sum += $weighted;
                $belowThreshold = $belowThreshold || $score < ($profile['review_policy']['any_criterion_below'] ?? 0);
                $decision['criteria'][] = [
                    'key' => $key,
                    'scope' => $scope,
                    'score' => round($score, 4),
                    'normalised' => round($score / 100, 4),
                    'weight' => round($weight($key), 6),
                    'weighted' => round($weighted, 2),
                    'source' => $source,
                ];
            }

            if ($missing !== []) {
                $decision['status'] = 'incomplete';
                $decision['missing_criteria'] = $missing;
                $totals[$ref] = ['total' => $sum, 'complete' => false];
            } else {
                $decision['weighted_score'] = round($sum, 2);
                $decision['needs_human_review'] = $belowThreshold;
                $decision['decision']['value'] = $belowThreshold ? 'review' : 'shortlist';
                $totals[$ref] = ['total' => $sum, 'complete' => true];
            }

            $decisions[] = $decision;
        }

        // 3. Ranking.
        uasort($totals, fn ($a, $b) => $b['total'] <=> $a['total']);
        $order = [];
        $rank = 0;
        foreach ($totals as $ref => $t) {
            $order[] = ['reference' => $ref, 'weighted_total' => round($t['total'], 2), 'rank' => ++$rank, 'complete' => $t['complete']];
        }

        $provisionalReasons = collect($decisions)->contains('status', 'incomplete') ? ['provisional:incomplete_decisions'] : [];
        $margin = count($order) >= 2 ? round(array_values($totals)[0]['total'] - array_values($totals)[1]['total'], 2) : null;
        $marginBelow = (float) ($profile['review_policy']['margin_below'] ?? 0);
        $reasons = $provisionalReasons;
        if ($margin !== null && $margin < $marginBelow) {
            $reasons[] = 'margin_below:'.number_format($marginBelow, 1, '.', '');
        }

        return [
            'batch_id' => $batchId,
            'status' => $provisionalReasons === [] ? 'completed' : 'partial',
            'schema' => ['key' => $body['schema_key'], 'version' => $body['schema_version']],
            'profile' => ['key' => $body['profile_key'], 'locked_at' => FakeDecisionEngine::$profiles[$body['profile_key']]['locked_at']],
            'supersedes' => $body['supersedes'] ?? null,
            'superseded_by' => [],
            'baseline' => [
                'included' => array_values(array_map(fn ($i) => $i['item']['reference'], $baselineItems)),
                'excluded' => array_values(array_map(fn ($i) => $i['item']['reference'], array_filter($items, fn ($i) => ! in_array($i, $baselineItems, true)))),
                'price' => ['best' => $bestPrice, 'formula' => 'relative_to_best'],
                'timeline' => ['best' => $bestDays, 'formula' => $timelineFormula],
            ],
            'decisions' => $decisions,
            'ranking' => [
                'provisional' => $provisionalReasons !== [],
                'order' => $order,
                'margin' => $margin,
                'needs_human_review' => $reasons !== [],
                'review_reasons' => $reasons,
            ],
            'created_at' => now()->toIso8601ZuluString(),
            'completed_at' => now()->toIso8601ZuluString(),
        ];
    }
}
