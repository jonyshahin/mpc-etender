<?php

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Deactivation has to actually deactivate.
 *
 * `is_active` is set by the admin user screen, counted on its dashboard and
 * filtered on in its list — but nothing on the staff side ever read it to
 * decide anything. Fortify was left on its default credential check, so a
 * deactivated account signed in normally and kept every permission its role
 * carried. The vendor guard had the same hole and was closed at login
 * (LoginController::store); nothing closed it for a session already open.
 *
 * Two separate moments, and both have to hold: the sign-in itself, and the
 * next request made by someone deactivated while logged in.
 */

// ── Staff sign-in ──────────────────────────────────────────────────────────

test('a deactivated user cannot sign in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('an active user can still sign in', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

/**
 * The refusal must not distinguish itself from a wrong password: whether an
 * address belongs to a deactivated account is not something the form should
 * confirm. Mirrors the vendor side, which already words it this way.
 */
test('the refusal does not reveal that the account exists', function () {
    $deactivated = User::factory()->create(['is_active' => false]);

    $refused = $this->post(route('login.store'), [
        'email' => $deactivated->email,
        'password' => 'password',
    ]);

    $wrongPassword = $this->post(route('login.store'), [
        'email' => User::factory()->create()->email,
        'password' => 'not-the-password',
    ]);

    // The bag arrives flattened to an array in the session, not as a
    // ViewErrorBag, so reach through it rather than calling first() on it.
    $emailError = fn ($response): ?string => $response->getSession()
        ->get('errors')['default']['messages']['email'][0] ?? null;

    expect($emailError($wrongPassword))->not->toBeNull()
        ->and($emailError($refused))->toBe($emailError($wrongPassword));
});

// ── A session already open ─────────────────────────────────────────────────

/**
 * Sign-in is only half of it. Sessions outlive the request that created them,
 * so an account deactivated at 10am kept working until its holder happened to
 * log out — which is precisely what someone being removed will not do.
 */
test('a user deactivated mid-session is signed out on their next request', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->update(['is_active' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a vendor suspended mid-session is signed out on their next request', function () {
    $vendor = Vendor::factory()->create([
        'is_active' => true,
        'prequalification_status' => VendorStatus::Qualified,
    ]);

    $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'))->assertOk();

    $vendor->update(['prequalification_status' => VendorStatus::Suspended]);

    $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.login'));
    $this->assertGuest('vendor');
});

test('an active session is left alone', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->get(route('dashboard'))->assertOk();

    $this->assertAuthenticated();
});
