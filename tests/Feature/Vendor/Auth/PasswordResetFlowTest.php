<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\VendorResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

/** Mirrors createAuthorizedUser() pattern used elsewhere in the test suite. */
function adminWithVendorUpdatePermission(): User
{
    $role = Role::factory()->create();
    $perm = Permission::firstOrCreate(
        ['slug' => 'vendors.update'],
        ['name' => 'Vendors Update', 'module' => 'vendors']
    );
    $role->permissions()->attach($perm->id);

    return User::factory()->create(['role_id' => $role->id]);
}

// ── Guest: forgot-password ───────────────────────────────────────────

it('renders the forgot-password page', function () {
    $this->get(route('vendor.password.request'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('vendor/ForgotPassword'));
});

it('does not leak whether an email exists', function () {
    Notification::fake();

    $this->post(route('vendor.password.email'), ['email' => 'nobody@example.com'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('sends the reset notification when the email matches a vendor', function () {
    Notification::fake();
    $vendor = Vendor::factory()->create(['email' => 'ahmed@example.com']);

    $this->post(route('vendor.password.email'), ['email' => 'ahmed@example.com'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($vendor, VendorResetPasswordNotification::class);
});

// ── Guest: reset via token ───────────────────────────────────────────

it('renders the reset-password page with token and email props', function () {
    $this->get(route('vendor.password.reset', ['token' => 'abc123']).'?email=x@x.com')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('vendor/ResetPassword')
            ->where('token', 'abc123')
            ->where('email', 'x@x.com')
        );
});

it('resets the password with a valid token', function () {
    $vendor = Vendor::factory()->create(['email' => 'ahmed@example.com']);
    $token = Password::broker('vendors')->createToken($vendor);

    $this->post(route('vendor.password.update'), [
        'token' => $token,
        'email' => 'ahmed@example.com',
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ])->assertRedirect(route('vendor.login'));

    expect(Hash::check('NewSecurePass456!', $vendor->fresh()->password))->toBeTrue();
});

it('rejects an invalid reset token', function () {
    Vendor::factory()->create(['email' => 'ahmed@example.com']);

    $this->post(route('vendor.password.update'), [
        'token' => 'totally-fake-token',
        'email' => 'ahmed@example.com',
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ])->assertSessionHasErrors('email');
});

it('requires password confirmation on reset', function () {
    $vendor = Vendor::factory()->create(['email' => 'ahmed@example.com']);
    $token = Password::broker('vendors')->createToken($vendor);

    $this->post(route('vendor.password.update'), [
        'token' => $token,
        'email' => 'ahmed@example.com',
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'Different!',
    ])->assertSessionHasErrors('password');
});

// ── Authenticated: self-service ──────────────────────────────────────

it('allows an authenticated vendor to change their password', function () {
    $vendor = Vendor::factory()->create(['password' => Hash::make('OldPass123!')]);

    $this->actingAs($vendor, 'vendor')
        ->put(route('vendor.password.change'), [
            'current_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Hash::check('NewPass456!', $vendor->fresh()->password))->toBeTrue();
});

it('rejects wrong current password on self-service change', function () {
    $vendor = Vendor::factory()->create(['password' => Hash::make('OldPass123!')]);

    $this->actingAs($vendor, 'vendor')
        ->put(route('vendor.password.change'), [
            'current_password' => 'WrongPass!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('OldPass123!', $vendor->fresh()->password))->toBeTrue();
});

it('clears must_change_password after successful self-service change', function () {
    $vendor = Vendor::factory()->create([
        'password' => Hash::make('TempPass123!'),
        'must_change_password' => true,
    ]);

    $this->actingAs($vendor, 'vendor')
        ->put(route('vendor.password.change'), [
            'current_password' => 'TempPass123!',
            'password' => 'MyRealPass456!',
            'password_confirmation' => 'MyRealPass456!',
        ])
        ->assertRedirect();

    expect($vendor->fresh()->must_change_password)->toBeFalse();
});

// ── Admin: send reset email ──────────────────────────────────────────

it('admin can send a reset email to a vendor', function () {
    Notification::fake();
    $admin = adminWithVendorUpdatePermission();
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.vendors.send-password-reset', $vendor))
        ->assertRedirect();

    Notification::assertSentTo($vendor, VendorResetPasswordNotification::class);
});

// ── Admin: temporary password ────────────────────────────────────────

it('admin can generate a temporary password and must_change_password flips true', function () {
    $admin = adminWithVendorUpdatePermission();
    $vendor = Vendor::factory()->create(['password' => Hash::make('OriginalPass!')]);

    $response = $this->actingAs($admin)
        ->post(route('admin.vendors.force-temporary-password', $vendor));

    $response->assertRedirect()
        ->assertSessionHas('temporary_password');

    $fresh = $vendor->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('OriginalPass!', $fresh->password))->toBeFalse();
});

// ── Audit log entries ────────────────────────────────────────────────

it('writes an audit log entry for admin temp-password reset', function () {
    $admin = adminWithVendorUpdatePermission();
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.vendors.force-temporary-password', $vendor));

    expect(AuditLog::query()
        ->where('action', 'password_reset_admin_temp')
        ->where('auditable_id', $vendor->id)
        ->exists()
    )->toBeTrue();
});
