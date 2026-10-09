<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Who can reach the admin area, from every side BUG-32 left open.
 *
 * RoleGateTest covers internal users of the wrong role and the Horizon/Pulse
 * gate closures. This covers what it does not: the vendor guard, which is a
 * separate authenticated population with its own session, and the structural
 * guarantee that no admin route exists outside the role-gated group — the way
 * this would most likely regress, silently, is someone adding a route below
 * the group's closing brace.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function adminAccessUser(string $slug): User
{
    $role = Role::firstOrCreate(
        ['slug' => $slug],
        ['name' => ucfirst($slug), 'is_system' => true],
    );

    return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
}

// ── The vendor guard ───────────────────────────────────────────────────────

/**
 * Signed in the way a vendor really is — through the portal form — rather than
 * with actingAs($vendor, 'vendor'), which also makes `vendor` the *default*
 * guard and so tests a request no browser can make.
 */
function signedInVendorForAdminAccess($test): Vendor
{
    $vendor = Vendor::factory()->create(['is_active' => true]);

    $test->post(route('vendor.login.store'), ['email' => $vendor->email, 'password' => 'password'])
        ->assertRedirect(route('vendor.dashboard'));

    return $vendor;
}

/**
 * A vendor session authenticates the `vendor` guard only. The admin group's
 * `auth` middleware checks the default `web` guard, so a vendor is a guest
 * there — sent to the portal sign-in, never shown the page.
 */
test('a signed-in vendor cannot open an admin page', function () {
    signedInVendorForAdminAccess($this);

    $this->get(route('admin.users.index'))->assertRedirect(route('vendor.login'));
});

test('a signed-in vendor cannot write through an admin route', function () {
    signedInVendorForAdminAccess($this);
    $roleId = adminAccessUser('viewer')->role_id;

    $this->post(route('admin.users.store'), [
        'name' => 'Planted',
        'email' => 'planted@example.com',
        'password' => 'Password123!',
        'role_id' => $roleId,
    ])->assertRedirect(route('vendor.login'));

    expect(User::where('email', 'planted@example.com')->exists())->toBeFalse();
});

/**
 * The second layer. Were a vendor ever resolved on the default guard — a
 * misconfigured AUTH_GUARD, a middleware that calls shouldUse() — `auth` would
 * let them through, and the role gate is what still stands: a Vendor has no
 * role, so it is refused outright.
 */
test('the role gate refuses a vendor even if auth were to let one through', function () {
    $vendor = Vendor::factory()->create(['is_active' => true]);

    // actingAs with a guard calls shouldUse(), which is exactly the
    // misconfiguration this guards against.
    $this->actingAs($vendor, 'vendor')
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

/**
 * Horizon and Pulse sit outside the admin group and carry their own gates.
 * The denial happens in middleware, before either touches Redis or its
 * storage tables, so these run against the real routes.
 */
test('a signed-in vendor cannot open Horizon or Pulse', function () {
    signedInVendorForAdminAccess($this);

    $this->get('/horizon/api/stats')->assertForbidden();
    $this->get('/pulse')->assertForbidden();
});

// ── Internal users ─────────────────────────────────────────────────────────

test('a verified internal user without an admin role is refused', function () {
    $officer = adminAccessUser('procurement_officer');

    expect($officer->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($officer)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($officer)->get('/horizon/api/stats')->assertForbidden();
    $this->actingAs($officer)->get('/pulse')->assertForbidden();
});

test('an admin and a super admin both get in', function (string $slug) {
    $this->actingAs(adminAccessUser($slug))
        ->get(route('admin.dashboard'))
        ->assertOk();
})->with(['admin', 'super_admin']);

// ── The structural guarantee ───────────────────────────────────────────────

/**
 * Every route under /admin, and every route named admin.*, carries the role
 * gate with exactly the two admin roles. Asserted over the route table rather
 * than by requesting each page, so a route added outside the group fails here
 * even if nobody thought to write a test for it.
 */
test('no admin route exists outside the role gate', function () {
    $adminRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => str_starts_with($route->uri(), 'admin/')
            || str_starts_with((string) $route->getName(), 'admin.'));

    expect($adminRoutes)->not->toBeEmpty();

    $ungated = $adminRoutes
        ->reject(fn (RouteDefinition $route) => in_array(
            'role:admin,super_admin',
            $route->gatherMiddleware(),
            true,
        ))
        ->map(fn (RouteDefinition $route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($ungated)->toBe([], 'Admin routes without the role gate: '.implode(', ', $ungated));
});
