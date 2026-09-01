<?php

use App\Models\Bid;
use App\Models\CommitteeMember;
use App\Models\EvaluationCommittee;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/** Local copy — Pest helper names are global and cross-file load order is not guaranteed. */
function rankingEvaluator(Tender $tender, string $committeeType): User
{
    $role = Role::firstOrCreate(['slug' => 'evaluator'], ['name' => 'Evaluator', 'is_system' => true]);
    $permission = Permission::firstOrCreate(
        ['slug' => 'evaluations.score'],
        ['name' => 'Score Evaluations', 'module' => 'evaluations'],
    );
    $role->permissions()->syncWithoutDetaching([$permission->id]);

    $user = User::factory()->create(['role_id' => $role->id]);
    $committee = EvaluationCommittee::factory()->create([
        'tender_id' => $tender->id,
        'committee_type' => $committeeType,
    ]);
    CommitteeMember::factory()->create([
        'committee_id' => $committee->id,
        'user_id' => $user->id,
    ]);

    return $user;
}

/**
 * Scoring work must actually reach the ranking.
 *
 * `is_two_envelope` defaults to false, so single-envelope is the ordinary
 * tender. computeFinalRanking() aggregates those against envelope 'single',
 * but StoreTenderRequest only ever accepts 'technical' or 'financial' for a
 * criterion — so the bucket it reads is one no reachable path can write. Every
 * bid ranks 0.00, and the top of that ranking becomes recommended_bid_id on
 * the approval request that leads to the award.
 */
test('a single-envelope tender ranks on the scores that were actually given', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => false]);

    // What the tender wizard produces: a technical criterion.
    $criterion = EvaluationCriterion::factory()->create([
        'tender_id' => $tender->id,
        'envelope' => 'technical',
        'weight_percentage' => 100,
        'max_score' => 100,
    ]);

    $strong = Bid::factory()->create(['tender_id' => $tender->id]);
    $weak = Bid::factory()->create(['tender_id' => $tender->id]);
    $evaluator = User::factory()->create();

    EvaluationScore::factory()->create([
        'bid_id' => $strong->id,
        'criterion_id' => $criterion->id,
        'evaluator_id' => $evaluator->id,
        'score' => 90,
    ]);
    EvaluationScore::factory()->create([
        'bid_id' => $weak->id,
        'criterion_id' => $criterion->id,
        'evaluator_id' => $evaluator->id,
        'score' => 10,
    ]);

    $ranking = app(EvaluationService::class)->computeFinalRanking($tender);

    $byBid = collect($ranking)->keyBy('bid_id');

    expect((float) $byBid[$strong->id]['final_score'])
        ->toBeGreaterThan(0.0, 'the scores the evaluators gave must reach the ranking');

    expect((float) $byBid[$strong->id]['final_score'])
        ->toBeGreaterThan((float) $byBid[$weak->id]['final_score'], 'the better bid must rank higher');
});

/**
 * committee_type and criterion envelope are different vocabularies. Comparing
 * them directly meant a Combined committee matched `envelope = 'combined'` — a
 * value nothing can write — so its members got an empty scoring form whose
 * submit failed validation. They could not score at all.
 */
test('a combined committee can score every criterion on the tender', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => true]);

    foreach (['technical', 'financial'] as $envelope) {
        EvaluationCriterion::factory()->create([
            'tender_id' => $tender->id,
            'envelope' => $envelope,
        ]);
    }

    $evaluator = rankingEvaluator($tender, 'combined');

    $this->actingAs($evaluator)
        ->get(route('evaluations.score.index', $tender))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            expect($page->toArray()['props']['criteria'])->toHaveCount(2);
        });
});

/**
 * On a single-envelope tender the technical/financial split carries no meaning:
 * one committee judges the whole bid, however the criteria happen to be
 * labelled by the tender wizard.
 */
test('a technical committee on a single-envelope tender sees every criterion', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => false]);

    foreach (['technical', 'financial'] as $envelope) {
        EvaluationCriterion::factory()->create([
            'tender_id' => $tender->id,
            'envelope' => $envelope,
        ]);
    }

    $this->actingAs(rankingEvaluator($tender, 'technical'))
        ->get(route('evaluations.score.index', $tender))
        ->assertInertia(function (AssertableInertia $page) {
            expect($page->toArray()['props']['criteria'])->toHaveCount(2);
        });
});

/**
 * On a two-envelope tender the split is real and must hold: a technical
 * evaluator has no standing to judge price.
 */
test('a technical committee on a two-envelope tender sees only technical criteria', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => true]);

    $technical = EvaluationCriterion::factory()->create([
        'tender_id' => $tender->id,
        'envelope' => 'technical',
    ]);
    EvaluationCriterion::factory()->create([
        'tender_id' => $tender->id,
        'envelope' => 'financial',
    ]);

    $this->actingAs(rankingEvaluator($tender, 'technical'))
        ->get(route('evaluations.score.index', $tender))
        ->assertInertia(function (AssertableInertia $page) use ($technical) {
            $criteria = $page->toArray()['props']['criteria'];

            expect($criteria)->toHaveCount(1)
                ->and($criteria[0]['id'])->toBe($technical->id);
        });
});

/**
 * The evaluator's own scores could not be posted against a criterion they have
 * no standing to judge — StoreScoresRequest checked only that the id existed
 * somewhere in the table.
 */
test('scores cannot be posted against a criterion outside your envelope', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => true]);
    $financial = EvaluationCriterion::factory()->create([
        'tender_id' => $tender->id,
        'envelope' => 'financial',
        'max_score' => 100,
    ]);
    $bid = Bid::factory()->create(['tender_id' => $tender->id]);

    $this->actingAs(rankingEvaluator($tender, 'technical'))
        ->post(route('evaluations.score.store', [$tender, $bid]), [
            'scores' => [['criterion_id' => $financial->id, 'score' => 99]],
        ])
        ->assertNotFound();

    expect(EvaluationScore::count())->toBe(0);
});
