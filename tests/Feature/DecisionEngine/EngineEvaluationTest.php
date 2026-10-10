<?php

use App\Enums\BidStatus;
use App\Enums\EngineEvaluationStatus;
use App\Enums\TenderStatus;
use App\Models\ApprovalRequest;
use App\Models\Award;
use App\Models\Bid;
use App\Models\BidBoqPrice;
use App\Models\EngineBidAssessment;
use App\Models\EngineEvaluation;
use App\Models\EvaluationReport;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BidService;
use App\Services\DecisionEngine\EngineProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EngineResults;
use Tests\Support\FakeDecisionEngine;

uses(RefreshDatabase::class);

/**
 * Running an evaluation through the Decision Engine, in shadow mode.
 *
 * Failure handling is the substance here (INTEGRATION-DESIGN §6): an engine
 * that cannot be reached leaves nothing behind and a retry is safe; a batch
 * that never finishes becomes a visible stuck state, not an endless spinner;
 * a partial batch says plainly that its ranking is provisional.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function evalUser(Tender $tender, array $permissions): User
{
    $role = Role::create(['name' => 'Eval '.Str::random(4), 'slug' => 'eval-'.Str::lower(Str::random(6)), 'is_system' => false]);
    foreach ($permissions as $slug) {
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'evaluations'])->id);
    }

    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $user->projects()->attach($tender->project_id, ['id' => (string) Str::uuid(), 'project_role' => 'member', 'assigned_at' => now()]);

    return $user;
}

function evalOfficer(Tender $tender): User
{
    return evalUser($tender, ['evaluations.view', 'evaluations.engine_run']);
}

/**
 * A tender that opened with a locked profile and whose bids have been opened.
 * BEF-01's figures, so results can be read against the historical form.
 *
 * @return array{0: Tender, 1: array<string, Bid>}
 */
function openedEngineTender(bool $scored = true): array
{
    // Single envelope: the factory makes a third of tenders two-envelope at
    // random, and those wait for technical scores this fixture does not have.
    $tender = Tender::factory()->lockedEngine()->create([
        'status' => TenderStatus::UnderEvaluation,
        'is_two_envelope' => false,
    ]);

    $bids = [];
    foreach ([
        'BID-TAJ' => ['Taj Iraq Company', 15000, 14, [89, 100, 100]],
        'BID-MUH' => ['Muhammed Hussein', 14250, 16, [71, 75, 100]],
        'BID-ABU' => ['Abu Ali', 14250, 14, [82, 85, 100]],
    ] as $ref => [$name, $amount, $days, [$tech, $refs, $pay]]) {
        $bid = Bid::factory()->create([
            'tender_id' => $tender->id,
            'vendor_id' => Vendor::factory()->create(['company_name' => $name])->id,
            'bid_reference' => $ref,
            'total_amount' => $amount,
            'duration_days' => $days,
            'status' => BidStatus::Opened,
            'is_sealed' => false,
            'submitted_at' => now()->subDays(3),
            'opened_at' => now()->subDay(),
        ]);

        if ($scored) {
            EngineBidAssessment::create([
                'tender_id' => $tender->id,
                'bid_id' => $bid->id,
                'technical_score' => $tech,
                'references_score' => $refs,
                'payment_flexibility_score' => $pay,
                'bank_guarantee' => true,
                'mandatory_documents' => true,
            ]);
        }

        $bids[$ref] = $bid;
    }

    return [$tender, $bids];
}

function runEvaluation(Tender $tender, User $officer)
{
    return test()->actingAs($officer)->from(route('tenders.engine-evaluation.show', $tender))
        ->post(route('tenders.engine-evaluation.run', $tender));
}

function flashedEngineToast(): ?array
{
    return session('inertia.flash_data')['toast'] ?? null;
}

// ── Engine unreachable at evaluation ───────────────────────────────────────

test('an unreachable engine leaves no evaluation behind, and says so', function () {
    [$tender] = openedEngineTender();
    FakeDecisionEngine::unreachable();

    runEvaluation($tender, evalOfficer($tender))->assertRedirect();

    expect(EngineEvaluation::count())->toBe(0)
        ->and(flashedEngineToast()['type'])->toBe('error')
        ->and(flashedEngineToast()['message'])->toContain(__('engine.error.unreachable'));
});

test('after an unreachable engine, running again is safe and creates one evaluation', function () {
    [$tender] = openedEngineTender();
    $officer = evalOfficer($tender);

    FakeDecisionEngine::unreachable();
    runEvaluation($tender, $officer);
    FakeDecisionEngine::unreachable(false);
    runEvaluation($tender, $officer);

    expect(EngineEvaluation::count())->toBe(1)
        ->and(FakeDecisionEngine::$batches)->toHaveCount(1);
});

/**
 * The same submission carries the same Idempotency-Key, so once the engine
 * honours it on batches, a retry after a lost response returns the original
 * batch rather than a second one.
 */
test('the Idempotency-Key is a hash of the exact request, sent on every submission', function () {
    [$tender] = openedEngineTender();
    $officer = evalOfficer($tender);

    // The response is lost after the engine accepted the batch.
    FakeDecisionEngine::failOn('POST #^/v1/decisions/batch$#', fn () => Http::failedConnection());
    runEvaluation($tender, $officer);
    FakeDecisionEngine::$overrides = [];
    runEvaluation($tender, $officer);

    $submissions = FakeDecisionEngine::requestsTo('POST', '/v1/decisions/batch');
    $keys = array_map(fn ($r) => $r['headers']['Idempotency-Key'][0] ?? null, $submissions);

    expect($submissions)->toHaveCount(2)
        ->and($keys[0])->not->toBeNull()
        ->and($keys[0])->toBe($keys[1])
        ->and($keys[0])->toBe(hash('sha256', EngineProfileService::canonical($submissions[1]['body'])))
        ->and(EngineEvaluation::sole()->idempotency_key)->toBe($keys[0]);
});

// ── Running ────────────────────────────────────────────────────────────────

test('a completed evaluation reproduces BEF-01 and is kept, final', function () {
    [$tender, $bids] = openedEngineTender();
    runEvaluation($tender, evalOfficer($tender));

    $evaluation = EngineEvaluation::sole();
    $this->actingAs(evalOfficer($tender))
        ->getJson(route('tenders.engine-evaluation.status', [$tender, $evaluation]))
        ->assertOk()
        ->assertJson(['status' => 'completed', 'final' => true, 'stuck' => false]);

    $evaluation->refresh();
    expect($evaluation->status)->toBe(EngineEvaluationStatus::Completed)
        ->and(array_column($evaluation->result['ranking']['order'], 'reference'))->toBe(['BID-TAJ', 'BID-ABU', 'BID-MUH'])
        ->and(array_column($evaluation->result['ranking']['order'], 'weighted_total'))->toEqual([95.69, 94.9, 88.84])
        ->and($evaluation->result['ranking']['margin'])->toEqual(0.79);

    // Final means final: a later poll does not ask the engine again.
    $polls = count(FakeDecisionEngine::requestsTo('GET', '/v1/decisions/batch/'));
    $this->actingAs(evalOfficer($tender))->getJson(route('tenders.engine-evaluation.status', [$tender, $evaluation]));
    expect(FakeDecisionEngine::requestsTo('GET', '/v1/decisions/batch/'))->toHaveCount($polls)
        ->and(fn () => $evaluation->update(['result' => []]))->toThrow(LogicException::class);
});

test('the results screen shows the ranking, the margin flag, and never an award', function () {
    [$tender] = openedEngineTender();
    $officer = evalOfficer($tender);
    runEvaluation($tender, $officer);
    $this->actingAs($officer)->getJson(route('tenders.engine-evaluation.status', [$tender, EngineEvaluation::sole()]));

    $this->actingAs($officer)->get(route('tenders.engine-evaluation.show', $tender))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('evaluation/EngineEvaluation')
            ->where('result.provisional', false)
            ->where('result.margin', 0.79)
            ->where('result.marginFlagged', true)
            ->where('result.ranking.0.reference', 'BID-TAJ')
            ->where('result.ranking.0.vendorName', 'Taj Iraq Company')
            ->has('result.ranking.0.criteria', 5)
            ->where('result.ranking.0.criteria.1.key', 'technical')
            ->where('result.ranking.0.criteria.1.source', 'caller')
            ->missing('result.award')
            ->missing('result.recommended'));
});

test('a re-run cites the evaluation it replaces', function () {
    [$tender] = openedEngineTender();
    $officer = evalOfficer($tender);
    runEvaluation($tender, $officer);
    $first = EngineEvaluation::sole();
    $this->actingAs($officer)->getJson(route('tenders.engine-evaluation.status', [$tender, $first]));

    EngineBidAssessment::query()->first()->update(['technical_score' => 50]);
    runEvaluation($tender, $officer);

    $second = EngineEvaluation::where('id', '!=', $first->id)->sole();
    expect($second->supersedes_batch_id)->toBe($first->batch_id)
        ->and(FakeDecisionEngine::$batches[$second->batch_id]['supersedes'])->toBe($first->batch_id);
});

// ── Stuck ──────────────────────────────────────────────────────────────────

test('a batch that never finishes becomes a visible stuck state, not an endless wait', function () {
    [$tender] = openedEngineTender();
    $officer = evalOfficer($tender);
    FakeDecisionEngine::keepBatchesProcessing();
    runEvaluation($tender, $officer);
    $evaluation = EngineEvaluation::sole();

    $this->actingAs($officer)->getJson(route('tenders.engine-evaluation.status', [$tender, $evaluation]))
        ->assertJson(['status' => 'processing', 'final' => false, 'stuck' => false]);

    $this->travel(config('services.decision_engine.stuck_after_seconds') + 1)->seconds();

    $this->actingAs($officer)->getJson(route('tenders.engine-evaluation.status', [$tender, $evaluation]))
        ->assertJson(['status' => 'processing', 'stuck' => true, 'batchId' => $evaluation->batch_id]);

    // It cannot be re-sent while unfinished: the engine will not supersede it.
    runEvaluation($tender, $officer)->assertSessionHasErrors('evaluation');
    expect(EngineEvaluation::count())->toBe(1);

    // "Check again" finds it once the engine finishes.
    FakeDecisionEngine::keepBatchesProcessing(false);
    $this->actingAs($officer)->getJson(route('tenders.engine-evaluation.status', [$tender, $evaluation]))
        ->assertJson(['status' => 'completed', 'stuck' => false, 'final' => true]);
});

test('an engine unreachable while checking leaves the stored state alone and says so', function () {
    [$tender] = openedEngineTender();
    $officer = evalOfficer($tender);
    FakeDecisionEngine::keepBatchesProcessing();
    runEvaluation($tender, $officer);
    $evaluation = EngineEvaluation::sole();

    FakeDecisionEngine::unreachable();
    $this->actingAs($officer)->getJson(route('tenders.engine-evaluation.status', [$tender, $evaluation]))
        ->assertOk()
        ->assertJson(['status' => 'queued', 'final' => false])
        ->assertJsonPath('unreachable', __('engine.error.unreachable'));
});

// ── Partial ────────────────────────────────────────────────────────────────

test('a partial evaluation is labelled provisional and says which bids failed and why', function () {
    [$tender, $bids] = openedEngineTender();
    $officer = evalOfficer($tender);

    // The officer ran with every score in, but the engine scored one bid
    // incompletely and refused another at a gate.
    FakeDecisionEngine::batchResult(function (array $body, string $id) {
        $body['items'][1]['scores'] = []; // BID-MUH: scores lost
        $body['items'][0]['gates']['mandatory_documents'] = false; // BID-ABU: review gate

        return EngineResults::completed($body, $id);
    });
    runEvaluation($tender, $officer);
    $this->actingAs($officer)->getJson(route('tenders.engine-evaluation.status', [$tender, EngineEvaluation::sole()]));

    expect(EngineEvaluation::sole()->status)->toBe(EngineEvaluationStatus::Partial);

    $this->actingAs($officer)->get(route('tenders.engine-evaluation.show', $tender))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.status', 'partial')
            ->where('result.provisional', true)
            ->where('result.ranking', fn ($ranking) => collect($ranking)->contains(fn ($r) => $r['reference'] === 'BID-MUH' && $r['complete'] === false
                && $r['missing'] === ['technical', 'references', 'payment_flexibility']))
            ->where('result.notRanked', fn ($rows) => collect($rows)->contains(fn ($r) => $r['reference'] === 'BID-ABU'
                && $r['failedGates'] === ['mandatory_documents'] && $r['outcome'] === 'review')));
});

// ── Before it can run ──────────────────────────────────────────────────────

test('nothing is sent while any bid is missing scores or checks', function () {
    [$tender, $bids] = openedEngineTender(scored: false);

    runEvaluation($tender, evalOfficer($tender))->assertSessionHasErrors('evaluation');

    expect(FakeDecisionEngine::requestsTo('POST', '/v1/decisions/batch'))->toBe([])
        ->and(EngineEvaluation::count())->toBe(0);
});

test('an evaluation cannot run while the bids are still sealed', function () {
    [$tender] = openedEngineTender();
    $tender->update(['status' => TenderStatus::SubmissionClosed]);

    runEvaluation($tender, evalOfficer($tender))->assertSessionHasErrors('evaluation');
    expect(FakeDecisionEngine::$requests)->toBe([]);
});

/**
 * Tenders that opened before engine scoring shipped are not locked now —
 * that would be "open first, lock later". The screen says why.
 */
test('a tender that opened before engine scoring explains it is unavailable', function () {
    $tender = Tender::factory()->create(['status' => TenderStatus::UnderEvaluation]);
    $officer = evalOfficer($tender);

    $this->actingAs($officer)->get(route('tenders.engine-evaluation.show', $tender))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unavailableReason', 'engine.unavailable.opened_before_engine')
            ->where('profile', null));

    runEvaluation($tender, $officer)->assertSessionHasErrors('evaluation');
    expect(FakeDecisionEngine::$requests)->toBe([]);
});

// ── Scores and attestations ────────────────────────────────────────────────

test('a gate attestation records who confirmed it and when', function () {
    [$tender, $bids] = openedEngineTender(scored: false);
    $officer = evalOfficer($tender);
    // To the second: the column stores no fraction.
    $this->freezeSecond();

    $this->actingAs($officer)->put(route('tenders.engine-evaluation.assessments.update', [$tender, $bids['BID-TAJ']]), [
        'technical_score' => 89,
        'bank_guarantee' => true,
    ])->assertSessionHasNoErrors();

    $assessment = $bids['BID-TAJ']->fresh()->engineAssessment;
    expect($assessment->bank_guarantee)->toBeTrue()
        ->and($assessment->bank_guarantee_attested_by)->toBe($officer->id)
        ->and($assessment->bank_guarantee_attested_at->equalTo(now()))->toBeTrue()
        ->and($assessment->scored_by)->toBe($officer->id)
        ->and($assessment->mandatory_documents)->toBeNull();
});

test('someone who can view but not run cannot enter scores or run', function () {
    [$tender, $bids] = openedEngineTender(scored: false);
    $viewer = evalUser($tender, ['evaluations.view']);

    $this->actingAs($viewer)->get(route('tenders.engine-evaluation.show', $tender))->assertOk();
    $this->actingAs($viewer)->put(route('tenders.engine-evaluation.assessments.update', [$tender, $bids['BID-TAJ']]), ['technical_score' => 90])
        ->assertForbidden();
    $this->actingAs($viewer)->post(route('tenders.engine-evaluation.run', $tender))->assertForbidden();
});

test('someone off the project sees nothing', function () {
    [$tender] = openedEngineTender();
    $outsider = evalOfficer(Tender::factory()->create());

    $this->actingAs($outsider)->get(route('tenders.engine-evaluation.show', $tender))->assertForbidden();
});

// ── Shadow mode ────────────────────────────────────────────────────────────

test('the engine ranking sits beside the existing evaluation and touches nothing on the award path', function () {
    [$tender] = openedEngineTender();
    $officer = evalOfficer($tender);
    runEvaluation($tender, $officer);
    $this->actingAs($officer)->getJson(route('tenders.engine-evaluation.status', [$tender, EngineEvaluation::sole()]));

    $this->actingAs($officer)->get(route('tenders.engine-evaluation.show', $tender))
        ->assertInertia(fn (Assert $page) => $page
            // No committee scores yet: nothing ranked by both, so nothing compared.
            ->where('comparison.moduleScored', false)
            ->where('comparison.comparedCount', 0)
            ->has('comparison.rows', 3));

    expect(EvaluationReport::count())->toBe(0)
        ->and(ApprovalRequest::count())->toBe(0)
        ->and(Award::count())->toBe(0)
        ->and($tender->fresh()->status)->toBe(TenderStatus::UnderEvaluation);
});

// ── The vendor's duration ──────────────────────────────────────────────────

test('a bid on an engine-scored tender cannot be submitted without a duration', function () {
    // Single envelope, so the duration check is the one that answers — a
    // two-envelope bid would first be refused for lacking technical documents.
    $tender = Tender::factory()->published()->lockedEngine()->withBoq()->create([
        'submission_deadline' => now()->addWeek(),
        'is_two_envelope' => false,
    ]);
    $vendor = Vendor::factory()->create(['is_active' => true]);
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'vendor_id' => $vendor->id, 'status' => BidStatus::Draft, 'is_sealed' => false]);

    $service = app(BidService::class);
    foreach ($tender->boqSections()->with('items')->get()->flatMap->items as $item) {
        BidBoqPrice::create(['bid_id' => $bid->id, 'boq_item_id' => $item->id, 'unit_price' => 10, 'total_price' => 10 * $item->quantity]);
    }

    expect(fn () => $service->submit($bid->fresh()))->toThrow(RuntimeException::class, __('engine.bid.duration_required'));

    $bid->update(['duration_days' => 30]);
    $service->submit($bid->fresh());
    expect($bid->fresh()->is_sealed)->toBeTrue();
});

test('the duration is sealed with the price: never serialised by default', function () {
    $bid = Bid::factory()->create(['duration_days' => 21, 'total_amount' => 1000]);

    expect($bid->toArray())->not->toHaveKey('duration_days')
        ->and($bid->toArray())->not->toHaveKey('total_amount');
});
