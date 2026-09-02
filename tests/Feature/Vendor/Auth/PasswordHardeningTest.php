<?php

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

// ── Reset-link requests ──

test('reset-link requests are throttled per address', function () {
    Notification::fake();
    Vendor::factory()->create(['email' => 'a@vendor.test']);

    // The broker throttles one token per address per minute, so a single
    // address was already covered. Nothing covered an attacker walking a list.
    foreach (range(1, 6) as $n) {
        $this->post(route('vendor.password.email'), ['email' => "unknown{$n}@nowhere.test"]);
    }

    $this->post(route('vendor.password.email'), ['email' => 'a@vendor.test'])
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

test('a throttled reset request still does not reveal whether the address exists', function () {
    Vendor::factory()->create(['email' => 'known@vendor.test']);

    foreach (range(1, 6) as $n) {
        $this->post(route('vendor.password.email'), ['email' => "burn{$n}@nowhere.test"]);
    }

    // Frozen so availableIn() reports the same figure for both calls, and the
    // two messages are comparable character for character.
    $this->freezeTime();
    $expected = __('auth.vendor_reset_throttled', [
        'seconds' => RateLimiter::availableIn('vendor-password-reset|127.0.0.1'),
    ]);

    $this->post(route('vendor.password.email'), ['email' => 'known@vendor.test'])
        ->assertSessionHasErrors(['email' => $expected]);

    $this->post(route('vendor.password.email'), ['email' => 'nobody@nowhere.test'])
        ->assertSessionHasErrors(['email' => $expected]);
});

// ── Completing a reset ──

test('a failed reset does not reveal whether the address exists', function () {
    Vendor::factory()->create([
        'email' => 'known@vendor.test',
        'password' => Hash::make('old-password-1'),
    ]);

    // The forgot-password step is careful about this; completion was not. It
    // returned the broker status verbatim, and passwords.user reads "We can't
    // find a user with that email address."
    $expected = __('messages.vendor_password_reset_failed_generic');

    $this->post(route('vendor.password.update'), [
        'token' => 'not-a-real-token',
        'email' => 'known@vendor.test',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertSessionHasErrors(['email' => $expected]);

    $this->post(route('vendor.password.update'), [
        'token' => 'not-a-real-token',
        'email' => 'nobody@nowhere.test',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertSessionHasErrors(['email' => $expected]);
});

// ── Changing a password while signed in ──

test('changing the password invalidates a stolen remember-me cookie', function () {
    $vendor = Vendor::factory()->qualified()->create([
        'is_active' => true,
        'password' => Hash::make('old-password-1'),
        'remember_token' => 'stolen-remember-token',
    ]);

    $this->actingAs($vendor, 'vendor')->put(route('vendor.password.change'), [
        'current_password' => 'old-password-1',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ]);

    // NewPasswordController rotates the token on a reset; this path did not,
    // so a remember-me cookie taken before the change kept working after it.
    expect($vendor->fresh()->remember_token)->not->toBe('stolen-remember-token');
});

test('a reset also invalidates a stolen remember-me cookie', function () {
    $vendor = Vendor::factory()->qualified()->create([
        'email' => 'known@vendor.test',
        'password' => Hash::make('old-password-1'),
        'remember_token' => 'stolen-remember-token',
    ]);

    $token = Password::broker('vendors')->createToken($vendor);

    $this->post(route('vendor.password.update'), [
        'token' => $token,
        'email' => 'known@vendor.test',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ]);

    expect($vendor->fresh()->remember_token)->not->toBe('stolen-remember-token');
});
