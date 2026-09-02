<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Every list screen, handed query parameters of the wrong shape.
 *
 * `?search[]=x` arrives as an array where the controller expects a string, and
 * `trim((string) $array)` is a fatal in PHP 8 — a 500 anyone could trigger by
 * editing the address bar, on pages that are otherwise access-controlled. The
 * same shape reaches `sort`, `direction`, `status` and the id filters.
 *
 * Written as a sweep rather than one test per page because this is a class of
 * bug, not an incident: it is the third of its kind on these controllers after
 * an unvalidated `orderBy` column and a filter that read `has()` instead of a
 * value. A new list page inherits the check by adding a line to the array.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function hostileStaffUser(): User
{
    // The real permission set, so no route is skipped for want of access.
    (new PermissionSeeder)->run();

    $role = Role::firstOrCreate(
        ['slug' => Role::SUPER_ADMIN],
        ['name' => 'Super Admin', 'is_system' => true],
    );
    $role->permissions()->sync(Permission::pluck('id'));

    return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
}

/** The shapes a hand-edited or crawler-mangled query string actually takes. */
function hostileQueries(): array
{
    return [
        'array search' => ['search' => ['x']],
        'array sort' => ['sort' => ['name']],
        'array direction' => ['direction' => ['asc']],
        'array status' => ['status' => ['active']],
        'array page' => ['page' => ['2']],
        'array id filter' => ['role_id' => ['x'], 'project_id' => ['x'], 'category_id' => ['x']],
        'array named filter' => ['action' => ['x'], 'entity_type' => ['x'], 'window' => ['open'], 'user_id' => ['x']],
        'array date range' => ['from' => ['2026-01-01'], 'to' => ['2026-12-31']],
        'nested array' => ['search' => ['a' => ['b']]],
        'unknown sort column' => ['sort' => 'no_such_column'],
        'unknown direction' => ['direction' => 'sideways'],
        'empty everything' => ['search' => '', 'sort' => '', 'direction' => '', 'status' => ''],
    ];
}

$staffRoutes = [
    'admin.users.index',
    'admin.projects.index',
    'admin.vendors.index',
    'admin.audit-logs.index',
    'admin.categories.index',
    'admin.roles.index',
    'admin.vendor-category-requests.index',
    'tenders.index',
    'approvals.index',
    'notifications.index',
];

$vendorRoutes = [
    'vendor.tenders.index',
    'vendor.bids.index',
    'vendor.documents.index',
    'vendor.category-requests.index',
    'vendor.notifications.index',
];

test('staff list screens survive a malformed query string', function (string $route) {
    $user = hostileStaffUser();

    $broken = [];

    foreach (hostileQueries() as $label => $query) {
        $response = $this->actingAs($user)->get(route($route).'?'.http_build_query($query));

        if ($response->getStatusCode() >= 500) {
            $broken[] = $label;
        }
    }

    expect($broken)->toBe([], "{$route} returned a server error for: ".implode(', ', $broken));
})->with($staffRoutes);

test('vendor list screens survive a malformed query string', function (string $route) {
    $vendor = Vendor::factory()->create(['is_active' => true]);

    $broken = [];

    foreach (hostileQueries() as $label => $query) {
        $response = $this->actingAs($vendor, 'vendor')
            ->get(route($route).'?'.http_build_query($query));

        if ($response->getStatusCode() >= 500) {
            $broken[] = $label;
        }
    }

    expect($broken)->toBe([], "{$route} returned a server error for: ".implode(', ', $broken));
})->with($vendorRoutes);
