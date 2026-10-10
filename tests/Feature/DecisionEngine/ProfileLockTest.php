<?php

use App\Enums\EngineContractType;
use App\Enums\TenderStatus;
use App\Enums\TimelineFormula;
use App\Models\AuditLog;
use App\Models\DecisionEngineSchema;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Services\DecisionEngine\EngineProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FakeDecisionEngine;

uses(RefreshDatabase::class);

/**
 * The rule the whole integration rests on: if the lock call fails, the tender
 * does not open (INTEGRATION-DESIGN §3).
 *
 * Not "open and lock later", not a background retry. A tender accepting bids
 * against an unlocked profile is precisely the hole the lock closes: someone
 * could read the bids and then change the weights.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function lockOfficer(Tender $tender): User
{
    $role = Role::firstOrCreate(['slug' => 'lock-officer'], ['name' => 'Officer', 'is_system' => true]);
    foreach (['tenders.publish', 'tenders.update', 'tenders.create', 'tenders.view'] as $slug) {
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'tenders'])->id,
        ]);
    }

    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $user->projects()->attach($tender->project_id, ['id' => (string) Str::uuid(), 'project_role' => 'member', 'assigned_at' => now()]);

    return $user;
}

/** A draft that satisfies every publish prerequisite, scoring criteria included. */
function publishableTender(): Tender
{
    return Tender::factory()->draft()->withBoqAndCriteria()->create();
}

function flashedToast(): ?array
{
    return session('inertia.flash_data')['toast'] ?? null;
}

// ── The rule ───────────────────────────────────────────────────────────────

test('engine unreachable: the tender stays closed and the officer sees why', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);
    FakeDecisionEngine::unreachable();

    $this->actingAs($officer)
        ->from(route('tenders.show', $tender))
        ->post(route('tenders.publish', $tender))
        ->assertRedirect(route('tenders.show', $tender));

    $tender->refresh();
    expect($tender->status)->toBe(TenderStatus::Draft)
        ->and($tender->publish_date)->toBeNull()
        ->and($tender->engineProfile->locked_at)->toBeNull()
        ->and($tender->engineProfile->profile_key)->toBeNull();

    // The officer is told it did not open, and that the engine is the reason.
    expect(flashedToast()['type'])->toBe('error')
        ->and(flashedToast()['message'])->toContain(__('engine.error.unreachable'));

    // And the attempt is on the record, not just the eventual success.
    expect(AuditLog::where('auditable_id', $tender->id)->where('action', 'engine_lock_failed')->exists())->toBeTrue();
});

test('a profile created but never locked does not open the tender', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);

    // Creation reaches the engine; the lock call does not.
    FakeDecisionEngine::failOn('POST #/lock$#', fn () => Http::failedConnection());

    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft)
        ->and(FakeDecisionEngine::$profiles)->toHaveCount(1)
        ->and(collect(FakeDecisionEngine::$profiles)->first()['status'])->toBe('draft');
});

test('an engine refusal keeps the tender closed and passes on the reason', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);
    FakeDecisionEngine::failOn('POST #^/v1/profiles$#', fn () => FakeDecisionEngine::error(400, 'schema_validation_error', 'Weight for timeline is not allowed.'));

    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft)
        ->and(flashedToast()['message'])->toContain('Weight for timeline is not allowed.');
});

test('an engine with no token configured keeps the tender closed', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);
    config(['services.decision_engine.token' => null]);

    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft)
        ->and(flashedToast()['message'])->toContain(__('engine.error.not_configured'))
        ->and(FakeDecisionEngine::$requests)->toBe([]);
});

// ── When it works ──────────────────────────────────────────────────────────

test('publishing locks the profile in the engine before the tender opens', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);

    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    $tender->refresh();
    $profile = $tender->engineProfile;
    $engineCopy = FakeDecisionEngine::$profiles[$profile->profile_key];

    expect($tender->status)->toBe(TenderStatus::Published)
        ->and($engineCopy['status'])->toBe('locked')
        // The lock time is the engine's, never eTender's.
        ->and($profile->locked_at->toIso8601ZuluString())->toBe($engineCopy['locked_at'])
        ->and($profile->locked_by)->toBe($officer->id)
        // What eTender recorded is what the engine locked (2.0 and 2 compare
        // equal: JSON does not keep the distinction).
        ->and(EngineProfileService::canonical($profile->definition))->toBe(EngineProfileService::canonical($engineCopy['definition']))
        ->and($profile->schema_key)->toBe('etender_bid_evaluation');

    // MPC's standard weights, as whole percentages, and installation's formula.
    $criteria = collect($engineCopy['definition']['criteria'])->keyBy('key');
    expect($criteria['price']['weight'])->toBe(40)
        ->and($criteria['payment_flexibility']['weight'])->toBe(10)
        ->and($criteria['timeline']['formula']['type'])->toBe('relative_to_best');

    // Locked before opened: the lock call precedes nothing that opens it.
    $published = AuditLog::where('auditable_id', $tender->id)->where('action', 'published')->sole();
    expect($published->new_values['engine_profile_key'])->toBe($profile->profile_key);
});

test('a supply contract locks with ratio_to_best', function () {
    $tender = publishableTender();
    $tender->engineProfile->update(['contract_type' => EngineContractType::Supply]);

    $this->actingAs(lockOfficer($tender))->post(route('tenders.publish', $tender));

    $definition = $tender->fresh()->engineProfile->definition;
    expect(collect($definition['criteria'])->firstWhere('key', 'timeline')['formula']['type'])->toBe('ratio_to_best');
});

// ── Retries ────────────────────────────────────────────────────────────────

/**
 * The first attempt created the profile and failed to lock it. The retry
 * meets 409 profile_exists — expected, not an error — locks it, and opens.
 * One profile in the engine, not two.
 */
test('retrying after a failed lock opens the tender against the same profile', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);

    FakeDecisionEngine::failOn('POST #/lock$#', fn () => Http::failedConnection());
    $this->actingAs($officer)->post(route('tenders.publish', $tender));
    expect($tender->fresh()->status)->toBe(TenderStatus::Draft);

    FakeDecisionEngine::$overrides = [];
    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Published)
        ->and(FakeDecisionEngine::$profiles)->toHaveCount(1)
        ->and(FakeDecisionEngine::requestsTo('POST', '/v1/profiles'))->toHaveCount(4); // create, lock, create (409), lock
});

/**
 * Weights edited between attempts change the hash in the key, so the retry
 * locks a new profile. The abandoned one keeps its old weights and is never
 * adopted: eTender opens only against what it last sent.
 */
test('weights edited between attempts lock a new profile, never the abandoned one', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);

    FakeDecisionEngine::failOn('POST #/lock$#', fn () => Http::failedConnection());
    $this->actingAs($officer)->post(route('tenders.publish', $tender));
    $abandonedKey = array_key_first(FakeDecisionEngine::$profiles);

    $tender->engineProfile->update(['weight_price' => 50, 'weight_payment_flexibility' => 0]);
    FakeDecisionEngine::$overrides = [];
    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    $profile = $tender->fresh()->engineProfile;
    expect($tender->fresh()->status)->toBe(TenderStatus::Published)
        ->and($profile->profile_key)->not->toBe($abandonedKey)
        ->and(collect($profile->definition['criteria'])->firstWhere('key', 'price')['weight'])->toBe(50)
        ->and(FakeDecisionEngine::$profiles[$abandonedKey]['status'])->toBe('draft');
});

/**
 * A key that already exists with different content was not created by this
 * request. Locking and opening against it would put weights eTender never
 * sent behind the tender.
 */
test('a profile the engine holds with other weights is refused, and the tender stays closed', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);

    FakeDecisionEngine::failOn('POST #/lock$#', function ($request) {
        $key = urldecode(explode('/', parse_url($request->url(), PHP_URL_PATH))[3]);
        FakeDecisionEngine::$profiles[$key]['definition']['criteria'][3]['weight'] = 99;
        FakeDecisionEngine::$profiles[$key]['status'] = 'locked';
        FakeDecisionEngine::$profiles[$key]['locked_at'] = now()->toIso8601ZuluString();

        return Http::response([
            'key' => $key, 'status' => 'locked', 'locked_at' => FakeDecisionEngine::$profiles[$key]['locked_at'],
            'definition' => FakeDecisionEngine::$profiles[$key]['definition'],
        ]);
    });

    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft)
        ->and($tender->fresh()->engineProfile->locked_at)->toBeNull();
});

test('a lock the engine does not confirm does not open the tender', function () {
    $tender = publishableTender();
    FakeDecisionEngine::failOn('POST #/lock$#', fn ($request) => Http::response(['key' => 'x', 'status' => 'draft', 'locked_at' => null, 'definition' => []]));

    $this->actingAs(lockOfficer($tender))->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft);
});

// ── The schema ─────────────────────────────────────────────────────────────

/**
 * The engine mints a new schema version on every POST, so eTender registers
 * its schema once per content and reuses the version after that.
 */
test('the schema is registered once, not on every publish', function () {
    foreach (range(1, 3) as $_) {
        $tender = publishableTender();
        $this->actingAs(lockOfficer($tender))->post(route('tenders.publish', $tender));
        expect($tender->fresh()->status)->toBe(TenderStatus::Published);
    }

    expect(FakeDecisionEngine::requestsTo('POST', '/v1/schemas'))->toHaveCount(1)
        ->and(DecisionEngineSchema::count())->toBe(1);
});

test('the schema sent is eTender\'s own, without annotations', function () {
    $tender = publishableTender();
    $this->actingAs(lockOfficer($tender))->post(route('tenders.publish', $tender));

    $sent = FakeDecisionEngine::requestsTo('POST', '/v1/schemas')[0]['body'];
    expect($sent['key'])->toBe('etender_bid_evaluation')
        ->and($sent)->not->toHaveKey('_comment')
        ->and(collect($sent['criteria'])->firstWhere('key', 'bank_guarantee')['score_source'])->toBe('caller');
});

// ── Prerequisites and the frozen copy ─────────────────────────────────────

test('a tender with no contract type cannot open, and nothing reaches the engine', function () {
    $tender = publishableTender();
    $tender->engineProfile->update(['contract_type' => null, 'timeline_formula' => null]);

    $this->actingAs(lockOfficer($tender))->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft)
        ->and(flashedToast()['message'])->toContain(__('engine.publish.no_contract_type'))
        ->and(FakeDecisionEngine::$requests)->toBe([]);
});

test('an "other" contract type opens only with an explicitly chosen formula', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);

    $this->actingAs($officer)->put(route('tenders.engine-profile.update', $tender), [
        ...engineProfilePayload(),
        'contract_type' => 'other',
        'contract_type_label' => 'Consultancy',
        'timeline_formula' => null,
    ])->assertSessionHasErrors('timeline_formula');

    $this->actingAs($officer)->put(route('tenders.engine-profile.update', $tender), [
        ...engineProfilePayload(),
        'contract_type' => 'other',
        'contract_type_label' => 'Consultancy',
        'timeline_formula' => 'ratio_to_best',
    ])->assertSessionHasNoErrors();

    $profile = $tender->fresh()->engineProfile;
    expect($profile->timeline_formula)->toBe(TimelineFormula::RatioToBest)
        ->and($profile->contract_type_label)->toBe('Consultancy');
});

test('a seeded contract type fixes its own formula whatever is sent', function () {
    $tender = publishableTender();

    $this->actingAs(lockOfficer($tender))->put(route('tenders.engine-profile.update', $tender), [
        ...engineProfilePayload(),
        'contract_type' => 'installation',
        'timeline_formula' => 'ratio_to_best',
    ])->assertSessionHasNoErrors();

    expect($tender->fresh()->engineProfile->timeline_formula)->toBe(TimelineFormula::RelativeToBest);
});

test('weights that do not add up to 100 are refused', function () {
    $tender = publishableTender();

    $this->actingAs(lockOfficer($tender))->put(route('tenders.engine-profile.update', $tender), [
        ...engineProfilePayload(),
        'weight_price' => 50,
    ])->assertSessionHasErrors('weight_price');
});

test('once the tender has opened its criteria cannot be edited', function () {
    $tender = publishableTender();
    $officer = lockOfficer($tender);
    $this->actingAs($officer)->post(route('tenders.publish', $tender));

    // The editor is closed by the tender's status...
    $this->actingAs($officer)->put(route('tenders.engine-profile.update', $tender), engineProfilePayload(['weight_price' => 60, 'weight_payment_flexibility' => 0]))
        ->assertForbidden();

    // ...and the model refuses even a direct write.
    expect(fn () => $tender->fresh()->engineProfile->update(['weight_price' => 60]))
        ->toThrow(LogicException::class);
});

// ── The create wizard ──────────────────────────────────────────────────────

/**
 * "Publish now" saves the draft first: an engine failure must not throw away
 * the officer's uploads with it. The officer lands on the saved draft, where
 * Publish is the retry.
 */
test('publish-now with the engine down keeps the draft and sends the officer to it', function () {
    $project = Project::factory()->create();
    $officer = lockOfficer(Tender::factory()->for($project)->create());
    FakeDecisionEngine::unreachable();

    $response = $this->actingAs($officer)->post(route('tenders.store'), tenderPayload([
        'project_id' => $project->id,
        'title_en' => 'Saved Despite The Engine',
        'publish' => true,
    ]));

    $tender = Tender::where('title_en', 'Saved Despite The Engine')->sole();
    $response->assertRedirect(route('tenders.show', $tender));

    expect($tender->status)->toBe(TenderStatus::Draft)
        ->and($tender->documents()->count())->toBe(1)
        ->and(flashedToast()['type'])->toBe('error');
});
