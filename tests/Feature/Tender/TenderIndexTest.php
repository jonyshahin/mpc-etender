<?php

use App\Enums\TenderStatus;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** A user assigned to one project, plus that project. Helper names are global. */
function userOnProject(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $user->projects()->attach($project->id, [
        'id' => (string) Str::uuid(),
        'project_role' => 'viewer',
        'assigned_at' => now(),
    ]);

    return [$user, $project];
}

test('it lists only tenders in the projects the user is on', function () {
    [$user, $project] = userOnProject();

    $mine = Tender::factory()->create(['project_id' => $project->id]);
    // Another project the user is not assigned to.
    Tender::factory()->create(['project_id' => Project::factory()->create()->id]);

    $this->actingAs($user)
        ->get(route('tenders.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($mine) {
            $rows = $page->toArray()['props']['tenders']['data'];

            expect($rows)->toHaveCount(1)
                ->and($rows[0]['id'])->toBe($mine->id);
        });
});

test('the status counts cover every status and follow the search', function () {
    [$user, $project] = userOnProject();

    Tender::factory()->count(2)->create([
        'project_id' => $project->id,
        'status' => TenderStatus::Published,
        'title_en' => 'Roadworks package',
    ]);
    Tender::factory()->create([
        'project_id' => $project->id,
        'status' => TenderStatus::Draft,
        'title_en' => 'Something else entirely',
    ]);

    $this->actingAs($user)
        ->get(route('tenders.index'))
        ->assertInertia(function (Assert $page) {
            $counts = $page->toArray()['props']['statusCounts'];

            // Every status present, so a tab never vanishes when it empties.
            expect(array_keys($counts))
                ->toBe(array_map(fn ($c) => $c->value, TenderStatus::cases()))
                ->and($counts['published'])->toBe(2)
                ->and($counts['draft'])->toBe(1)
                ->and($counts['awarded'])->toBe(0);
        });

    // The counts narrow with the search, so each tab says what the current
    // search would find there.
    $this->actingAs($user)
        ->get(route('tenders.index', ['search' => 'Roadworks']))
        ->assertInertia(function (Assert $page) {
            $counts = $page->toArray()['props']['statusCounts'];

            expect($counts['published'])->toBe(2)
                ->and($counts['draft'])->toBe(0);
        });
});

test('the status filter narrows the rows but not the counts', function () {
    [$user, $project] = userOnProject();

    Tender::factory()->count(2)->create(['project_id' => $project->id, 'status' => TenderStatus::Published]);
    Tender::factory()->create(['project_id' => $project->id, 'status' => TenderStatus::Draft]);

    $this->actingAs($user)
        ->get(route('tenders.index', ['status' => 'draft']))
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            expect($props['tenders']['data'])->toHaveCount(1)
                // Counts ignore the status filter, or every other tab would
                // read zero the moment one was selected.
                ->and($props['statusCounts']['published'])->toBe(2);
        });
});

/**
 * orderBy() validates the direction and throws on anything else, but hands the
 * column straight to the grammar — an unknown one was a 500 rather than a
 * rejected request.
 */
test('an unusable sort or direction falls back instead of erroring', function (array $query, string $sort, string $direction) {
    [$user] = userOnProject();

    $this->actingAs($user)
        ->get(route('tenders.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', $sort)
            ->where('filters.direction', $direction)
        );
})->with([
    'unknown column' => [['sort' => 'nonexistent_column'], 'created_at', 'desc'],
    'injection attempt' => [['sort' => 'id); drop table tenders; --'], 'created_at', 'desc'],
    'bad direction' => [['direction' => 'sideways'], 'created_at', 'desc'],
    'valid pair' => [['sort' => 'submission_deadline', 'direction' => 'asc'], 'submission_deadline', 'asc'],
]);

/**
 * The page hands these straight back to DataTable, which merges them into every
 * sort request. Before, it received nothing: sorting wiped the search and the
 * status, and could never toggle to descending.
 */
test('the filters it echoes back are complete enough to sort with', function () {
    [$user, $project] = userOnProject();
    Tender::factory()->create(['project_id' => $project->id, 'status' => TenderStatus::Published]);

    $this->actingAs($user)
        ->get(route('tenders.index', ['search' => 'anything', 'status' => 'published']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'anything')
            ->where('filters.status', 'published')
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
        );
});

test('the summary counts the same scope as the rows', function () {
    [$user, $project] = userOnProject();

    Tender::factory()->count(3)->create([
        'project_id' => $project->id,
        'status' => TenderStatus::Published,
        'submission_deadline' => now()->addDays(3),
    ]);
    Tender::factory()->create([
        'project_id' => $project->id,
        'status' => TenderStatus::Published,
        'submission_deadline' => now()->addDays(30),
    ]);
    Tender::factory()->create(['project_id' => $project->id, 'status' => TenderStatus::UnderEvaluation]);
    // Outside the user's projects — must not reach any figure on the page.
    Tender::factory()->count(5)->create(['project_id' => Project::factory()->create()->id]);

    $this->actingAs($user)
        ->get(route('tenders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 5)
            ->where('summary.open', 4)
            ->where('summary.closing_this_week', 3)
            ->where('summary.awaiting_evaluation', 1)
        );
});

test('it renders for a user with no projects at all', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tenders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 0)
            ->where('tenders.data', [])
        );
});
