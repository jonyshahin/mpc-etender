<?php

use App\Enums\ApprovalStatus;
use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\Award;
use App\Models\Bid;
use App\Models\EvaluationReport;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * An approver who may sign at $levels and is assigned to $project.
 *
 * Helper names in Pest files are global, so this carries its own suffix rather
 * than reusing the queue test's.
 */
function approverForDecision(array $levels, ?Project $project = null): User
{
    $slug = 'decider_'.implode('_', $levels);
    $role = Role::firstOrCreate(['slug' => $slug], ['name' => 'Decider', 'is_system' => false]);

    foreach ($levels as $level) {
        $permission = Permission::firstOrCreate(
            ['slug' => "approvals.level{$level}"],
            ['name' => "Approve Level {$level}", 'module' => 'approvals'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    $user = User::factory()->create(['role_id' => $role->id]);

    if ($project) {
        $user->projects()->attach($project->id, [
            'project_role' => 'member',
            'assigned_at' => now(),
            'assigned_by' => $user->id,
        ]);
    }

    return $user;
}

/**
 * A pending approval on $project, with a recommended bid so the final-level
 * branch can reach createAward().
 */
function decidableApproval(Project $project, array $attributes = []): ApprovalRequest
{
    $tender = Tender::factory()->create(['project_id' => $project->id]);
    $bid = Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => Vendor::factory()->create()->id,
    ]);

    return ApprovalRequest::factory()->create([
        'tender_id' => $tender->id,
        'report_id' => EvaluationReport::factory()->create([
            'tender_id' => $tender->id,
            'recommended_bid_id' => $bid->id,
        ])->id,
        'approval_level' => 1,
        'required_level' => 1,
        'status' => ApprovalStatus::Pending,
        ...$attributes,
    ]);
}

/**
 * The bug this change exists for.
 *
 * ApprovalDecisionRequest asked `can('approvals.level1')`. A bare permission
 * slug is not an ability Gate knows — no registered gate, no policy method — so
 * it denied for every user and approve, reject and delegate were 403 for
 * everyone. The entire decision flow was unreachable, which is why no test
 * before this one exercised these endpoints over HTTP.
 */
test('an approver at the requests level can approve it', function () {
    $project = Project::factory()->create();
    $approval = decidableApproval($project, ['required_level' => 2]);
    $approver = approverForDecision([1], $project);

    $this->actingAs($approver)
        ->from(route('approvals.show', $approval))
        ->post(route('approvals.approve', $approval), ['comments' => 'Within budget.'])
        ->assertRedirect(route('approvals.show', $approval));

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and(ApprovalDecision::where('request_id', $approval->id)->count())->toBe(1)
        // required_level 2, so approving level 1 escalates rather than awards.
        ->and(ApprovalRequest::where('tender_id', $approval->tender_id)
            ->where('approval_level', 2)->exists())->toBeTrue();
});

test('an approver at the requests level can reject it', function () {
    $project = Project::factory()->create();
    $approval = decidableApproval($project);
    $approver = approverForDecision([1], $project);

    $this->actingAs($approver)
        ->post(route('approvals.reject', $approval), ['comments' => 'Over budget.'])
        ->assertRedirect();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Rejected);
});

test('an approver can delegate a request they could decide', function () {
    $project = Project::factory()->create();
    $approval = decidableApproval($project);
    $approver = approverForDecision([1], $project);
    $colleague = approverForDecision([1], $project);

    $this->actingAs($approver)
        ->post(route('approvals.delegate', $approval), ['delegatee_id' => $colleague->id])
        ->assertRedirect();

    expect(ApprovalDecision::where('request_id', $approval->id)
        ->where('decision', 'delegated')->count())->toBe(1);
});

/**
 * The escalation. The old check asked only whether the caller held *some*
 * level, never whether they held *this request's* level — and a chain climbs
 * 1 → 2 → 3 by award value, so the lowest signature could have cleared the
 * largest award in the system.
 */
test('a lower-level approver cannot decide a higher-level request', function (string $action) {
    $project = Project::factory()->create();
    $approval = decidableApproval($project, ['approval_level' => 3, 'required_level' => 3]);
    $levelOne = approverForDecision([1], $project);

    $this->actingAs($levelOne)
        ->post(route("approvals.{$action}", $approval), ['comments' => 'Signing anyway.'])
        ->assertForbidden();

    expect(ApprovalDecision::where('request_id', $approval->id)->count())->toBe(0)
        ->and($approval->fresh()->status)->toBe(ApprovalStatus::Pending);
})->with(['approve', 'reject']);

test('a level-3 approver can decide a level-3 request', function () {
    $project = Project::factory()->create();
    $approval = decidableApproval($project, ['approval_level' => 3, 'required_level' => 3]);
    $levelThree = approverForDecision([3], $project);

    $this->actingAs($levelThree)
        ->post(route('approvals.approve', $approval), ['comments' => 'Approved at level three.'])
        ->assertRedirect();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved);
});

/**
 * The queue no longer lists approvals outside the reader's projects, so the
 * endpoints must not accept them either — otherwise the scope is only as good
 * as the caller's inability to guess a UUID.
 */
test('holding the level is not enough without the project', function (string $action) {
    $theirs = Project::factory()->create();
    $approval = decidableApproval($theirs);
    $outsider = approverForDecision([1], Project::factory()->create());

    $this->actingAs($outsider)
        ->post(route("approvals.{$action}", $approval), ['comments' => 'Not my project.'])
        ->assertForbidden();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Pending);
})->with(['approve', 'reject']);

test('delegation is refused to someone who could not decide', function () {
    $project = Project::factory()->create();
    $approval = decidableApproval($project, ['approval_level' => 2, 'required_level' => 2]);
    $levelOne = approverForDecision([1], $project);

    $this->actingAs($levelOne)
        ->post(route('approvals.delegate', $approval), ['delegatee_id' => $levelOne->id])
        ->assertForbidden();

    expect(ApprovalDecision::where('request_id', $approval->id)->count())->toBe(0);
});

/**
 * approve() wrote a decision, moved the status and — at the final level —
 * created the Award, none of it guarded by state. A second call wrote a second
 * decision and a second Award for the same tender. Nothing could reach that
 * while the endpoints were 403 for everyone; opening them is what makes the
 * guard matter.
 */
test('an approved request cannot be approved a second time', function () {
    $project = Project::factory()->create();
    $approval = decidableApproval($project);
    $approver = approverForDecision([1], $project);

    $this->actingAs($approver)
        ->post(route('approvals.approve', $approval), ['comments' => 'First signature.'])
        ->assertRedirect();

    expect(Award::where('tender_id', $approval->tender_id)->count())->toBe(1);

    $this->actingAs($approver)
        ->post(route('approvals.approve', $approval), ['comments' => 'Second signature.'])
        ->assertRedirect();

    expect(ApprovalDecision::where('request_id', $approval->id)->count())->toBe(1)
        ->and(Award::where('tender_id', $approval->tender_id)->count())->toBe(1);
});

test('a rejected request cannot be flipped to approved', function () {
    $project = Project::factory()->create();
    $approval = decidableApproval($project, ['status' => ApprovalStatus::Rejected]);
    $approver = approverForDecision([1], $project);

    $this->actingAs($approver)
        ->post(route('approvals.approve', $approval), ['comments' => 'Reconsidered.'])
        ->assertRedirect();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and(ApprovalDecision::where('request_id', $approval->id)->count())->toBe(0)
        ->and(Award::where('tender_id', $approval->tender_id)->count())->toBe(0);
});

test('a decided request cannot be delegated either', function () {
    $project = Project::factory()->create();
    $approval = decidableApproval($project, ['status' => ApprovalStatus::Approved]);
    $approver = approverForDecision([1], $project);

    $this->actingAs($approver)
        ->post(route('approvals.delegate', $approval), ['delegatee_id' => $approver->id])
        ->assertRedirect();

    expect(ApprovalDecision::where('request_id', $approval->id)->count())->toBe(0);
});

test('a decision still requires comments', function () {
    $project = Project::factory()->create();
    $approval = decidableApproval($project);
    $approver = approverForDecision([1], $project);

    $this->actingAs($approver)
        ->post(route('approvals.approve', $approval), ['comments' => ''])
        ->assertSessionHasErrors('comments');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Pending);
});

test('a signed-out visitor cannot decide anything', function () {
    $approval = decidableApproval(Project::factory()->create());

    $this->post(route('approvals.approve', $approval), ['comments' => 'Hello.'])
        ->assertRedirect('/login');
});
