<?php

use App\Enums\CommitteeStatus;
use App\Models\Bid;
use App\Models\CommitteeMember;
use App\Models\EvaluationCommittee;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Services\EvaluationService;
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

/**
 * AddMemberRequest validates only that the user id exists somewhere in the
 * users table. Committee membership is what EvaluationScorePolicy::score gates
 * on, so adding an outsider hands them the evaluation data for a project they
 * have nothing to do with.
 */
test('someone outside the project cannot be added to a committee', function () {
    $tender = Tender::factory()->create();
    $manager = interactionEvaluator($tender);
    $committee = EvaluationCommittee::factory()->create(['tender_id' => $tender->id]);

    $this->actingAs($manager)->post(
        route('tenders.committees.members.store', [$tender, $committee]),
        ['user_id' => User::factory()->create()->id, 'role' => 'member'],
    );

    expect(CommitteeMember::where('committee_id', $committee->id)->count())->toBe(0);
});

/**
 * Sitting on both the technical and the financial committee of one tender is
 * not a richer role — it is an ambiguity the scoring screen has to guess its
 * way out of.
 */
test('the same person cannot sit on two committees of one tender', function () {
    $tender = Tender::factory()->create();
    $manager = interactionEvaluator($tender);

    $technical = EvaluationCommittee::factory()->create([
        'tender_id' => $tender->id, 'committee_type' => 'technical',
    ]);
    $financial = EvaluationCommittee::factory()->create([
        'tender_id' => $tender->id, 'committee_type' => 'financial',
    ]);

    $candidate = User::factory()->create();
    $candidate->projects()->attach($tender->project_id, [
        'id' => (string) Str::uuid(), 'project_role' => 'viewer', 'assigned_at' => now(),
    ]);

    foreach ([$technical, $financial] as $committee) {
        $this->actingAs($manager)->post(
            route('tenders.committees.members.store', [$tender, $committee]),
            ['user_id' => $candidate->id, 'role' => 'member'],
        );
    }

    expect(CommitteeMember::where('user_id', $candidate->id)->count())->toBe(1);
});

/**
 * A report is the evidence of how an award was reached, so regenerating must
 * not silently replace the file an earlier run produced.
 */
test('regenerating the report does not overwrite the previous file', function () {
    Storage::fake('s3');

    $tender = Tender::factory()->create(['is_two_envelope' => false]);
    $generator = interactionEvaluator($tender);
    Bid::factory()->create(['tender_id' => $tender->id]);
    EvaluationCriterion::factory()->create(['tender_id' => $tender->id, 'envelope' => 'technical']);

    $service = app(EvaluationService::class);
    $first = $service->generateReport($tender, $generator);
    $second = $service->generateReport($tender->fresh(), $generator);

    expect($second->file_path)->not->toBe($first->file_path);
    Storage::disk('s3')->assertExists($first->file_path);
    Storage::disk('s3')->assertExists($second->file_path);
});

/**
 * tenders.committees.update existed but nothing in the app called it, so a
 * committee could never be renamed or marked completed — and completion is the
 * flag the evaluation workflow reads.
 */
test('a committee can be renamed and marked completed', function () {
    $tender = Tender::factory()->create();
    $manager = interactionEvaluator($tender);
    $committee = EvaluationCommittee::factory()->create([
        'tender_id' => $tender->id,
        'name' => 'Technical Panel',
        'status' => CommitteeStatus::Active,
    ]);

    $this->actingAs($manager)->put(
        route('tenders.committees.update', [$tender, $committee]),
        ['name' => 'Technical Panel A', 'status' => 'completed'],
    );

    $fresh = $committee->fresh();

    expect($fresh->name)->toBe('Technical Panel A')
        ->and($fresh->status)->toBe(CommitteeStatus::Completed);
});

/**
 * Three sources disagreed on this column: the migration defaulted it to
 * 'pending', store() wrote 'active', update() accepted only active|completed.
 */
test('a committee status outside the enum is refused', function () {
    $tender = Tender::factory()->create();
    $manager = interactionEvaluator($tender);
    $committee = EvaluationCommittee::factory()->create([
        'tender_id' => $tender->id,
        'status' => CommitteeStatus::Active,
    ]);

    $this->actingAs($manager)->put(
        route('tenders.committees.update', [$tender, $committee]),
        ['name' => 'Renamed', 'status' => 'pending'],
    );

    expect($committee->fresh()->status)->toBe(CommitteeStatus::Active);
});
