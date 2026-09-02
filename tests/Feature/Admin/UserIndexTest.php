<?php

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function adminForUserList(string ...$slugs): User
{
    // The admin route group gates on the role slug (role:admin,super_admin),
    // and firstOrCreate keeps a second call in the same test from colliding
    // with the unique index on roles.slug.
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true]);

    foreach ($slugs === [] ? ['admin.users'] : $slugs as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('.', ' ', $slug)), 'module' => explode('.', $slug)[0]],
        );
        $role->permissions()->attach($permission->id);
    }

    return User::factory()->create([
        'role_id' => $role->id,
        // The factory randomises both of these, and the summary counts them.
        // Pinned so the actor never lands in a figure a test is asserting on.
        'last_login_at' => now(),
        'is_2fa_enabled' => true,
    ]);
}

/** Every row from the last response, keyed by user id. */
function userRows(Assert $page): array
{
    return collect($page->toArray()['props']['users']['data'])
        ->keyBy('id')
        ->all();
}

/**
 * The bug this page was rebuilt around.
 *
 * orderBy() does not check the column. Laravel validates the direction and
 * throws on anything but asc/desc, but hands the column straight to the SQL
 * grammar — so `?sort=nope` was a 500 on a page anyone could reach with a
 * hand-edited URL.
 */
test('an unusable sort or direction falls back instead of erroring', function (array $query, string $sort, string $direction) {
    $this->actingAs(adminForUserList())
        ->get(route('admin.users.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', $sort)
            ->where('filters.direction', $direction)
        );
})->with([
    'unknown column' => [['sort' => 'not_a_column'], 'created_at', 'desc'],
    'injection attempt' => [['sort' => 'id); drop table users; --'], 'created_at', 'desc'],
    // Real columns are no safer to expose: ordering by the password hash or
    // the 2FA secret leaks the shape of both to anyone who can read the list.
    'a real column that is not offered' => [['sort' => 'password'], 'created_at', 'desc'],
    'bad direction' => [['direction' => 'upwards'], 'created_at', 'desc'],
    'valid pair' => [['sort' => 'name', 'direction' => 'asc'], 'name', 'asc'],
]);

/**
 * DataTable merges these into every sort request, so an incomplete set wipes
 * the active search and makes descending order unreachable.
 */
test('the filters it echoes back are complete enough to sort with', function () {
    $admin = adminForUserList();

    $this->actingAs($admin)
        ->get(route('admin.users.index', [
            'search' => 'Rasha',
            'status' => 'inactive',
            'role_id' => $admin->role_id,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'Rasha')
            ->where('filters.status', 'inactive')
            ->where('filters.role_id', $admin->role_id)
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
        );
});

/** The unset keys have to arrive as null rather than be dropped. */
test('the filter keys are present even when nothing is filtered', function () {
    $this->actingAs(adminForUserList())
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', null)
            ->where('filters.status', null)
            ->where('filters.role_id', null)
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
        );
});

/**
 * The old filter read `$request->has('is_active')` and coerced the value with
 * boolean(), so an empty parameter — what a cleared select submits — narrowed
 * the list to the deactivated accounts instead of clearing the filter.
 */
test('an empty status parameter clears the filter rather than selecting the deactivated', function () {
    $admin = adminForUserList();
    User::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['status' => '']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', null)
            ->count('users.data', 2)
        );
});

/**
 * select() replaces the select list wholesale, so calling it after withCount()
 * drops the count subquery and the column renders as an empty cell on every
 * row — which is exactly how the projects list shipped.
 */
test('every row carries its assigned project count', function () {
    $admin = adminForUserList();
    $member = User::factory()->create();
    $member->projects()->attach(
        Project::factory()->count(2)->create()->pluck('id'),
        ['project_role' => 'member'],
    );

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($member, $admin) {
            $rows = userRows($page);

            expect($rows[$member->id]['projects_count'])->toBe(2)
                ->and($rows[$admin->id]['projects_count'])->toBe(0);
        });
});

test('the active and inactive counts cover both states and follow the search', function () {
    $admin = adminForUserList();

    User::factory()->count(2)->create(['name' => 'Basra Site Engineer', 'is_active' => true]);
    User::factory()->create(['name' => 'Basra Site Clerk', 'is_active' => false]);
    User::factory()->create(['name' => 'Unrelated Person', 'is_active' => false]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(function (Assert $page) {
            $counts = $page->toArray()['props']['statusCounts'];

            expect(array_keys($counts))->toBe(['active', 'inactive'])
                // The two Basra engineers plus the acting admin.
                ->and($counts['active'])->toBe(3)
                ->and($counts['inactive'])->toBe(2);
        });

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'Basra']))
        ->assertInertia(function (Assert $page) {
            $counts = $page->toArray()['props']['statusCounts'];

            expect($counts['active'])->toBe(2)
                ->and($counts['inactive'])->toBe(1);
        });
});

test('the status filter narrows the rows but not the counts', function () {
    $admin = adminForUserList();
    User::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['status' => 'inactive']))
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            expect($props['users']['data'])->toHaveCount(1)
                ->and($props['statusCounts']['active'])->toBe(1)
                ->and($props['summary']['total'])->toBe(2);
        });
});

/** The role filter is part of the base scope, so the tab counts follow it. */
test('the role filter narrows the rows and the status counts with them', function () {
    $admin = adminForUserList();
    $evaluatorRole = Role::factory()->create(['name' => 'Evaluator', 'slug' => 'evaluator']);
    User::factory()->create(['role_id' => $evaluatorRole->id]);
    User::factory()->create(['role_id' => $evaluatorRole->id, 'is_active' => false]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['role_id' => $evaluatorRole->id]))
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            expect($props['users']['data'])->toHaveCount(2)
                ->and($props['statusCounts']['active'])->toBe(1)
                ->and($props['statusCounts']['inactive'])->toBe(1)
                ->and($props['summary']['total'])->toBe(2);
        });
});

test('search matches the name, the email and the phone', function (string $term) {
    $admin = adminForUserList();
    $match = User::factory()->create([
        'name' => 'Noor Al-Karrada',
        'email' => 'noor@mpc-iraq.test',
        'phone' => '+9647701234567',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => $term]))
        ->assertInertia(function (Assert $page) use ($match) {
            $rows = $page->toArray()['props']['users']['data'];

            expect($rows)->toHaveCount(1)
                ->and($rows[0]['id'])->toBe($match->id);
        });
})->with([
    'name' => ['Karrada'],
    'email' => ['mpc-iraq'],
    'phone' => ['7701234567'],
]);

test('the summary counts the roster, the accounts never used and the 2FA gaps', function () {
    $admin = adminForUserList();

    User::factory()->count(2)->create(['last_login_at' => null, 'is_2fa_enabled' => false]);
    User::factory()->create(['last_login_at' => now(), 'is_2fa_enabled' => true]);
    // Deactivated, so it is already accounted for and should not also show up
    // as an outstanding 2FA exception or an unclaimed credential.
    User::factory()->create([
        'is_active' => false,
        'last_login_at' => null,
        'is_2fa_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 5)
            ->where('summary.active', 4)
            ->where('summary.never_signed_in', 2)
            ->where('summary.without_2fa', 2)
        );
});

/**
 * The list offered Edit and Delete on every row, including the accounts the
 * policy refuses to hand over — so the only feedback was a 403 on a control
 * that looked available.
 */
test('the row actions follow the policy rather than being offered to everyone', function () {
    $admin = adminForUserList();
    $superRole = Role::firstOrCreate(
        ['slug' => Role::SUPER_ADMIN],
        ['name' => 'Super Admin', 'is_system' => true],
    );
    $super = User::factory()->create(['role_id' => $superRole->id]);
    $peer = User::factory()->create();
    $retired = User::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(function (Assert $page) use ($admin, $super, $peer, $retired) {
            $rows = userRows($page);

            // A super admin is administrable only by another super admin.
            expect($rows[$super->id]['can_edit'])->toBeFalse()
                ->and($rows[$super->id]['can_deactivate'])->toBeFalse()
                ->and($rows[$peer->id]['can_edit'])->toBeTrue()
                ->and($rows[$peer->id]['can_deactivate'])->toBeTrue()
                // Editing your own account is fine; locking yourself out is not.
                ->and($rows[$admin->id]['can_edit'])->toBeTrue()
                ->and($rows[$admin->id]['can_deactivate'])->toBeFalse()
                // Already deactivated — the endpoint would be a no-op.
                ->and($rows[$retired->id]['can_edit'])->toBeTrue()
                ->and($rows[$retired->id]['can_deactivate'])->toBeFalse();
        });
});

test('an administrator without the admin.users permission is offered no row actions', function () {
    $admin = adminForUserList('projects.view');
    $peer = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($peer) {
            $row = userRows($page)[$peer->id];

            expect($row['can_edit'])->toBeFalse()
                ->and($row['can_deactivate'])->toBeFalse();
        });
});

/**
 * roles.name holds English only — the seeder writes "Procurement Officer" and
 * nothing translates it — so the label has to be keyed on the slug instead.
 */
test('each row carries the role slug its label is keyed on', function () {
    $this->actingAs(adminForUserList())
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.role.slug', 'admin')
            ->where('users.data.0.role.name', 'Admin')
        );
});

test('the status options it offers are exactly the two the filter accepts', function () {
    $this->actingAs(adminForUserList())
        ->get(route('admin.users.index'))
        ->assertInertia(function (Assert $page) {
            $options = $page->toArray()['props']['statusOptions'];

            expect(array_column($options, 'value'))->toBe(['active', 'inactive'])
                ->and(array_column($options, 'labelKey'))
                ->toBe(['status.active', 'status.inactive']);
        });
});

/** The actor is always in their own list, so this is the emptiest it gets. */
test('it renders with the actor as the only account', function () {
    $this->actingAs(adminForUserList())
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 1)
            ->where('summary.never_signed_in', 0)
            ->where('summary.without_2fa', 0)
            ->where('statusCounts.inactive', 0)
            ->count('users.data', 1)
        );
});
