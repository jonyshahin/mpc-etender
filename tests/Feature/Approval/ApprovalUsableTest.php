<?php

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Award;
use App\Models\Bid;
use App\Models\EvaluationReport;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function usableApprover(Tender $tender, string ...$slugs): User
{
    $role = Role::firstOrCreate(['slug' => 'approver'], ['name' => 'Approver', 'is_system' => true]);

    foreach ($slugs as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'module' => explode('.', $slug)[0]],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    $user = User::factory()->create(['role_id' => $role->id]);
    $user->projects()->attach($tender->project_id, [
        'id' => (string) Str::uuid(),
        'project_role' => 'viewer',
        'assigned_at' => now(),
    ]);

    return $user;
}

/** A tender with a scored, recommended bid and a chain waiting on level 1. */
function usableChain(float $amount = 10_000): array
{
    $tender = Tender::factory()->create(['is_two_envelope' => false, 'currency' => 'USD']);
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'total_amount' => $amount]);
    $report = EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => $bid->id,
    ]);

    $raiser = usableApprover($tender, 'evaluations.finalize_reports');
    $request = app(ApprovalService::class)->requestApproval($tender, $report, $raiser);

    return [$tender, $request];
}

/**
 * Show.tsx calls projectUsers.map() to build the delegate picker. JSX props are
 * evaluated when the element is constructed, so a missing prop threw during
 * render whether or not the dialog was open — and this screen is the only place
 * Approve, Reject and Delegate live.
 */
test('the decision screen is given the prop it renders with', function () {
    [$tender, $request] = usableChain();

    $this->actingAs(usableApprover($tender, 'approvals.level1'))
        ->get(route('approvals.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('projectUsers'));
});

test('the decision screen does not ship other staff contact details', function () {
    [$tender, $request] = usableChain();
    $viewer = usableApprover($tender, 'approvals.level1');

    // A colleague on the project, so they appear in the delegate picker.
    // Their name belongs there; their email and phone do not.
    $colleague = usableApprover($tender, 'approvals.level1');
    $colleague->update(['phone' => '+9647700000001']);

    $response = $this->actingAs($viewer)->get(route('approvals.show', $request));

    expect($response->getContent())
        ->toContain($colleague->name)
        ->and($response->getContent())->not->toContain($colleague->email)
        ->and($response->getContent())->not->toContain('+9647700000001');
});

// ── Delegation ─────────────────────────────────────────────────────────────

/**
 * delegate() used to write a history row and nothing else: it changed neither
 * the request nor who could sign it, then reported success.
 */
test('delegating hands the request to someone who could not otherwise sign', function () {
    [$tender, $request] = usableChain();
    $approver = usableApprover($tender, 'approvals.level1');

    // On the project, but holding no approval level of their own.
    $colleague = User::factory()->create();
    $colleague->projects()->attach($tender->project_id, [
        'id' => (string) Str::uuid(),
        'project_role' => 'viewer',
        'assigned_at' => now(),
    ]);

    expect($colleague->can('approve', $request))->toBeFalse();

    $this->actingAs($approver)->post(route('approvals.delegate', $request), [
        'delegatee_id' => $colleague->id,
        'comments' => 'Away this week.',
    ]);

    $fresh = $request->fresh();

    expect($fresh->delegated_to)->toBe($colleague->id)
        ->and($fresh->status)->toBe(ApprovalStatus::Pending)
        ->and($colleague->fresh()->can('approve', $fresh))->toBeTrue();
});

test('delegating to yourself is refused', function () {
    [$tender, $request] = usableChain();
    $approver = usableApprover($tender, 'approvals.level1');

    $this->actingAs($approver)->post(route('approvals.delegate', $request), [
        'delegatee_id' => $approver->id,
        'comments' => 'Mine.',
    ]);

    expect($request->fresh()->delegated_to)->toBeNull();
});

test('an approval cannot be delegated outside its project', function () {
    [$tender, $request] = usableChain();
    $approver = usableApprover($tender, 'approvals.level1');
    $outsider = User::factory()->create();

    $this->actingAs($approver)->post(route('approvals.delegate', $request), [
        'delegatee_id' => $outsider->id,
        'comments' => 'Over to you.',
    ]);

    expect($request->fresh()->delegated_to)->toBeNull();
});

// ── A report that recommends nothing ───────────────────────────────────────

/**
 * recommended_bid_id is nullable and generateReport() leaves it null when the
 * ranking is empty. awardValue() tolerates that, so a chain can be raised and
 * run all the way to createAward(), which dereferenced the bid unconditionally.
 */
test('approving a report that recommends no bid fails safely, not with a 500', function () {
    // Under the level-1 threshold, so the chain is one signature long and
    // the approval reaches createAward() rather than escalating. With no
    // recommended bid, awardValue() falls back to this figure.
    $tender = Tender::factory()->create([
        'is_two_envelope' => false,
        'currency' => 'USD',
        'estimated_value' => 10_000,
    ]);
    $report = EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => null,
    ]);

    $raiser = usableApprover($tender, 'evaluations.finalize_reports');
    $request = app(ApprovalService::class)->requestApproval($tender, $report, $raiser);

    $this->actingAs(usableApprover($tender, 'approvals.level1'))
        ->from(route('approvals.index'))
        ->post(route('approvals.approve', $request), ['comments' => 'Looks fine.'])
        ->assertRedirect(route('approvals.index'));

    // Nothing half-written: no award, and the chain is still signable once the
    // report is regenerated.
    expect(Award::count())->toBe(0)
        ->and($request->fresh()->status)->toBe(ApprovalStatus::Pending)
        ->and($tender->fresh()->status->value)->not->toBe('awarded');
});

// ── Who may start a chain ──────────────────────────────────────────────────

/**
 * ApprovalRequestPolicy::create requires evaluations.finalize_reports and was
 * registered but never called. Authorizing on view() alone let any project
 * member start an award chain.
 */
test('starting an approval chain needs the finalise permission, not just project access', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => false, 'currency' => 'USD']);
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'total_amount' => 5_000]);
    EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => $bid->id,
    ]);

    $this->actingAs(usableApprover($tender))
        ->post(route('tenders.request-approval', $tender))
        ->assertForbidden();

    expect(ApprovalRequest::count())->toBe(0);

    $this->actingAs(usableApprover($tender, 'evaluations.finalize_reports'))
        ->post(route('tenders.request-approval', $tender));

    expect(ApprovalRequest::count())->toBe(1);
});
