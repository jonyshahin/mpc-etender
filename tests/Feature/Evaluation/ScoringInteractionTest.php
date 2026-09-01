<?php

use App\Models\Bid;
use App\Models\CommitteeMember;
use App\Models\EvaluationCommittee;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function interactionEvaluator(Tender $tender, string $committeeType = 'technical'): User
{
    $role = Role::firstOrCreate(['slug' => 'evaluator'], ['name' => 'Evaluator', 'is_system' => true]);

    foreach (['evaluations.score', 'evaluations.manage_committees'] as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'module' => 'evaluations'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    $user = User::factory()->create(['role_id' => $role->id]);
    $user->projects()->attach($tender->project_id, [
        'id' => (string) Str::uuid(),
        'project_role' => 'viewer',
        'assigned_at' => now(),
    ]);

    $committee = EvaluationCommittee::factory()->create([
        'tender_id' => $tender->id,
        'committee_type' => $committeeType,
    ]);
    CommitteeMember::factory()->create([
        'committee_id' => $committee->id,
        'user_id' => $user->id,
    ]);

    return $user;
}

/**
 * EvaluationCommittee::members() is a belongsToMany over users and never
 * exposed the pivot id, so the screen only ever had a user id to send — while
 * the route bound a CommitteeMember. Removal 404'd every time, and a member
 * added by mistake could never be taken off.
 */
test('a committee member can be removed using their user id', function () {
    $tender = Tender::factory()->create();
    $manager = interactionEvaluator($tender);

    $committee = EvaluationCommittee::factory()->create(['tender_id' => $tender->id]);
    $member = User::factory()->create();
    CommitteeMember::factory()->create([
        'committee_id' => $committee->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($manager)
        ->delete(route('tenders.committees.members.destroy', [$tender, $committee, $member]))
        ->assertRedirect();

    expect(CommitteeMember::where('committee_id', $committee->id)
        ->where('user_id', $member->id)
        ->exists())->toBeFalse();
});

test('removing someone who is not on the committee is a 404, not a silent success', function () {
    $tender = Tender::factory()->create();
    $manager = interactionEvaluator($tender);
    $committee = EvaluationCommittee::factory()->create(['tender_id' => $tender->id]);

    $this->actingAs($manager)
        ->delete(route('tenders.committees.members.destroy', [$tender, $committee, User::factory()->create()]))
        ->assertNotFound();
});

/**
 * The write loop had its max-score guard inside it and returned from the
 * middle, so a rejected submission left some criteria saved and some not.
 */
test('a submission that breaches a maximum saves nothing at all', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => false]);
    $evaluator = interactionEvaluator($tender);
    $bid = Bid::factory()->create(['tender_id' => $tender->id]);

    $good = EvaluationCriterion::factory()->create([
        'tender_id' => $tender->id, 'envelope' => 'technical', 'max_score' => 100, 'sort_order' => 1,
    ]);
    $strict = EvaluationCriterion::factory()->create([
        'tender_id' => $tender->id, 'envelope' => 'technical', 'max_score' => 10, 'sort_order' => 2,
    ]);

    $this->actingAs($evaluator)
        ->post(route('evaluations.score.store', [$tender, $bid]), [
            'scores' => [
                ['criterion_id' => $good->id, 'score' => 80],
                ['criterion_id' => $strict->id, 'score' => 999],
            ],
        ]);

    expect(EvaluationScore::count())->toBe(0, 'the first score must not survive the second being rejected');
});

/**
 * 'complete' arrives from a screen that scores ONE bid, but the handler marked
 * the evaluator done for the whole tender — so finishing the first bid showed
 * them as finished while the rest still read 'Not scored'.
 */
test('completing one bid does not mark the whole tender scored', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => false]);
    $evaluator = interactionEvaluator($tender);

    $first = Bid::factory()->create(['tender_id' => $tender->id]);
    Bid::factory()->create(['tender_id' => $tender->id]);

    $criterion = EvaluationCriterion::factory()->create([
        'tender_id' => $tender->id, 'envelope' => 'technical', 'max_score' => 100,
    ]);

    $this->actingAs($evaluator)
        ->post(route('evaluations.score.store', [$tender, $first]), [
            'scores' => [['criterion_id' => $criterion->id, 'score' => 80]],
            'complete' => true,
        ]);

    expect(CommitteeMember::where('user_id', $evaluator->id)->first()->has_scored)->toBeFalse();
});

test('completing the last outstanding bid does mark the tender scored', function () {
    $tender = Tender::factory()->create(['is_two_envelope' => false]);
    $evaluator = interactionEvaluator($tender);

    $only = Bid::factory()->create(['tender_id' => $tender->id]);
    $criterion = EvaluationCriterion::factory()->create([
        'tender_id' => $tender->id, 'envelope' => 'technical', 'max_score' => 100,
    ]);

    $this->actingAs($evaluator)
        ->post(route('evaluations.score.store', [$tender, $only]), [
            'scores' => [['criterion_id' => $criterion->id, 'score' => 80]],
            'complete' => true,
        ]);

    expect(CommitteeMember::where('user_id', $evaluator->id)->first()->has_scored)->toBeTrue();
});
