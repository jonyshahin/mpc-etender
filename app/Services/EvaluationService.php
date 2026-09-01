<?php

namespace App\Services;

use App\Enums\BidStatus;
use App\Enums\CommitteeType;
use App\Enums\EnvelopeType;
use App\Models\Bid;
use App\Models\EvaluationReport;
use App\Models\EvaluationScore;
use App\Models\Tender;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Evaluation engine: aggregates scores, applies weights, ranks bids.
 */
class EvaluationService
{
    /**
     * Criterion envelopes an evaluator may score, given their committees.
     *
     * committee_type and criterion envelope are different vocabularies that
     * happen to share two words; comparing them directly left Combined
     * committees matching nothing. The union across memberships also fixes
     * the evaluator who sits on both committees of a two-envelope tender —
     * the old code took whichever membership the database returned first and
     * silently hid the other envelope.
     *
     * @param  iterable<int, CommitteeType>  $committeeTypes
     * @return array<int, string>
     */
    public function scorableEnvelopes(Tender $tender, iterable $committeeTypes): array
    {
        // On a single-envelope tender the split carries no meaning: one
        // committee judges the whole bid, however the criteria are labelled.
        if (! $tender->is_two_envelope) {
            return array_map(fn (EnvelopeType $e) => $e->value, EnvelopeType::cases());
        }

        $envelopes = [];

        foreach ($committeeTypes as $type) {
            $envelopes = array_merge($envelopes, $type->criterionEnvelopes());
        }

        return array_values(array_unique($envelopes));
    }

    /**
     * Aggregate scores from all evaluators for a tender.
     * For each bid: average each criterion score across evaluators,
     * then compute weighted total.
     *
     * @return array<int, array{bid_id: string, vendor_name: string, criteria_scores: array, weighted_total: float, rank: int}>
     */
    public function aggregateScores(Tender $tender, ?array $envelopes = null): array
    {
        // null means every criterion on the tender. The parameter used to be
        // a single envelope string, which forced callers to name one bucket
        // and made 'score everything' inexpressible.
        $criteria = $tender->evaluationCriteria()
            ->when($envelopes !== null, fn ($q) => $q->whereIn('envelope', $envelopes))
            ->get();

        $bids = $tender->bids()
            ->with('vendor:id,company_name')
            ->where('status', '!=', BidStatus::Withdrawn)
            ->where('status', '!=', BidStatus::Disqualified)
            ->get();

        $results = [];

        foreach ($bids as $bid) {
            $criteriaScores = [];
            $weightedTotal = 0;

            foreach ($criteria as $criterion) {
                $scores = EvaluationScore::where('bid_id', $bid->id)
                    ->where('criterion_id', $criterion->id)
                    ->get();

                if ($scores->isEmpty()) {
                    continue;
                }

                $avgScore = $scores->avg('score');
                $weightedScore = ($avgScore / $criterion->max_score) * $criterion->weight_percentage;

                $criteriaScores[] = [
                    'criterion_id' => $criterion->id,
                    'criterion_name' => $criterion->name_en,
                    'max_score' => (float) $criterion->max_score,
                    'weight' => (float) $criterion->weight_percentage,
                    'avg_score' => round($avgScore, 2),
                    'weighted_score' => round($weightedScore, 2),
                ];

                $weightedTotal += $weightedScore;
            }

            $results[] = [
                'bid_id' => $bid->id,
                'vendor_name' => $bid->vendor->company_name,
                'criteria_scores' => $criteriaScores,
                'weighted_total' => round($weightedTotal, 2),
                'rank' => 0,
            ];
        }

        // Sort by weighted total descending and assign ranks
        usort($results, fn ($a, $b) => $b['weighted_total'] <=> $a['weighted_total']);
        foreach ($results as $i => &$result) {
            $result['rank'] = $i + 1;
        }

        return $results;
    }

    /**
     * For two-envelope tenders: filter bids that pass the technical threshold.
     *
     * @return Collection<int, Bid>
     */
    public function getPassingBids(Tender $tender): Collection
    {
        $technicalResults = $this->aggregateScores($tender, CommitteeType::Technical->criterionEnvelopes());
        $threshold = (float) $tender->technical_pass_score;

        $passingBidIds = collect($technicalResults)
            ->filter(fn ($r) => $r['weighted_total'] >= $threshold)
            ->pluck('bid_id')
            ->all();

        // Disqualify bids below threshold
        $tender->bids()
            ->whereNotIn('id', $passingBidIds)
            ->where('status', '!=', BidStatus::Withdrawn)
            ->update(['status' => BidStatus::Disqualified]);

        return $tender->bids()->whereIn('id', $passingBidIds)->get();
    }

    /**
     * Compute final ranking for single-envelope or after both envelopes are scored.
     *
     * @return array<int, array{bid_id: string, vendor_name: string, technical_score: float, financial_score: float, final_score: float, rank: int}>
     */
    public function computeFinalRanking(Tender $tender): array
    {
        if (! $tender->is_two_envelope) {
            // Every criterion counts, whatever envelope it happens to carry.
            //
            // This used to aggregate envelope 'single' alone — a bucket no
            // reachable path writes, since the tender wizard only ever stores
            // 'technical' or 'financial'. Single-envelope is the DEFAULT
            // tender, so every bid ranked 0.00, ranks fell out of array order,
            // and the top of that list became the recommended bid on the
            // approval request the award is made from.
            $results = $this->aggregateScores($tender);

            return array_map(fn ($r) => [
                'bid_id' => $r['bid_id'],
                'vendor_name' => $r['vendor_name'],
                'technical_score' => $r['weighted_total'],
                'financial_score' => 0,
                'final_score' => $r['weighted_total'],
                'rank' => $r['rank'],
            ], $results);
        }

        // Two-envelope: combine technical + financial
        $technicalResults = collect($this->aggregateScores($tender, CommitteeType::Technical->criterionEnvelopes()));
        $financialResults = collect($this->aggregateScores($tender, CommitteeType::Financial->criterionEnvelopes()));

        $combined = [];
        foreach ($technicalResults as $tech) {
            $fin = $financialResults->firstWhere('bid_id', $tech['bid_id']);
            $financialScore = $fin ? $fin['weighted_total'] : 0;

            $combined[] = [
                'bid_id' => $tech['bid_id'],
                'vendor_name' => $tech['vendor_name'],
                'technical_score' => $tech['weighted_total'],
                'financial_score' => $financialScore,
                'final_score' => round($tech['weighted_total'] + $financialScore, 2),
                'rank' => 0,
            ];
        }

        usort($combined, fn ($a, $b) => $b['final_score'] <=> $a['final_score']);
        foreach ($combined as $i => &$result) {
            $result['rank'] = $i + 1;
        }

        return $combined;
    }

    /**
     * Generate evaluation report with full scoring matrix.
     */
    public function generateReport(Tender $tender, User $generatedBy): EvaluationReport
    {
        $ranking = $this->computeFinalRanking($tender);
        $recommendedBidId = ! empty($ranking) ? $ranking[0]['bid_id'] : null;

        // Generate PDF
        $pdf = Pdf::loadView('pdf.evaluation-report', [
            'tender' => $tender,
            'ranking' => $ranking,
        ]);

        $pdfPath = "reports/evaluation-{$tender->reference_number}.pdf";
        Storage::disk('s3')->put($pdfPath, $pdf->output());

        return EvaluationReport::create([
            'tender_id' => $tender->id,
            'generated_by' => $generatedBy->id,
            'report_type' => $tender->is_two_envelope ? 'two_envelope' : 'single_envelope',
            'summary' => 'Evaluation completed. '.count($ranking).' bids ranked.',
            'ranking_data' => $ranking,
            'recommended_bid_id' => $recommendedBidId,
            'status' => 'draft',
            'file_path' => $pdfPath,
            'generated_at' => now(),
        ]);
    }
}
