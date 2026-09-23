<?php

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Where an unauthenticated visitor is sent depends on which door they knocked on.
 *
 * Laravel's Authenticate middleware redirects guests to `route('login')` with no
 * knowledge of which guard refused them, so every vendor route sent vendors to
 * the MPC staff sign-in page. The reported symptom was logging out — POST
 * /vendor/logout with an already-expired session lands there — but it is the
 * same for any vendor page opened after a session ends, which is the common
 * case: a vendor comes back the next morning to a form that asks for staff
 * credentials they do not have.
 */
test('a vendor route sends guests to the vendor sign-in page', function () {
    $this->get(route('vendor.dashboard'))->assertRedirect(route('vendor.login'));
});

test('logging out lands on the vendor sign-in page, not the staff one', function () {
    $vendor = Vendor::factory()->create(['is_active' => true]);

    $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.logout'))
        ->assertRedirect(route('vendor.login'));

    $this->assertGuest('vendor');
});

/**
 * The case the reported symptom actually came through: the session had already
 * gone, so `auth:vendor` refused the request before the controller could run.
 */
test('logging out with an expired session still lands on the vendor page', function () {
    $this->post(route('vendor.logout'))->assertRedirect(route('vendor.login'));
});

/**
 * Staff routes lead there too. Redirecting them to route('login') would hand
 * the staff path to anyone who opened a single staff URL, which is the whole
 * thing STAFF_AUTH_PREFIX exists to avoid.
 */
test('a staff route does not disclose the staff sign-in path to guests', function () {
    $this->get(route('dashboard'))->assertRedirect(route('vendor.login'));
    $this->get(route('admin.users.index'))->assertRedirect(route('vendor.login'));
});

test('the public login path leads to the portal', function () {
    $this->get('/login')->assertRedirect('/vendor/login');
});

test('staff sign-in is reachable at its configured prefix', function () {
    expect(route('login', absolute: false))->toBe('/staff/login');

    $this->get(route('login'))->assertOk();
});

/**
 * The landing page is the one page every visitor sees, so it must not carry the
 * staff path. That its *button* is gone is a client-rendered fact this level
 * cannot see — the check here is that nothing server-side discloses the path.
 */
test('no page a guest can reach discloses the staff sign-in path', function () {
    foreach ([route('home'), route('vendor.login')] as $url) {
        expect($this->get($url)->getContent())->not->toContain('/staff/login');
    }
});
