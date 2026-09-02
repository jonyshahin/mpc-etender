<?php

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

/**
 * What the decision history says a delegation was.
 *
 * The trail rendered `decision.delegated_from` straight into the line. That key
 * is both a column on approval_decisions — a UUID — and, once the controller
 * eager-loads `decisions.delegatedFrom:id,name`, the serialised relation under
 * the same snake-cased name. Whichever wins, neither is a person's name: one
 * prints a raw UUID at the reader, the other is an object React refuses to
 * render.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function trailApprover(Tender $tender, string ...$slugs): User
{
    $role = Role::firstOrCreate(['slug' => 'trail-approver'], ['name' => 'Approver', 'is_system' => true]);

    foreach ($slugs as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'module' => explode('.', $slug)[0]],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    $user->projects()->attach($tender->project_id, [
        'id' => (string) Str::uuid(),
        'project_role' => 'viewer',
        'assigned_at' => now(),
    ]);

    return $user;
}

/** A chain with one delegation already recorded against it. */
function delegatedChain(): array
{
    $tender = Tender::factory()->create(['is_two_envelope' => false, 'currency' => 'USD']);
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'total_amount' => 10_000]);
    $report = EvaluationReport::factory()->create([
        'tender_id' => $tender->id,
        'recommended_bid_id' => $bid->id,
    ]);

    $raiser = trailApprover($tender, 'evaluations.finalize_reports');
    $request = app(ApprovalService::class)->requestApproval($tender, $report, $raiser);

    $delegator = trailApprover($tender, 'approvals.level1');
    $delegatee = trailApprover($tender, 'approvals.level1');

    app(ApprovalService::class)->delegate($request, $delegator, $delegatee, 'Away this week.');

    return [$request, $delegator, $delegatee];
}

test('the decision trail names who delegated, not their id', function () {
    [$request, $delegator, $delegatee] = delegatedChain();

    $this->actingAs($delegatee)
        ->get(route('approvals.show', $request))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($delegator) {
            $decision = collect($page->toArray()['props']['approval']['decisions'])
                ->firstWhere('decision', 'delegated');

            expect($decision)->not->toBeNull()
                ->and($decision['delegated_from'])->toBeArray()
                ->and($decision['delegated_from']['name'])->toBe($delegator->name);
        });
});

/**
 * The id is what made this look fine in review: it is present, it is truthy,
 * and printing it produces something. It just is not information.
 */
test('the rendered page does not show a raw uuid where a name belongs', function () {
    [$request, $delegator, $delegatee] = delegatedChain();

    $response = $this->actingAs($delegatee)->get(route('approvals.show', $request));

    expect($response->getContent())->toContain($delegator->name);
});
