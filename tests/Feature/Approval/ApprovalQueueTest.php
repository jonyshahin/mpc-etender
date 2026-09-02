<?php

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Bid;
use App\Models\EvaluationReport;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * An approver who may sign at $levels and is assigned to $project.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function approverForQueue(array $levels = [1], ?Project $project = null): User
{
    // The slug carries the levels so two approvers in one test do not share a
    // role and quietly inherit each other's permissions.
    $slug = 'approver_'.implode('_', $levels);
    $role = Role::firstOrCreate(['slug' => $slug], ['name' => 'Approver', 'is_system' => false]);

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

/** An approval request against a tender on $project. */
function approvalOn(Project $project, array $attributes = []): ApprovalRequest
{
    $tender = Tender::factory()->create(['project_id' => $project->id]);

    return ApprovalRequest::factory()->create([
        'tender_id' => $tender->id,
        'report_id' => EvaluationReport::factory()->create(['tender_id' => $tender->id])->id,
        ...$attributes,
    ]);
}

/** @return array<string, mixed> */
function queueProps(User $user, array $query = []): array
{
    $props = [];

    test()->actingAs($user)
        ->get(route('approvals.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

    return $props;
}

/**
 * The bug this queue was rebuilt around, and the reason nobody hit the other one.
 *
 * The level filter used `$user->can("approvals.level1")`. That names neither a
 * registered gate nor a policy ability, so Gate fell through to its default
 * deny for every user and the list resolved to
 * `whereIn('approval_level', [])` — no row, ever.
 *
 * That masked a second fault sitting right behind it: the query eager-loaded
 * `requestedByUser`, which ApprovalRequest does not declare. Eloquent skips
 * eager loading entirely when the base query returns no models, so the
 * BadMethodCallException only fires once there is a row to load against —
 * which the first bug guaranteed there never was. Fixing either one alone
 * would have turned an empty page into a 500.
 */
test('an approver sees the approvals waiting at their level', function () {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    approvalOn($project, ['approval_level' => 1]);
    approvalOn($project, ['approval_level' => 1]);

    $props = queueProps($approver);

    expect($props['approvals']['data'])->toHaveCount(2);
});

test('only the levels the approver may sign are listed', function () {
    $project = Project::factory()->create();
    $approver = approverForQueue([2], $project);

    approvalOn($project, ['approval_level' => 1]);
    $mine = approvalOn($project, ['approval_level' => 2]);
    approvalOn($project, ['approval_level' => 3]);

    $rows = queueProps($approver)['approvals']['data'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($mine->id);
});

test('a user with no approval permission gets an empty queue rather than an error', function () {
    $project = Project::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'no_approvals'], ['name' => 'None', 'is_system' => false]);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->projects()->attach($project->id, [
        'project_role' => 'member',
        'assigned_at' => now(),
        'assigned_by' => $user->id,
    ]);

    approvalOn($project, ['approval_level' => 1]);

    expect(queueProps($user)['approvals']['data'])->toBe([]);
});

/**
 * CLAUDE.md is explicit that users see only their assigned projects' data, and
 * ApprovalRequestPolicy::view enforces exactly that on the detail screen. The
 * queue had no project scope at all, so it listed every pending approval in
 * the system — reference, title and award value — including rows the reader
 * would then be refused when they clicked through.
 */
test('approvals on projects the reader is not assigned to are not listed', function () {
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();
    $approver = approverForQueue([1], $mine);

    $visible = approvalOn($mine, ['approval_level' => 1]);
    approvalOn($theirs, ['approval_level' => 1]);

    $rows = queueProps($approver)['approvals']['data'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($visible->id);
});

test('the recommended vendor reaches the row', function () {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    $tender = Tender::factory()->create(['project_id' => $project->id]);
    $vendor = Vendor::factory()->create(['company_name' => 'Baghdad Steel Works']);
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'vendor_id' => $vendor->id]);
    $report = EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => $bid->id,
    ]);

    ApprovalRequest::factory()->create([
        'tender_id' => $tender->id,
        'report_id' => $report->id,
        'approval_level' => 1,
    ]);

    // index() eager-loaded `report` but never report.recommendedBid.vendor, so
    // the card's vendor line was always undefined and never rendered.
    expect(queueProps($approver)['approvals']['data'][0]['recommended_vendor'])
        ->toBe('Baghdad Steel Works');
});

test('the queue defaults to pending and can be pointed at any other state', function () {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    approvalOn($project, ['approval_level' => 1, 'status' => ApprovalStatus::Pending]);
    approvalOn($project, ['approval_level' => 1, 'status' => ApprovalStatus::Approved]);
    approvalOn($project, ['approval_level' => 1, 'status' => ApprovalStatus::Escalated]);

    // A work queue opens on the work, not on years of decided history.
    expect(queueProps($approver)['approvals']['data'])->toHaveCount(1)
        ->and(queueProps($approver)['filters']['status'])->toBe('pending');

    expect(queueProps($approver, ['status' => 'escalated'])['approvals']['data'])->toHaveCount(1);
    expect(queueProps($approver, ['status' => 'all'])['approvals']['data'])->toHaveCount(3);
});

test('an unusable status falls back to pending rather than filtering on nothing', function (string $value) {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    approvalOn($project, ['approval_level' => 1, 'status' => ApprovalStatus::Pending]);
    approvalOn($project, ['approval_level' => 1, 'status' => ApprovalStatus::Approved]);

    $props = queueProps($approver, ['status' => $value]);

    expect($props['filters']['status'])->toBe('pending')
        ->and($props['approvals']['data'])->toHaveCount(1);
})->with([
    'unknown state' => ['nonsense'],
    'empty' => [''],
    'injection attempt' => ["pending' or '1'='1"],
]);

/**
 * DataTable merges this set into every sort request, so an incomplete echo
 * sorts with the filters wiped and — comparing against a sort that is always
 * undefined — can never reach descending. This page sent no filters at all.
 */
test('the filters it echoes back are complete enough to sort with', function () {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    $props = queueProps($approver, ['search' => 'PRJ', 'status' => 'approved']);

    expect($props['filters'])->toBe([
        'search' => 'PRJ',
        'status' => 'approved',
        'sort' => 'deadline',
        'direction' => 'asc',
    ]);
});

test('an unusable sort or direction falls back instead of erroring', function (array $query, string $sort, string $direction) {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    $props = queueProps($approver, $query);

    expect($props['filters']['sort'])->toBe($sort)
        ->and($props['filters']['direction'])->toBe($direction);
})->with([
    'unknown column' => [['sort' => 'not_a_column'], 'deadline', 'asc'],
    'injection attempt' => [['sort' => 'id); drop table approval_requests; --'], 'deadline', 'asc'],
    'a column that exists but is not offered' => [['sort' => 'report_id'], 'deadline', 'asc'],
    'bad direction' => [['direction' => 'sideways'], 'deadline', 'asc'],
    'valid pair' => [['sort' => 'value_threshold', 'direction' => 'desc'], 'value_threshold', 'desc'],
]);

test('the status counts cover every state and follow the search', function () {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    $matching = Tender::factory()->create([
        'project_id' => $project->id,
        'reference_number' => 'TND-BASRA-001',
    ]);
    ApprovalRequest::factory()->count(2)->create([
        'tender_id' => $matching->id,
        'report_id' => EvaluationReport::factory()->create(['tender_id' => $matching->id])->id,
        'approval_level' => 1,
        'status' => ApprovalStatus::Pending,
    ]);
    approvalOn($project, ['approval_level' => 1, 'status' => ApprovalStatus::Rejected]);

    $all = queueProps($approver)['statusCounts'];

    expect(array_keys($all))->toBe(array_map(fn ($c) => $c->value, ApprovalStatus::cases()))
        ->and($all['pending'])->toBe(2)
        ->and($all['rejected'])->toBe(1)
        ->and($all['expired'])->toBe(0);

    $searched = queueProps($approver, ['search' => 'BASRA'])['statusCounts'];

    expect($searched['pending'])->toBe(2)
        ->and($searched['rejected'])->toBe(0);
});

test('the status filter narrows the rows but not the counts', function () {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    approvalOn($project, ['approval_level' => 1, 'status' => ApprovalStatus::Pending]);
    approvalOn($project, ['approval_level' => 1, 'status' => ApprovalStatus::Rejected]);

    $props = queueProps($approver, ['status' => 'rejected']);

    expect($props['approvals']['data'])->toHaveCount(1)
        ->and($props['statusCounts']['pending'])->toBe(1);
});

test('the search matches the reference and both titles', function (string $term) {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    $match = Tender::factory()->create([
        'project_id' => $project->id,
        'reference_number' => 'TND-KBL-001',
        'title_en' => 'Karbala Water Treatment Plant',
        'title_ar' => 'محطة معالجة مياه كربلاء',
    ]);
    ApprovalRequest::factory()->create([
        'tender_id' => $match->id,
        'report_id' => EvaluationReport::factory()->create(['tender_id' => $match->id])->id,
        'approval_level' => 1,
    ]);
    approvalOn($project, ['approval_level' => 1]);

    expect(queueProps($approver, ['search' => $term])['approvals']['data'])->toHaveCount(1);
})->with([
    'reference' => ['KBL-001'],
    'english title' => ['Karbala'],
    'arabic title' => ['كربلاء'],
]);

test('the summary separates what is merely pending from what is late', function () {
    $project = Project::factory()->create();
    $approver = approverForQueue([1], $project);

    approvalOn($project, [
        'approval_level' => 1,
        'status' => ApprovalStatus::Pending,
        'deadline' => now()->subDay(),
        'value_threshold' => 100000,
    ]);
    approvalOn($project, [
        'approval_level' => 1,
        'status' => ApprovalStatus::Pending,
        'deadline' => now()->addHours(6),
        'value_threshold' => 50000,
    ]);
    approvalOn($project, [
        'approval_level' => 1,
        'status' => ApprovalStatus::Pending,
        'deadline' => now()->addDays(10),
        'value_threshold' => 25000,
    ]);
    // Decided rows are not pending and must not reach any of these figures.
    approvalOn($project, [
        'approval_level' => 1,
        'status' => ApprovalStatus::Approved,
        'deadline' => now()->subDays(5),
        'value_threshold' => 999999,
    ]);

    $summary = queueProps($approver)['summary'];

    expect($summary['pending'])->toBe(3)
        ->and($summary['overdue'])->toBe(1)
        ->and($summary['due_soon'])->toBe(1)
        ->and((float) $summary['value'])->toBe(175000.0);
});

test('it renders with nothing waiting', function () {
    $project = Project::factory()->create();
    $props = queueProps(approverForQueue([1], $project));

    expect($props['approvals']['data'])->toBe([])
        ->and($props['summary']['pending'])->toBe(0)
        ->and($props['summary']['overdue'])->toBe(0);
});

test('the status options offered are exactly the states an approval can hold', function () {
    $project = Project::factory()->create();
    $values = array_column(queueProps(approverForQueue([1], $project))['statusOptions'], 'value');

    expect($values)->toBe(array_map(fn ($c) => $c->value, ApprovalStatus::cases()));
});

/**
 * show() had no authorization of any kind, so any verified user could open any
 * approval by id — including one on a project they are not assigned to. It also
 * carried the same undefined `requestedByUser` relation, which here is not
 * masked: a single model is always eager-loaded.
 */
test('the detail screen opens for an assigned reader and refuses everyone else', function () {
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();

    $approval = approvalOn($theirs, ['approval_level' => 1]);

    $outsider = approverForQueue([1], $mine);
    $this->actingAs($outsider)->get(route('approvals.show', $approval))->assertForbidden();

    $insider = approverForQueue([2], $theirs);
    $this->actingAs($insider)->get(route('approvals.show', $approval))->assertOk();
});

test('a signed-out visitor is turned away', function () {
    $this->get(route('approvals.index'))->assertRedirect('/login');
});

/**
 * The dashboard tile links straight here. Counted without the project scope it
 * promised rows the queue does not show — the "list of dead ends" that
 * DashboardService::attentionFor exists to avoid.
 */
test('the dashboard count agrees with what the queue lists', function () {
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();
    $approver = approverForQueue([1], $mine);

    approvalOn($mine, ['approval_level' => 1, 'status' => ApprovalStatus::Pending]);
    approvalOn($theirs, ['approval_level' => 1, 'status' => ApprovalStatus::Pending]);

    $queued = count(queueProps($approver)['approvals']['data']);

    $this->actingAs($approver)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($queued) {
            $attention = collect($page->toArray()['props']['dashboard']['attention'] ?? []);
            $approvals = $attention->firstWhere('key', 'approvals');

            expect($queued)->toBe(1)
                ->and($approvals['count'] ?? null)->toBe($queued);
        });
});
