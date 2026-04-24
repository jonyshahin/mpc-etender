<?php

use App\Http\Middleware\ForceVendorPasswordChange;
use App\Models\Vendor;
use App\Notifications\VendorResetPasswordNotification;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('phase 2 smoke: must_change_password middleware allow-list blocks dashboard but passes change page', function () {
    $vendor = Vendor::factory()->create([
        'email' => 'forced@example.com',
        'must_change_password' => true,
    ]);

    // Exercise the middleware directly so we verify routing + allow-list logic
    // without needing the React page (ships in Phase 3). Calling it as a closure
    // lets us assert what `$next` receives vs. the redirect it returns.
    $middleware = new ForceVendorPasswordChange;

    // Allow-listed route: middleware must call $next (not redirect).
    $allowedRequest = Request::create(route('vendor.password.change.show', absolute: false));
    $allowedRequest->setRouteResolver(fn () => (new Route('GET', '', []))->name('vendor.password.change.show'));
    $allowedRequest->setUserResolver(fn ($guard = null) => $guard === 'vendor' ? $vendor : null);

    $passed = false;
    $middleware->handle($allowedRequest, function () use (&$passed) {
        $passed = true;

        return response('ok');
    });
    expect($passed)->toBeTrue();

    // Non-allow-listed route: middleware must redirect to password.change.show.
    $blockedRequest = Request::create(route('vendor.dashboard', absolute: false));
    $blockedRequest->setRouteResolver(fn () => (new Route('GET', '', []))->name('vendor.dashboard'));
    $blockedRequest->setUserResolver(fn ($guard = null) => $guard === 'vendor' ? $vendor : null);

    $blockedCalled = false;
    $response = $middleware->handle($blockedRequest, function () use (&$blockedCalled) {
        $blockedCalled = true;

        return response('should not reach');
    });

    expect($blockedCalled)->toBeFalse();
    expect($response->isRedirect(route('vendor.password.change.show')))->toBeTrue();
});

test('phase 2 smoke: vendor broker end-to-end', function () {
    // 1. Schema + config wired
    expect(Schema::hasTable('vendor_password_reset_tokens'))->toBeTrue();
    expect(Schema::hasColumn('vendors', 'must_change_password'))->toBeTrue();
    expect(config('auth.passwords.vendors.table'))->toBe('vendor_password_reset_tokens');

    // 2. Model contract
    $vendor = Vendor::factory()->create(['email' => 'smoke@example.com']);
    expect($vendor)->toBeInstanceOf(CanResetPassword::class);
    expect($vendor->getEmailForPasswordReset())->toBe('smoke@example.com');

    // 3. Broker sends reset link, custom notification fires
    Notification::fake();
    $status = Password::broker('vendors')->sendResetLink(['email' => $vendor->email]);
    expect($status)->toBe(Password::RESET_LINK_SENT);
    Notification::assertSentTo($vendor, VendorResetPasswordNotification::class);

    // 4. Token row lands in the vendor-scoped table (not the users-scoped one)
    expect(DB::table('vendor_password_reset_tokens')->where('email', $vendor->email)->count())->toBe(1);
    expect(DB::table('password_reset_tokens')->where('email', $vendor->email)->count())->toBe(0);
});
