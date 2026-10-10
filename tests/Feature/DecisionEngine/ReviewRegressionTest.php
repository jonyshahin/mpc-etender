<?php

use App\Enums\BidStatus;
use App\Enums\TenderStatus;
use App\Models\Bid;
use App\Models\EngineBidAssessment;
use App\Models\EngineEvaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeDecisionEngine;

uses(RefreshDatabase::class);

/**
 * Defects found by the adversarial review of stage A before it was committed,
 * each pinned so it cannot come back.
 *
 * Several turn on what happens DURING the engine call: publishing makes up to
 * three HTTP calls with no row lock held, and the tender is an editable draft
 * throughout. The fake engine's overrides stand in for a second officer acting
 * in that window.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function reviewUser(Tender $tender, array $permissions): User
{
    $role = Role::create(['name' => 'R '.Str::random(4), 'slug' => 'rev-'.Str::lower(Str::random(6)), 'is_system' => false]);
    foreach ($permissions as $slug) {
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => explode('.', $slug)[0]])->id);
    }
    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $user->projects()->attach($tender->project_id, ['id' => (string) Str::uuid(), 'project_role' => 'member', 'assigned_at' => now()]);

    return $user;
}

function reviewPublisher(Tender $tender): User
{
    return reviewUser($tender, ['tenders.publish', 'tenders.update', 'tenders.view']);
}

/** Run something in the middle of the engine call that creates the profile. */
function duringProfileCreation(Closure $sideEffect): void
{
    FakeDecisionEngine::failOn('POST #^/v1/profiles$#', function ($request) use ($sideEffect) {
        $sideEffect();
        $body = $request->data();
        $key = $body['key'];
        unset($body['status']);
        FakeDecisionEngine::$profiles[$key] = ['definition' => $body, 'status' => 'draft', 'locked_at' => null];

        return Http::response(['key' => $key, 'status' => 'draft', 'locked_at' => null, 'definition' => $body], 201);
    });
}

// ── Publishing while the draft changes underneath ──────────────────────────

test('criteria saved during the engine call stop the tender opening, and nothing is frozen', function () {
    $tender = Tender::factory()->draft()->withBoqAndCriteria()->create();
    $officer = reviewPublisher($tender);

    // A second officer saves new weights while the engine is locking the old.
    duringProfileCreation(fn () => DB::table('tender_engine_profiles')
        ->where('tender_id', $tender->id)
        ->update(['weight_price' => 50, 'weight_payment_flexibility' => 0]));

    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    $profile = $tender->fresh()->engineProfile;
    expect($tender->fresh()->status)->toBe(TenderStatus::Draft)
        ->and($profile->locked_at)->toBeNull()
        ->and($profile->profile_key)->toBeNull()
        ->and(session('inertia.flash_data')['toast']['message'])->toContain(__('engine.publish.criteria_changed'));

    // The retry locks what the tender now holds.
    FakeDecisionEngine::$overrides = [];
    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    $profile = $tender->fresh()->engineProfile;
    expect($tender->fresh()->status)->toBe(TenderStatus::Published)
        ->and($profile->weight_price)->toBe(50)
        ->and(collect($profile->definition['criteria'])->firstWhere('key', 'price')['weight'])->toBe(50)
        ->and(collect($profile->definition['criteria'])->firstWhere('key', 'payment_flexibility')['active'])->toBeFalse();
});

test('a document deleted during the engine call keeps the tender closed', function () {
    $tender = Tender::factory()->draft()->withBoqAndCriteria()->create();

    duringProfileCreation(fn () => $tender->documents()->update(['is_current' => false]));

    $this->actingAs(reviewPublisher($tender))->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft)
        ->and($tender->fresh()->engineProfile->locked_at)->toBeNull();
});

test('a tender cancelled during the engine call is not reported as published', function () {
    $tender = Tender::factory()->draft()->withBoqAndCriteria()->create();

    duringProfileCreation(fn () => DB::table('tenders')->where('id', $tender->id)->update(['status' => TenderStatus::Cancelled->value]));

    $this->actingAs(reviewPublisher($tender))->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Cancelled)
        ->and(session('inertia.flash_data')['toast']['type'])->toBe('error');
});

test('the criteria editor refuses once a publish has opened the tender', function () {
    $tender = Tender::factory()->draft()->withBoqAndCriteria()->create();
    $officer = reviewPublisher($tender);
    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    $this->actingAs($officer)
        ->put(route('tenders.engine-profile.update', $tender), engineProfilePayload())
        ->assertForbidden();
});

// ── Two-envelope tenders ───────────────────────────────────────────────────

/**
 * Price is scored relative to the set, so a bid that will fail the technical
 * envelope must not be in it: it would set the price benchmark for the bids
 * that pass. The engine waits for technical scores and leaves out failures.
 */
test('a two-envelope tender waits for technical scores, then leaves out bids below the pass mark', function () {
    $tender = Tender::factory()->lockedEngine()->twoEnvelope(70)->create(['status' => TenderStatus::UnderEvaluation]);
    $criterion = EvaluationCriterion::factory()->create(['tender_id' => $tender->id, 'envelope' => 'technical', 'weight_percentage' => 100, 'max_score' => 100]);
    $evaluator = User::factory()->create();

    $bids = collect(['BID-PASS' => [80, 1000], 'BID-FAIL' => [40, 500]])->map(function ($spec, $ref) use ($tender) {
        $bid = Bid::factory()->create([
            'tender_id' => $tender->id, 'bid_reference' => $ref, 'total_amount' => $spec[1], 'duration_days' => 10,
            'status' => BidStatus::Opened, 'is_sealed' => false,
            'vendor_id' => Vendor::factory()->create(['company_name' => $ref])->id,
        ]);
        EngineBidAssessment::create([
            'tender_id' => $tender->id, 'bid_id' => $bid->id, 'technical_score' => 70, 'references_score' => 70,
            'payment_flexibility_score' => 70, 'bank_guarantee' => true, 'mandatory_documents' => true,
        ]);

        return $bid;
    });

    $officer = reviewUser($tender, ['evaluations.view', 'evaluations.engine_run']);

    // No technical scores yet: refused, nothing sent.
    $this->actingAs($officer)->post(route('tenders.engine-evaluation.run', $tender))->assertSessionHasErrors('evaluation');
    expect(FakeDecisionEngine::requestsTo('POST', '/v1/decisions/batch'))->toBe([]);

    foreach (['BID-PASS' => 80, 'BID-FAIL' => 40] as $ref => $score) {
        EvaluationScore::create(['bid_id' => $bids[$ref]->id, 'criterion_id' => $criterion->id, 'evaluator_id' => $evaluator->id, 'score' => $score, 'scored_at' => now()]);
    }

    $this->actingAs($officer)->post(route('tenders.engine-evaluation.run', $tender))->assertSessionHasNoErrors();

    $sent = FakeDecisionEngine::requestsTo('POST', '/v1/decisions/batch')[0]['body'];
    expect(array_column($sent['items'], 'reference'))->toBe(['BID-PASS']);

    $this->actingAs($officer)->get(route('tenders.engine-evaluation.show', $tender))
        ->assertInertia(fn (Assert $page) => $page->where('technicallyExcluded.0.reference', 'BID-FAIL'));
});

// ── The comparison ─────────────────────────────────────────────────────────

/**
 * The engine leaves out a bid that failed a condition; the existing module
 * ranks it. Comparing raw ranks would shift every bid below it and report a
 * disagreement where the two agree on the order.
 */
test('ranks are compared over the bids both methods ranked', function () {
    $tender = Tender::factory()->lockedEngine()->create(['status' => TenderStatus::UnderEvaluation, 'is_two_envelope' => false]);
    $criterion = EvaluationCriterion::factory()->create(['tender_id' => $tender->id, 'envelope' => 'technical', 'weight_percentage' => 100, 'max_score' => 100]);
    $evaluator = User::factory()->create();

    // Module order: GATED (99), A (90), B (80). Engine leaves GATED out.
    foreach (['A' => [90, 1000, true], 'B' => [80, 1100, true], 'GATED' => [99, 900, false]] as $ref => [$module, $amount, $guarantee]) {
        $bid = Bid::factory()->create([
            'tender_id' => $tender->id, 'bid_reference' => $ref, 'total_amount' => $amount, 'duration_days' => 10,
            'status' => BidStatus::Opened, 'is_sealed' => false,
        ]);
        EngineBidAssessment::create([
            'tender_id' => $tender->id, 'bid_id' => $bid->id, 'technical_score' => 80, 'references_score' => 80,
            'payment_flexibility_score' => 80, 'bank_guarantee' => $guarantee, 'mandatory_documents' => true,
        ]);
        EvaluationScore::create(['bid_id' => $bid->id, 'criterion_id' => $criterion->id, 'evaluator_id' => $evaluator->id, 'score' => $module, 'scored_at' => now()]);
    }

    $officer = reviewUser($tender, ['evaluations.view', 'evaluations.engine_run']);
    $this->actingAs($officer)->post(route('tenders.engine-evaluation.run', $tender));
    $this->actingAs($officer)->getJson(route('tenders.engine-evaluation.status', [$tender, EngineEvaluation::sole()]));

    $this->actingAs($officer)->get(route('tenders.engine-evaluation.show', $tender))
        ->assertInertia(fn (Assert $page) => $page
            ->where('comparison.comparedCount', 2)
            ->where('comparison.rows', fn ($rows) => collect($rows)->every(
                fn ($r) => $r['reference'] === 'GATED' ? $r['rankDifference'] === null : $r['rankDifference'] === 0,
            )));
});

// ── Concurrent runs ────────────────────────────────────────────────────────

test('a run that cannot get the submit lock is told an evaluation is in progress, not a 500', function () {
    [$tender] = openedEngineTender();
    config(['services.decision_engine.submit_lock_wait_seconds' => 0]);
    Cache::lock("engine-evaluation:{$tender->id}", 30)->get();

    $this->actingAs(evalOfficer($tender))
        ->post(route('tenders.engine-evaluation.run', $tender))
        ->assertSessionHasErrors(['evaluation' => __('engine.run.already_running')]);

    expect(EngineEvaluation::count())->toBe(0);
});
