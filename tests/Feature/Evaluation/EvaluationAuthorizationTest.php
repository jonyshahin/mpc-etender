<?php

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
 * A user holding permissions globally but assigned to no project.
 *
 * This is the actor every gap in this file admitted: the permissions are all
 * global booleans, so without a project check they authorise work on every
 * tender in the system. Helper names in Pest files are global — hence the
 * suffix.
 */
function globallyPermittedUser(string ...$slugs): User
{
    $role = Role::firstOrCreate(['slug' => 'evaluator'], ['name' => 'Evaluator', 'is_system' => true]);

    foreach ($slugs as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'module' => explode('.', $slug)[0]],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

/** The same, but actually on the tender's project. */
function projectMemberFor(Tender $tender, string ...$slugs): User
{
    $user = globallyPermittedUser(...$slugs);
    $user->projects()->attach($tender->project_id, [
        'id' => (string) Str::uuid(),
        'project_role' => 'viewer',
        'assigned_at' => now(),
    ]);

    return $user;
}

/**
 * show() was the only read action in TenderController with no authorize() call.
 * estimated_value is MPC's own pre-tender cost estimate — the number a bidder
 * would most like to have — and notes_internal is internal commentary.
 */
test('a tender cannot be read by someone outside its project', function () {
    $tender = Tender::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('tenders.show', $tender))
        ->assertForbidden();
});

test('but a project member can still read it', function () {
    $tender = Tender::factory()->create();

    $this->actingAs(projectMemberFor($tender))
        ->get(route('tenders.show', $tender))
        ->assertOk();
});

/**
 * update() and removeMember() took plain parameters and called no authorize()
 * at all — any verified account could rewrite or dismantle any committee.
 */
test('committee mutations are refused outside the project', function (string $call) {
    $tender = Tender::factory()->create();
    $committee = EvaluationCommittee::factory()->create(['tender_id' => $tender->id]);
    $member = CommitteeMember::factory()->create([
        'committee_id' => $committee->id,
        'user_id' => User::factory()->create()->id,
    ]);

    // Holds the permission globally, but belongs to no project.
    $outsider = globallyPermittedUser('evaluations.manage_committees');

    $response = match ($call) {
        'update' => $this->actingAs($outsider)->put(
            route('tenders.committees.update', [$tender, $committee]),
            ['name' => 'Renamed', 'status' => 'completed'],
        ),
        'addMember' => $this->actingAs($outsider)->post(
            route('tenders.committees.members.store', [$tender, $committee]),
            ['user_id' => User::factory()->create()->id, 'role' => 'member'],
        ),
        'removeMember' => $this->actingAs($outsider)->delete(
            route('tenders.committees.members.destroy', [$tender, $committee, $member]),
        ),
    };

    $response->assertForbidden();
})->with(['update', 'addMember', 'removeMember']);

test('a committee member survives an unauthorised delete attempt', function () {
    $tender = Tender::factory()->create();
    $committee = EvaluationCommittee::factory()->create(['tender_id' => $tender->id]);
    $member = CommitteeMember::factory()->create([
        'committee_id' => $committee->id,
        'user_id' => User::factory()->create()->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->delete(route('tenders.committees.members.destroy', [$tender, $committee, $member]));

    expect(CommitteeMember::find($member->id))->not->toBeNull();
});

/**
 * {tender} and {committee} bind independently, so a committee from tender B
 * could be mutated while authorising against tender A.
 */
test('a committee belonging to another tender is not reachable', function () {
    $mine = Tender::factory()->create();
    $theirs = Tender::factory()->create();
    $foreign = EvaluationCommittee::factory()->create(['tender_id' => $theirs->id]);

    $this->actingAs(projectMemberFor($mine, 'evaluations.manage_committees'))
        ->put(route('tenders.committees.update', [$mine, $foreign]), [
            'name' => 'Renamed', 'status' => 'completed',
        ])
        ->assertNotFound();
});

/**
 * The evaluation report carries every bidder's technical AND financial score.
 * All three actions gated on 'view' — bare project membership — while
 * EvaluationReportPolicy, which demands evaluations.view, had zero call sites.
 */
test('the evaluation report needs the evaluations.view permission, not just project membership', function () {
    $tender = Tender::factory()->create();

    // On the project, but with no evaluation permission at all.
    $this->actingAs(projectMemberFor($tender))
        ->get(route('tenders.report.show', $tender))
        ->assertForbidden();

    $this->actingAs(projectMemberFor($tender, 'evaluations.view'))
        ->get(route('tenders.report.show', $tender))
        ->assertOk();
});

test('generating a report needs the generate permission', function () {
    $tender = Tender::factory()->create();

    $this->actingAs(projectMemberFor($tender, 'evaluations.view'))
        ->post(route('tenders.report.generate', $tender))
        ->assertForbidden();
});

/**
 * summary() computed canOpen as "date passed AND submissions closed", but the
 * POST enforced only the date. The status half was advisory UI, so bids could
 * be unsealed while the submission window was still open.
 */
test('bids cannot be opened while submissions are still open', function () {
    $tender = Tender::factory()->create([
        'status' => 'published',
        'opening_date' => now()->subDay(),
    ]);
    $opener = projectMemberFor($tender, 'bids.open');
    $authorizer = projectMemberFor($tender, 'bids.open');

    $this->actingAs($opener)
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id]);

    expect($tender->fresh()->status->value)->toBe('published');
});

/**
 * The opener was never checked against the project — only the authorizer was.
 */
test('someone outside the project cannot open its bids', function () {
    $tender = Tender::factory()->create([
        'status' => 'submission_closed',
        'opening_date' => now()->subDay(),
    ]);
    $authorizer = projectMemberFor($tender, 'bids.open');

    $this->actingAs(globallyPermittedUser('bids.open'))
        ->post(route('tenders.open-bids', $tender), ['authorizer_id' => $authorizer->id])
        ->assertForbidden();

    expect($tender->fresh()->status->value)->toBe('submission_closed');
});
