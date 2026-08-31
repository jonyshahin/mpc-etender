<?php

use App\Enums\VendorDocStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function adminForVendorList(string ...$slugs): User
{
    // The admin route group gates on the role slug (role:admin,super_admin),
    // and firstOrCreate keeps a second call in the same test from colliding
    // with the unique index on roles.slug.
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true]);

    foreach ($slugs === [] ? ['vendors.view'] : $slugs as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('.', ' ', $slug)), 'module' => explode('.', $slug)[0]],
        );
        $role->permissions()->attach($permission->id);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

test('the status counts cover every status and follow the search', function () {
    $admin = adminForVendorList();

    Vendor::factory()->count(2)->create([
        'prequalification_status' => VendorStatus::Qualified,
        'company_name' => 'Sama Cool HVAC',
    ]);
    Vendor::factory()->create([
        'prequalification_status' => VendorStatus::Pending,
        'company_name' => 'Unrelated Trading',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.vendors.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $counts = $page->toArray()['props']['statusCounts'];

            // All six, including the two the old filter could not reach.
            expect(array_keys($counts))
                ->toBe(array_map(fn ($c) => $c->value, VendorStatus::cases()))
                ->and($counts['qualified'])->toBe(2)
                ->and($counts['pending'])->toBe(1)
                ->and($counts['under_review'])->toBe(0)
                ->and($counts['blacklisted'])->toBe(0);
        });

    $this->actingAs($admin)
        ->get(route('admin.vendors.index', ['search' => 'Sama']))
        ->assertInertia(function (Assert $page) {
            $counts = $page->toArray()['props']['statusCounts'];

            expect($counts['qualified'])->toBe(2)
                ->and($counts['pending'])->toBe(0);
        });
});

test('the status filter narrows the rows but not the counts', function () {
    $admin = adminForVendorList();
    Vendor::factory()->count(2)->create(['prequalification_status' => VendorStatus::Qualified]);
    Vendor::factory()->create(['prequalification_status' => VendorStatus::Pending]);

    $this->actingAs($admin)
        ->get(route('admin.vendors.index', ['status' => 'pending']))
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            expect($props['vendors']['data'])->toHaveCount(1)
                ->and($props['statusCounts']['qualified'])->toBe(2);
        });
});

/**
 * The backend has supported this filter since the page was written; the UI
 * never offered it, so it was unreachable.
 */
test('it filters by category', function () {
    $admin = adminForVendorList();
    $hvac = Category::factory()->create(['name_en' => 'HVAC']);
    $civil = Category::factory()->create(['name_en' => 'Civil']);

    $inHvac = Vendor::factory()->create();
    $inHvac->categories()->attach($hvac->id);
    $inCivil = Vendor::factory()->create();
    $inCivil->categories()->attach($civil->id);

    $this->actingAs($admin)
        ->get(route('admin.vendors.index', ['category_id' => $hvac->id]))
        ->assertInertia(function (Assert $page) use ($inHvac) {
            $rows = $page->toArray()['props']['vendors']['data'];

            expect($rows)->toHaveCount(1)
                ->and($rows[0]['id'])->toBe($inHvac->id);
        });
});

test('search matches the Arabic name and the licence number too', function (string $term) {
    $admin = adminForVendorList();
    $match = Vendor::factory()->create([
        'company_name' => 'Sama Cool',
        'company_name_ar' => 'سما البرد',
        'trade_license_no' => '2020466320',
        'email' => 'ibrahim@samacool.test',
    ]);
    Vendor::factory()->create(['company_name' => 'Somebody Else']);

    $this->actingAs($admin)
        ->get(route('admin.vendors.index', ['search' => $term]))
        ->assertInertia(function (Assert $page) use ($match) {
            $rows = $page->toArray()['props']['vendors']['data'];

            expect($rows)->toHaveCount(1)
                ->and($rows[0]['id'])->toBe($match->id);
        });
})->with([
    'english name' => ['Sama'],
    'arabic name' => ['البرد'],
    'licence number' => ['2020466320'],
    'email' => ['ibrahim@'],
]);

/**
 * orderBy() validates the direction and throws on anything else, but hands the
 * column straight to the grammar — an unknown one was a 500.
 */
test('an unusable sort or direction falls back instead of erroring', function (array $query, string $sort, string $direction) {
    $this->actingAs(adminForVendorList())
        ->get(route('admin.vendors.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', $sort)
            ->where('filters.direction', $direction)
        );
})->with([
    'unknown column' => [['sort' => 'not_a_column'], 'created_at', 'desc'],
    'injection attempt' => [['sort' => 'id); drop table vendors; --'], 'created_at', 'desc'],
    'bad direction' => [['direction' => 'upwards'], 'created_at', 'desc'],
    'valid pair' => [['sort' => 'company_name', 'direction' => 'asc'], 'company_name', 'asc'],
]);

/**
 * DataTable merges these into every sort request. Before, it received nothing:
 * sorting wiped the search, status and category, and could never toggle to
 * descending.
 */
test('the filters it echoes back are complete enough to sort with', function () {
    $admin = adminForVendorList();
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.index', [
            'search' => 'anything',
            'status' => 'qualified',
            'category_id' => $category->id,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'anything')
            ->where('filters.status', 'qualified')
            ->where('filters.category_id', $category->id)
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
        );
});

test('the summary counts vendors, not documents', function () {
    $admin = adminForVendorList();

    Vendor::factory()->count(3)->create(['prequalification_status' => VendorStatus::Qualified]);
    Vendor::factory()->create(['prequalification_status' => VendorStatus::Pending]);
    Vendor::factory()->create(['prequalification_status' => VendorStatus::UnderReview]);

    // Two unreviewed files on one vendor is one vendor needing attention.
    // Pinned statuses: the factory defaults to pending, which would
    // otherwise fold these two into awaiting_review and blur the assertion.
    $withDocs = Vendor::factory()->create(['prequalification_status' => VendorStatus::Qualified]);
    VendorDocument::factory()->count(2)->for($withDocs)->create(['status' => VendorDocStatus::Pending]);
    $reviewed = Vendor::factory()->create(['prequalification_status' => VendorStatus::Qualified]);
    VendorDocument::factory()->for($reviewed)->create(['status' => VendorDocStatus::Approved]);

    $this->actingAs($admin)
        ->get(route('admin.vendors.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 7)
            ->where('summary.qualified', 5)
            ->where('summary.awaiting_review', 2)
            ->where('summary.documents_pending', 1)
        );
});

test('each row carries its document count', function () {
    $admin = adminForVendorList();
    $vendor = Vendor::factory()->create();
    VendorDocument::factory()->count(3)->for($vendor)->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.index'))
        ->assertInertia(function (Assert $page) {
            $rows = $page->toArray()['props']['vendors']['data'];

            expect($rows[0]['documents_count'])->toBe(3);
        });
});

test('it renders on an empty register', function () {
    $this->actingAs(adminForVendorList())
        ->get(route('admin.vendors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 0)
            ->where('vendors.data', [])
        );
});
