<?php

namespace App\Services\DecisionEngine;

use App\Models\Bid;
use App\Models\EngineEvaluation;
use App\Models\Tender;
use App\Services\EvaluationService;
use Illuminate\Support\Collection;

/**
 * Shapes an engine batch result, and the comparison with the existing
 * evaluation, for the results screen.
 *
 * Two things this deliberately does not do. It never names a winner: the top
 * of the ranking is the highest weighted total, arithmetic, and the screen
 * says so (DECISIONS §1.0). And it never softens a provisional ranking: a
 * partial batch carries no award recommendation, and every row says whether
 * its total is complete.
 */
class EngineResultPresenter
{
    public function __construct(private EvaluationService $evaluations) {}

    /**
     * @param  Collection<string, Bid>  $bidsByReference
     * @return array<string, mixed>|null null until the evaluation has finalised
     */
    public function result(EngineEvaluation $evaluation, Collection $bidsByReference): ?array
    {
        if (! $evaluation->isFinal() || $evaluation->result === null) {
            return null;
        }

        $batch = $evaluation->result;
        $ranking = $batch['ranking'] ?? ['order' => [], 'provisional' => true, 'margin' => null, 'review_reasons' => []];
        $decisions = collect($batch['decisions'] ?? [])->keyBy('reference');
        $bid = fn (string $ref) => $bidsByReference->get($ref);
        $marginBelow = (float) ($evaluation->profile->margin_below ?? 0);

        $ranked = collect($ranking['order'])->map(function (array $row) use ($decisions, $bid) {
            $decision = $decisions->get($row['reference'], []);

            return [
                'rank' => $row['rank'],
                'reference' => $row['reference'],
                'bidId' => $bid($row['reference'])?->id,
                'vendorName' => $bid($row['reference'])?->vendor?->company_name ?? $row['reference'],
                'weightedTotal' => (float) $row['weighted_total'],
                'complete' => (bool) ($row['complete'] ?? true),
                'outcome' => $decision['decision']['value'] ?? null,
                'missing' => $decision['missing_criteria'] ?? [],
                'criteria' => collect($decision['criteria'] ?? [])->map(fn (array $c) => [
                    'key' => $c['key'],
                    'score' => $c['score'] === null ? null : round((float) $c['score'], 2),
                    'weightPercent' => round(((float) ($c['weight'] ?? 0)) * 100, 2),
                    'weighted' => $c['weighted'] === null ? null : (float) $c['weighted'],
                    'source' => $c['source'] ?? null,
                ])->values()->all(),
            ];
        })->values();

        $rankedRefs = $ranked->pluck('reference')->all();

        // Every bid the engine scored but did not rank, and why — a failed
        // gate, missing scores, or a failure on the engine's side.
        $notRanked = $decisions->reject(fn ($d, $ref) => in_array($ref, $rankedRefs, true))
            ->map(fn (array $d, string $ref) => [
                'reference' => $ref,
                'bidId' => $bid($ref)?->id,
                'vendorName' => $bid($ref)?->vendor?->company_name ?? $ref,
                'status' => $d['status'],
                'outcome' => $d['decision']['value'] ?? null,
                'failedGates' => collect($d['gates'] ?? [])->where('passed', false)->pluck('key')->values()->all(),
                'unresolvedGates' => collect($d['gates'] ?? [])->whereNull('passed')->pluck('key')->values()->all(),
                'missing' => $d['missing_criteria'] ?? [],
                'error' => is_array($d['error'] ?? null) ? ($d['error']['message'] ?? null) : ($d['error'] ?? null),
            ])->values()->all();

        return [
            'evaluationId' => $evaluation->id,
            'batchId' => $evaluation->batch_id,
            'status' => $evaluation->status->value,
            'provisional' => (bool) ($ranking['provisional'] ?? false),
            'finalisedAt' => $evaluation->finalised_at?->toIso8601String(),
            'margin' => $ranking['margin'] === null ? null : (float) $ranking['margin'],
            'marginBelow' => $marginBelow,
            'marginFlagged' => collect($ranking['review_reasons'] ?? [])->contains(fn ($r) => str_starts_with($r, 'margin_below:')),
            'reviewReasons' => $ranking['review_reasons'] ?? [],
            'ranking' => $ranked->all(),
            'notRanked' => $notRanked,
            'baseline' => [
                'priceBest' => isset($batch['baseline']['price']['best']) ? (float) $batch['baseline']['price']['best'] : null,
                'timelineBest' => isset($batch['baseline']['timeline']['best']) ? (float) $batch['baseline']['timeline']['best'] : null,
                'timelineFormula' => $batch['baseline']['timeline']['formula'] ?? null,
                'excluded' => $batch['baseline']['excluded'] ?? [],
            ],
        ];
    }

    /**
     * The engine's order next to the existing module's, bid by bid (§1.1).
     *
     * A shadow period earns its name only if someone compares, so the
     * differences are computed here, not left for the reader to spot. The
     * existing ranking only counts once committees have scored: before that
     * every bid sits at zero and its "order" means nothing.
     *
     * @return array{moduleScored: bool, comparedCount: int, rows: array<int, array<string, mixed>>}
     */
    public function comparison(Tender $tender, ?array $result, Collection $bids): array
    {
        $module = collect($this->evaluations->aggregateScores($tender))
            ->filter(fn ($r) => $r['criteria_scores'] !== [])
            ->keyBy('bid_id');
        $moduleScored = $module->isNotEmpty();

        $engine = collect($result['ranking'] ?? [])->keyBy('bidId');

        // Ranks are compared over the bids BOTH methods ranked. Each side
        // ranks a different set — the engine leaves out a bid that failed a
        // condition, the module includes it — and comparing raw ranks across
        // the two would shift every bid below the gap and report
        // disagreements that do not exist.
        $common = $bids->pluck('id')->filter(fn ($id) => $engine->has($id) && $module->has($id))->values();
        $rankWithin = fn (Collection $rows, string $totalKey) => $rows
            ->only($common->all())
            ->sortByDesc($totalKey)
            ->keys()
            ->flip()
            ->map(fn (int $position) => $position + 1);
        $engineRanks = $rankWithin($engine, 'weightedTotal');
        $moduleRanks = $rankWithin($module, 'weighted_total');

        $rows = $bids->map(function (Bid $bid) use ($module, $engine, $engineRanks, $moduleRanks) {
            $e = $engine->get($bid->id);
            $m = $module->get($bid->id);
            $engineRank = $engineRanks->get($bid->id);
            $moduleRank = $moduleRanks->get($bid->id);

            return [
                'bidId' => $bid->id,
                'reference' => $bid->bid_reference,
                'vendorName' => $bid->vendor?->company_name ?? $bid->bid_reference,
                // Rank among the bids both methods ranked; null outside them.
                'engineRank' => $engineRank,
                'engineTotal' => $e['weightedTotal'] ?? null,
                'engineComplete' => $e['complete'] ?? null,
                'moduleRank' => $moduleRank,
                'moduleTotal' => $m['weighted_total'] ?? null,
                // Positive: the engine ranks the bid higher than the module does.
                'rankDifference' => $engineRank !== null && $moduleRank !== null ? $moduleRank - $engineRank : null,
            ];
        })->sortBy(fn ($r) => [$r['engineRank'] ?? PHP_INT_MAX, $r['moduleRank'] ?? PHP_INT_MAX])->values()->all();

        return [
            'moduleScored' => $moduleScored,
            'comparedCount' => $common->count(),
            'rows' => $rows,
        ];
    }
}
