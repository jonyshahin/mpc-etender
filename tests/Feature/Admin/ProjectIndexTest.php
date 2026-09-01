<?php

use App\Enums\ProjectStatus;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function adminForProjectList(string ...$slugs): User
{
    // The admin route group gates on the role slug (role:admin,super_admin),
    // and firstOrCreate keeps a second call in the same test from colliding
    // with the unique index on roles.slug.
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true]);

    foreach ($slugs === [] ? ['projects.view'] : $slugs as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('.', ' ', $slug)), 'module' => explode('.', $slug)[0]],
        );
        $role->permissions()->attach($permission->id);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * The bug this page was rebuilt around.
 *
 * The query called select() after withCount(). select() replaces the select
 * list wholesale, so the count subqueries were dropped and the Tenders and
 * Team Size columns rendered as empty cells on every row in production.
 */
test('every row carries its tender and team counts', function () {
    $admin = adminForProjectList();
    $project = Project::factory()->create();
    Tender::factory()->count(3)->create(['project_id' => $project->id]);
    $project->users()->attach(User::factory()->count(2)->create()->pluck('id'), [
        'project_role' => 'viewer',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.projects.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $row = $page->toArray()['props']['projects']['data'][0];

            expect($row['tenders_count'])->toBe(3)
                ->and($row['users_count'])->toBe(2);
        });
});

test('the status counts cover every status and follow the search', function () {
    $admin = adminForProjectList();

    Project::factory()->count(2)->create([
        'status' => ProjectStatus::Active,
        'name' => 'Basra Port Terminal Upgrade',
    ]);
    Project::factory()->create([
        'status' => ProjectStatus::Completed,
        'name' => 'Unrelated Works',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.projects.index'))
        ->assertInertia(function (Assert $page) {
            $counts = $page->toArray()['props']['statusCounts'];

            expect(array_keys($counts))
                ->toBe(array_map(fn ($c) => $c->value, ProjectStatus::cases()))
                ->and($counts['active'])->toBe(2)
                ->and($counts['completed'])->toBe(1)
                ->and($counts['on_hold'])->toBe(0)
                ->and($counts['cancelled'])->toBe(0);
        });

    $this->actingAs($admin)
        ->get(route('admin.projects.index', ['search' => 'Basra']))
        ->assertInertia(function (Assert $page) {
            $counts = $page->toArray()['props']['statusCounts'];

            expect($counts['active'])->toBe(2)
                ->and($counts['completed'])->toBe(0);
        });
});

test('the status filter narrows the rows but not the counts', function () {
    $admin = adminForProjectList();
    Project::factory()->count(2)->create(['status' => ProjectStatus::Active]);
    Project::factory()->create(['status' => ProjectStatus::OnHold]);

    $this->actingAs($admin)
        ->get(route('admin.projects.index', ['status' => 'on_hold']))
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            expect($props['projects']['data'])->toHaveCount(1)
                ->and($props['statusCounts']['active'])->toBe(2);
        });
});

test('search matches the Arabic name, the code and the client too', function (string $term) {
    $admin = adminForProjectList();
    $match = Project::factory()->create([
        'name' => 'Karbala Water Treatment Plant',
        'name_ar' => 'محطة معالجة مياه كربلاء',
        'code' => 'PRJ-KBL001',
        'client_name' => 'Ministry of Water Resources',
    ]);
    Project::factory()->create(['name' => 'Something Else', 'name_ar' => null]);

    $this->actingAs($admin)
        ->get(route('admin.projects.index', ['search' => $term]))
        ->assertInertia(function (Assert $page) use ($match) {
            $rows = $page->toArray()['props']['projects']['data'];

            expect($rows)->toHaveCount(1)
                ->and($rows[0]['id'])->toBe($match->id);
        });
})->with([
    'english name' => ['Karbala'],
    'arabic name' => ['كربلاء'],
    'code' => ['KBL001'],
    'client' => ['Water Resources'],
]);

/**
 * orderBy() validates the direction and throws on anything else, but hands the
 * column straight to the grammar — an unknown one was a 500.
 */
test('an unusable sort or direction falls back instead of erroring', function (array $query, string $sort, string $direction) {
    $this->actingAs(adminForProjectList())
        ->get(route('admin.projects.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', $sort)
            ->where('filters.direction', $direction)
        );
})->with([
    'unknown column' => [['sort' => 'not_a_column'], 'created_at', 'desc'],
    'injection attempt' => [['sort' => 'id); drop table projects; --'], 'created_at', 'desc'],
    'bad direction' => [['direction' => 'upwards'], 'created_at', 'desc'],
    'valid pair' => [['sort' => 'name', 'direction' => 'asc'], 'name', 'asc'],
]);

/**
 * DataTable merges these into every sort request, so an incomplete set wipes
 * the active search and makes descending order unreachable.
 */
test('the filters it echoes back are complete enough to sort with', function () {
    $this->actingAs(adminForProjectList())
        ->get(route('admin.projects.index', ['search' => 'Basra', 'status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'Basra')
            ->where('filters.status', 'active')
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
        );
});

test('the summary counts projects, their tenders and the unstaffed ones', function () {
    $admin = adminForProjectList();

    $staffed = Project::factory()->create(['status' => ProjectStatus::Active]);
    $staffed->users()->attach(User::factory()->create()->id, ['project_role' => 'manager']);
    Tender::factory()->count(2)->create(['project_id' => $staffed->id]);

    // Nobody assigned: every tender under this one is invisible in the app.
    $unstaffed = Project::factory()->create(['status' => ProjectStatus::Active]);
    Tender::factory()->create(['project_id' => $unstaffed->id]);

    Project::factory()->create(['status' => ProjectStatus::Completed]);

    $this->actingAs($admin)
        ->get(route('admin.projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 3)
            ->where('summary.active', 2)
            ->where('summary.tenders', 3)
            ->where('summary.unstaffed', 2)
        );
});

/**
 * The filter used to offer a "draft" option that validation has never accepted,
 * so selecting it always returned nothing. Serving the options from the enum is
 * what stops that reappearing.
 */
test('the status options it offers are exactly the ones a project can hold', function () {
    $this->actingAs(adminForProjectList())
        ->get(route('admin.projects.index'))
        ->assertInertia(function (Assert $page) {
            $values = array_column($page->toArray()['props']['statusOptions'], 'value');

            expect($values)->toBe(array_map(fn ($c) => $c->value, ProjectStatus::cases()))
                ->and($values)->not->toContain('draft');
        });
});

test('it renders with no projects at all', function () {
    $this->actingAs(adminForProjectList())
        ->get(route('admin.projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 0)
            ->where('summary.tenders', 0)
            ->where('projects.data', [])
        );
});
