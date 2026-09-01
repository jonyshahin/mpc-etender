<?php

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\CommitteeMember;
use App\Models\EvaluationCommittee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * A sealed bid on a tender, with pricing that must not leave the server.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function sealedBidForScoring(Tender $tender, float $amount = 750_000): Bid
{
    return Bid::factory()->create([
        'tender_id' => $tender->id,
        'total_amount' => $amount,
        'encrypted_pricing_data' => json_encode(['unit_rate' => 1234.56]),
        'status' => BidStatus::Submitted,
        'is_sealed' => true,
        'opened_at' => null,
    ]);
}

/**
 * CLAUDE.md, non-negotiable: "Evaluators see only tenders assigned to their
 * committee". The route group carries only ['auth', 'verified'] — no
 * permission, no policy — and index() never checks membership: when the user
 * is on no committee it defaults the envelope to 'technical' and renders.
 */
test('a user on no committee cannot open the scoring screen', function () {
    $tender = Tender::factory()->create();
    sealedBidForScoring($tender);

    $this->actingAs(User::factory()->create())
        ->get(route('evaluations.score.index', $tender))
        ->assertForbidden();
});

/**
 * CLAUDE.md, non-negotiable: "Bid pricing encrypted at rest via Laravel
 * encrypt() — decrypted only after opening_date".
 *
 * Bid declares no $hidden, and getEncryptedPricingDataAttribute() decrypts on
 * access — which toArray() invokes while Inertia serialises the props. So the
 * whole model, plaintext pricing included, was being handed to the browser.
 */
test('sealed pricing never reaches the page props', function () {
    $tender = Tender::factory()->create();
    sealedBidForScoring($tender);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('evaluations.score.index', $tender));

    // Whatever the status, the one thing that must never appear is the price.
    expect($response->getContent())
        ->not->toContain('1234.56')
        ->not->toContain('750000');
});

/**
 * The other half of the contract: the gate must not lock out the people it
 * exists to admit.
 */
function evaluatorOnCommittee(Tender $tender, string $type = 'technical'): User
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
        'committee_type' => $type,
    ]);
    CommitteeMember::factory()->create([
        'committee_id' => $committee->id,
        'user_id' => $user->id,
    ]);

    return $user;
}

test('a committee member can still open the scoring screen', function () {
    $tender = Tender::factory()->create();
    sealedBidForScoring($tender);

    $this->actingAs(evaluatorOnCommittee($tender))
        ->get(route('evaluations.score.index', $tender))
        ->assertOk();
});

/**
 * Even for a legitimate evaluator, the price stays out of the props: a
 * technical evaluator must not see pricing at all, and nothing on this screen
 * needs it.
 */
test('pricing stays hidden even from a legitimate evaluator', function () {
    $tender = Tender::factory()->create();
    sealedBidForScoring($tender);

    $response = $this->actingAs(evaluatorOnCommittee($tender))
        ->get(route('evaluations.score.index', $tender));

    expect($response->getContent())
        ->not->toContain('1234.56')
        ->not->toContain('750000');
});

/**
 * {tender} and {bid} bind independently, so nothing stopped a member of one
 * tender's committee from passing another tender's bid.
 */
test('a bid from another tender cannot be scored through this one', function () {
    $mine = Tender::factory()->create();
    $theirs = Tender::factory()->create();
    $foreignBid = sealedBidForScoring($theirs);

    $this->actingAs(evaluatorOnCommittee($mine))
        ->get(route('evaluations.score.bid', ['tender' => $mine, 'bid' => $foreignBid]))
        ->assertNotFound();
});

/**
 * The opening screen showed every sealed bid's total to anyone who could view
 * the tender, and ordered the rows by amount — so even without the column the
 * cheapest bid was identifiable. Prices appear only once a bid is opened.
 */
test('the opening screen hides prices until the bids are actually opened', function () {
    $tender = Tender::factory()->create();
    $bid = sealedBidForScoring($tender);

    // TenderPolicy::view() gates on project assignment, so this is the actor the
    // screen is actually built for — not someone incidentally locked out.
    $viewer = User::factory()->create();
    $viewer->projects()->attach($tender->project_id, [
        'id' => (string) Str::uuid(),
        'project_role' => 'viewer',
        'assigned_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('tenders.bid-summary', $tender))
        ->assertOk();

    expect($this->get(route('tenders.bid-summary', $tender))->getContent())
        ->not->toContain('750000');

    // Once opened under dual authorisation, the same screen must show it —
    // otherwise this is a broken feature rather than a closed hole.
    $bid->update(['is_sealed' => false, 'status' => BidStatus::Opened, 'opened_at' => now()]);

    expect($this->get(route('tenders.bid-summary', $tender))->getContent())
        ->toContain('750000');
});
