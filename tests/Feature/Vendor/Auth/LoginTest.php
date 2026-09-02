<?php

use App\Enums\VendorStatus;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The limiter is keyed per address-and-IP, so there is no single key to
    // clear; the array cache store it sits on persists across tests in one
    // process, hence the flush.
    Cache::flush();
});

function loginAs(string $email, string $password = 'password'): TestResponse
{
    return test()->post(route('vendor.login.store'), [
        'email' => $email,
        'password' => $password,
    ]);
}

// ── Who may hold a session at all ──

test('a qualified vendor can sign in', function () {
    $vendor = Vendor::factory()->qualified()->create([
        'is_active' => true,
        'password' => Hash::make('correct-horse-1'),
    ]);

    loginAs($vendor->email, 'correct-horse-1')
        ->assertRedirect(route('vendor.dashboard'));

    $this->assertAuthenticatedAs($vendor, 'vendor');
});

test('a suspended vendor cannot sign in', function () {
    $vendor = Vendor::factory()->create([
        'prequalification_status' => VendorStatus::Suspended,
        'is_active' => false,
        'password' => Hash::make('correct-horse-1'),
    ]);

    // Auth::attempt only checks the credentials, and nothing downstream checked
    // is_active — so a suspended vendor held a session and could browse every
    // tender, document and clarification their categories reached.
    loginAs($vendor->email, 'correct-horse-1')->assertSessionHasErrors('email');

    $this->assertGuest('vendor');
});

test('a deactivated vendor cannot sign in even while qualified', function () {
    $vendor = Vendor::factory()->qualified()->create([
        'is_active' => false,
        'password' => Hash::make('correct-horse-1'),
    ]);

    loginAs($vendor->email, 'correct-horse-1')->assertSessionHasErrors('email');

    $this->assertGuest('vendor');
});

test('a blacklisted vendor cannot sign in', function () {
    $vendor = Vendor::factory()->create([
        'prequalification_status' => VendorStatus::Blacklisted,
        'is_active' => true,
        'password' => Hash::make('correct-horse-1'),
    ]);

    loginAs($vendor->email, 'correct-horse-1')->assertSessionHasErrors('email');

    $this->assertGuest('vendor');
});

test('a pending vendor can sign in, since the portal is where they are told why', function () {
    $vendor = Vendor::factory()->create([
        'prequalification_status' => VendorStatus::Pending,
        'is_active' => true,
        'password' => Hash::make('correct-horse-1'),
    ]);

    loginAs($vendor->email, 'correct-horse-1')
        ->assertRedirect(route('vendor.dashboard'));

    $this->assertAuthenticatedAs($vendor, 'vendor');
});

test('a rejected vendor is not told which of the two things went wrong', function () {
    $vendor = Vendor::factory()->create([
        'prequalification_status' => VendorStatus::Blacklisted,
        'is_active' => false,
        'password' => Hash::make('correct-horse-1'),
    ]);

    // Same message as a wrong password: whether an address belongs to a
    // blacklisted vendor is not something the login form should confirm.
    // Asserting both against the one message proves they match, without
    // reaching into a session bag whose type differs between the two reads.
    loginAs($vendor->email, 'correct-horse-1')
        ->assertSessionHasErrors(['email' => __('auth.vendor_failed')]);

    loginAs($vendor->email, 'not-the-password')
        ->assertSessionHasErrors(['email' => __('auth.vendor_failed')]);
});

// ── Brute force ──

test('repeated failures lock the login form', function () {
    $vendor = Vendor::factory()->qualified()->create([
        'is_active' => true,
        'password' => Hash::make('correct-horse-1'),
    ]);

    // Nothing throttled this route: no middleware on it, none globally, and
    // the staff side gets its throttling from Fortify. An attacker had
    // unlimited guesses against a known address.
    foreach (range(1, 5) as $ignored) {
        loginAs($vendor->email, 'wrong-password');
    }

    loginAs($vendor->email, 'wrong-password')->assertSessionHasErrors('email');

    // Locked out even with the right password, which is what makes it a lockout
    // rather than a slow-down.
    loginAs($vendor->email, 'correct-horse-1');
    $this->assertGuest('vendor');
});

test('a successful sign-in clears the failure count', function () {
    $vendor = Vendor::factory()->qualified()->create([
        'is_active' => true,
        'password' => Hash::make('correct-horse-1'),
    ]);

    foreach (range(1, 3) as $ignored) {
        loginAs($vendor->email, 'wrong-password');
    }

    loginAs($vendor->email, 'correct-horse-1');
    $this->assertAuthenticatedAs($vendor, 'vendor');

    $this->post(route('vendor.logout'));

    foreach (range(1, 3) as $ignored) {
        loginAs($vendor->email, 'wrong-password');
    }

    loginAs($vendor->email, 'correct-horse-1');
    $this->assertAuthenticatedAs($vendor, 'vendor');
});
