<?php

use App\Http\Requests\Admin\ReviewVendorCategoryRequest;
use App\Http\Requests\Vendor\StoreCategoryChangeRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeReviewRequest(?User $user = null, ?Vendor $vendor = null): ReviewVendorCategoryRequest
{
    $req = ReviewVendorCategoryRequest::create('/fake', 'POST', ['action' => 'approve']);
    $req->setUserResolver(fn ($guard = null) => match ($guard) {
        'web' => $user,
        'vendor' => $vendor,
        default => $user,
    });
    $req->setContainer(app());

    return $req;
}

function makeStoreRequest(?Vendor $vendor = null, ?User $user = null): StoreCategoryChangeRequest
{
    $req = StoreCategoryChangeRequest::create('/fake', 'POST', []);
    $req->setUserResolver(fn ($guard = null) => match ($guard) {
        'vendor' => $vendor,
        'web' => $user,
        default => $vendor,
    });
    $req->setContainer(app());

    return $req;
}

/** Mint a user with a fresh role carrying just the given permission slugs. */
function userWithPerms(array $slugs): User
{
    $role = Role::factory()->create();
    foreach ($slugs as $slug) {
        $perm = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('.', ' ', $slug)), 'module' => explode('.', $slug)[0]]
        );
        $role->permissions()->attach($perm->id);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

// ── Admin review FormRequest ──────────────────────────────────

it('ADMIN-PERM-01: admin without vendors.review_category_requests fails authorize (→ 403)', function () {
    $user = userWithPerms(['vendors.view']); // has something else, NOT the new one
    $req = makeReviewRequest($user);

    expect($req->authorize())->toBeFalse();
});

it('ADMIN-PERM-02: admin WITH vendors.review_category_requests passes authorize', function () {
    $user = userWithPerms(['vendors.review_category_requests']);
    $req = makeReviewRequest($user);

    expect($req->authorize())->toBeTrue();
});

it('ADMIN-PERM-03: vendor on the wrong guard cannot satisfy admin authorize', function () {
    $vendor = Vendor::factory()->create();
    // No web user, only a vendor — admin authorize looks at web guard.
    $req = makeReviewRequest(null, $vendor);

    expect($req->authorize())->toBeFalse();
});

it('ADMIN-PERM-04: unauthenticated request fails authorize', function () {
    $req = makeReviewRequest(null);

    expect($req->authorize())->toBeFalse();
});

// ── Vendor store FormRequest ──────────────────────────────────

it('VENDOR-PERM-01: authenticated vendor passes authorize', function () {
    $vendor = Vendor::factory()->create();
    $req = makeStoreRequest($vendor);

    expect($req->authorize())->toBeTrue();
});

it('VENDOR-PERM-02: web admin cannot submit as vendor (wrong guard)', function () {
    $admin = User::factory()->create();
    $req = makeStoreRequest(null, $admin);

    expect($req->authorize())->toBeFalse();
});

it('VENDOR-PERM-03: unauthenticated vendor fails authorize', function () {
    $req = makeStoreRequest(null);

    expect($req->authorize())->toBeFalse();
});
