<?php

use App\Enums\BidOpeningRequestStatus;
use App\Enums\BidStatus;
use App\Enums\TenderStatus;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BidOpeningRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** A tender whose submissions have closed and whose opening date has passed. */
function openableTender(): Tender
{
    $tender = Tender::factory()->create([
        'status' => TenderStatus::SubmissionClosed,
        'opening_date' => now()->subHour(),
    ]);

    Bid::factory()->create([
        'tender_id' => $tender->id,
        'status' => BidStatus::Submitted,
        'is_sealed' => true,
        'total_amount' => 500_000,
    ]);

    return $tender;
}

/** Someone entitled to take part in an opening on this tender. */
function bidOpener(Tender $tender): User
{
    $role = Role::firstOrCreate(['slug' => 'opener'], ['name' => 'Opener', 'is_system' => true]);
    $permission = Permission::firstOrCreate(
        ['slug' => 'bids.open'],
        ['name' => 'Open Bids', 'module' => 'bids'],
    );
    $role->permissions()->syncWithoutDetaching([$permission->id]);

    $user = User::factory()->create(['role_id' => $role->id]);
    $user->projects()->attach($tender->project_id, [
        'id' => (string) Str::uuid(),
        'project_role' => 'viewer',
        'assigned_at' => now(),
    ]);

    return $user;
}

function sealedCount(Tender $tender): int
{
    return $tender->bids()->where('is_sealed', true)->count();
}

/**
 * The defect this exists to close: one person could produce a complete,
 * audited "dual" authorisation by naming a colleague who never acted.
 */
test('requesting an opening does not open anything on its own', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);

    expect(sealedCount($tender))->toBe(1)
        ->and($tender->fresh()->status)->toBe(TenderStatus::SubmissionClosed)
        ->and(BidOpeningRequest::where('tender_id', $tender->id)->count())->toBe(1);
});

test('the second person confirming is what actually opens the bids', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);

    $request = BidOpeningRequest::where('tender_id', $tender->id)->sole();

    $this->actingAs($authorizer)
        ->post(route('tenders.open-bids.confirm', [$tender, $request]));

    expect(sealedCount($tender))->toBe(0)
        ->and($tender->fresh()->status)->toBe(TenderStatus::UnderEvaluation)
        ->and($request->fresh()->status)->toBe(BidOpeningRequestStatus::Confirmed)
        ->and($request->fresh()->confirmed_by)->toBe($authorizer->id);
});

/** The whole point: the opener cannot be both halves. */
test('the requester cannot confirm their own request', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);
    $request = BidOpeningRequest::where('tender_id', $tender->id)->sole();

    $this->actingAs($opener)
        ->post(route('tenders.open-bids.confirm', [$tender, $request]));

    expect(sealedCount($tender))->toBe(1)
        ->and($request->fresh()->status)->toBe(BidOpeningRequestStatus::Pending);
});

test('nobody but the nominated authorizer can confirm', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);
    $bystander = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);
    $request = BidOpeningRequest::where('tender_id', $tender->id)->sole();

    $this->actingAs($bystander)
        ->post(route('tenders.open-bids.confirm', [$tender, $request]));

    expect(sealedCount($tender))->toBe(1);
});

test('nominating yourself is refused', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $opener->id]);

    expect(BidOpeningRequest::count())->toBe(0);
});

test('an expired request can no longer be confirmed', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $request = BidOpeningRequest::factory()->expired()->create([
        'tender_id' => $tender->id,
        'requested_by' => $opener->id,
        'authorizer_id' => $authorizer->id,
    ]);

    $this->actingAs($authorizer)
        ->post(route('tenders.open-bids.confirm', [$tender, $request]));

    expect(sealedCount($tender))->toBe(1)
        ->and($request->fresh()->status)->toBe(BidOpeningRequestStatus::Pending);
});

test('a confirmed request cannot be replayed to open again', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);
    $request = BidOpeningRequest::where('tender_id', $tender->id)->sole();

    $this->actingAs($authorizer)->post(route('tenders.open-bids.confirm', [$tender, $request]));
    $confirmedAt = $request->fresh()->confirmed_at;

    $this->actingAs($authorizer)->post(route('tenders.open-bids.confirm', [$tender, $request]));

    expect($request->fresh()->confirmed_at->eq($confirmedAt))->toBeTrue();
});

/**
 * Entitlement is re-checked at confirmation, not trusted from request time —
 * either party may have been taken off the project in between.
 */
test('an authorizer removed from the project in the meantime cannot confirm', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);
    $request = BidOpeningRequest::where('tender_id', $tender->id)->sole();

    $authorizer->projects()->detach($tender->project_id);

    $this->actingAs($authorizer)
        ->post(route('tenders.open-bids.confirm', [$tender, $request]));

    expect(sealedCount($tender))->toBe(1);
});

test('an outsider cannot be nominated as the authorizer', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);

    // Holds bids.open globally but is on no project.
    $outsider = User::factory()->create(['role_id' => Role::where('slug', 'opener')->value('id')]);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $outsider->id]);

    expect(BidOpeningRequest::count())->toBe(0);
});

test('only one opening request can be awaiting confirmation at a time', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    foreach ([1, 2] as $_) {
        $this->actingAs($opener)
            ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);
    }

    expect(BidOpeningRequest::where('tender_id', $tender->id)->count())->toBe(1);
});

test('either party can cancel, and a cancelled request cannot be confirmed', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);
    $request = BidOpeningRequest::where('tender_id', $tender->id)->sole();

    $this->actingAs($opener)->delete(route('tenders.open-bids.cancel', [$tender, $request]));
    expect($request->fresh()->status)->toBe(BidOpeningRequestStatus::Cancelled);

    $this->actingAs($authorizer)->post(route('tenders.open-bids.confirm', [$tender, $request]));
    expect(sealedCount($tender))->toBe(1);
});

/**
 * The status half of canOpen was advisory UI only — the POST enforced just the
 * date, so bids could be unsealed while the submission window was still open.
 */
test('an opening cannot be requested while submissions are still open', function () {
    $tender = openableTender();
    $tender->update(['status' => TenderStatus::Published]);

    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);

    expect(BidOpeningRequest::count())->toBe(0);
});

test('an opening cannot be requested before the opening date', function () {
    $tender = openableTender();
    $tender->update(['opening_date' => now()->addDay()]);

    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);

    expect(BidOpeningRequest::count())->toBe(0);
});

/**
 * The audit row is append-only and is what a dispute would be settled on, so
 * it must name the person who actually acted.
 */
test('the audit row names the confirmer, who really did act', function () {
    $tender = openableTender();
    $opener = bidOpener($tender);
    $authorizer = bidOpener($tender);

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);
    $request = BidOpeningRequest::where('tender_id', $tender->id)->sole();

    $this->actingAs($authorizer)->post(route('tenders.open-bids.confirm', [$tender, $request]));

    $audit = AuditLog::where('action', 'opened')->sole();

    expect($audit->new_values['opened_by'])->toBe($opener->id)
        ->and($audit->new_values['authorized_by'])->toBe($authorizer->id);
});

test('a request from another tender is not reachable through this one', function () {
    $mine = openableTender();
    $theirs = openableTender();
    $opener = bidOpener($theirs);
    $authorizer = bidOpener($theirs);

    $foreign = BidOpeningRequest::factory()->create([
        'tender_id' => $theirs->id,
        'requested_by' => $opener->id,
        'authorizer_id' => $authorizer->id,
    ]);

    $this->actingAs(bidOpener($mine))
        ->post(route('tenders.open-bids.confirm', [$mine, $foreign]))
        ->assertNotFound();
});
